<?php
declare(strict_types=1);

return static function (PDO $pdo): void {
    $columnExists = static function (string $table, string $column) use ($pdo): bool {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');
        $stmt->execute([$table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    };
    $indexExists = static function (string $table, string $index) use ($pdo): bool {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');
        $stmt->execute([$table, $index]);
        return (int)$stmt->fetchColumn() > 0;
    };
    $tableExists = static function (string $table) use ($pdo): bool {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    };

    $pdo->exec("CREATE TABLE IF NOT EXISTS companies (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      gda_client_id VARCHAR(100) NULL,
      name VARCHAR(160) NOT NULL,
      identifier VARCHAR(100) NOT NULL,
      status ENUM('active','inactive') NOT NULL DEFAULT 'active',
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY uniq_companies_identifier(identifier),
      UNIQUE KEY uniq_companies_gda_client(gda_client_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS tenant_migration_log (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      migration VARCHAR(190) NOT NULL,
      event VARCHAR(100) NOT NULL,
      company_id INT UNSIGNED NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY uniq_tenant_migration_event(migration,event)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $companyColumns = [
        'gda_client_id' => 'VARCHAR(100) NULL AFTER id',
        'name' => "VARCHAR(160) NOT NULL DEFAULT 'Empresa' AFTER gda_client_id",
        'identifier' => 'VARCHAR(100) NULL AFTER name',
        'status' => "ENUM('active','inactive') NOT NULL DEFAULT 'active' AFTER identifier",
        'created_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER status',
        'updated_at' => 'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
    ];
    foreach ($companyColumns as $column => $definition) {
        if (!$columnExists('companies',$column)) $pdo->exec("ALTER TABLE companies ADD COLUMN `{$column}` {$definition}");
    }
    $pdo->exec("UPDATE companies SET identifier=CONCAT('company-',id) WHERE identifier IS NULL OR identifier=''");
    if (!$indexExists('companies','uniq_companies_identifier')) $pdo->exec('ALTER TABLE companies ADD UNIQUE KEY uniq_companies_identifier(identifier)');
    if (!$indexExists('companies','uniq_companies_gda_client')) $pdo->exec('ALTER TABLE companies ADD UNIQUE KEY uniq_companies_gda_client(gda_client_id)');
    $pdo->exec('ALTER TABLE companies MODIFY identifier VARCHAR(100) NOT NULL, ENGINE=InnoDB, DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

    foreach (['users','cycles','participants','app_settings','mobile_access_logs'] as $table) {
        if ($tableExists($table) && !$columnExists($table, 'company_id')) {
            $after = $table === 'app_settings' ? 'setting_key' : 'id';
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN company_id INT UNSIGNED NULL AFTER `{$after}`");
        }
        if ($tableExists($table) && !$indexExists($table, 'idx_'.$table.'_company')) {
            $pdo->exec("ALTER TABLE `{$table}` ADD INDEX `idx_{$table}_company` (company_id)");
        }
    }
    if ($tableExists('users') && !$columnExists('users', 'role')) {
        $pdo->exec("ALTER TABLE users ADD COLUMN role VARCHAR(40) NOT NULL DEFAULT 'client_admin' AFTER password");
    }

    $legacyCount = 0;
    foreach (['users','cycles','participants','app_settings','mobile_access_logs'] as $table) {
        if ($tableExists($table) && $columnExists($table, 'company_id')) {
            $legacyCount += (int)$pdo->query("SELECT COUNT(*) FROM `{$table}` WHERE company_id IS NULL")->fetchColumn();
        }
    }
    if ($legacyCount > 0) {
        $stmt = $pdo->prepare('SELECT id FROM companies WHERE identifier=? LIMIT 1');
        $stmt->execute(['legacy-default']);
        $companyId = (int)$stmt->fetchColumn();
        if (!$companyId) {
            $stmt = $pdo->prepare("INSERT INTO companies(gda_client_id,name,identifier,status) VALUES(NULL,?,?,'active')");
            $stmt->execute(['Empresa legada', 'legacy-default']);
            $companyId = (int)$pdo->lastInsertId();
        }
        foreach (['cycles','participants','app_settings','mobile_access_logs'] as $table) {
            if ($tableExists($table)) $pdo->prepare("UPDATE `{$table}` SET company_id=? WHERE company_id IS NULL")->execute([$companyId]);
        }
        if ($tableExists('users')) {
            $pdo->prepare("UPDATE users SET company_id=?,role='client_admin' WHERE company_id IS NULL AND role<>'global_admin'")->execute([$companyId]);
        }
        $pdo->prepare("INSERT IGNORE INTO tenant_migration_log(migration,event,company_id) VALUES('003_multi_tenant_foundation.php','legacy_data_assigned',?)")->execute([$companyId]);
    }
    if ($tableExists('app_settings')) {
        $pk = $pdo->query("SHOW INDEX FROM app_settings WHERE Key_name='PRIMARY'")->fetchAll(PDO::FETCH_ASSOC);
        $pkColumns = array_column($pk, 'Column_name');
        if ($pkColumns !== ['company_id','setting_key']) {
            $pdo->exec('ALTER TABLE app_settings MODIFY company_id INT UNSIGNED NOT NULL, DROP PRIMARY KEY, ADD PRIMARY KEY(company_id,setting_key)');
        }
    }
    if ($tableExists('mobile_access_logs') && $indexExists('mobile_access_logs','ip_address')) {
        $pdo->exec('ALTER TABLE mobile_access_logs DROP INDEX ip_address, ADD UNIQUE KEY uniq_mobile_company_ip(company_id,ip_address)');
    } elseif ($tableExists('mobile_access_logs') && !$indexExists('mobile_access_logs','uniq_mobile_company_ip')) {
        $pdo->exec('ALTER TABLE mobile_access_logs ADD UNIQUE KEY uniq_mobile_company_ip(company_id,ip_address)');
    }
    foreach (['cycles','participants','app_settings','mobile_access_logs'] as $table) {
        if ($tableExists($table)) $pdo->exec("ALTER TABLE `{$table}` MODIFY company_id INT UNSIGNED NOT NULL");
    }
    foreach (['users','cycles','participants','app_settings','mobile_access_logs'] as $table) {
        if (!$tableExists($table)) continue;
        $constraint='fk_'.$table.'_company';
        $stmt=$pdo->prepare("SELECT COUNT(*) FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME='company_id' AND REFERENCED_TABLE_NAME='companies' AND REFERENCED_COLUMN_NAME='id'");
        $stmt->execute([$table]);
        if (!(int)$stmt->fetchColumn()) $pdo->exec("ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraint}` FOREIGN KEY(company_id) REFERENCES companies(id)");
    }
};
