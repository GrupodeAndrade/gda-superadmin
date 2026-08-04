<?php
declare(strict_types=1);

return static function(PDO $pdo):void{
    $columnExists=static function(string $table,string $column)use($pdo):bool{$s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');$s->execute([$table,$column]);return(int)$s->fetchColumn()>0;};
    $indexExists=static function(string $table,string $index)use($pdo):bool{$s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');$s->execute([$table,$index]);return(int)$s->fetchColumn()>0;};
    if(!$columnExists('users','active'))$pdo->exec('ALTER TABLE users ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1 AFTER role');
    if(!$indexExists('users','idx_users_role_active'))$pdo->exec('ALTER TABLE users ADD INDEX idx_users_role_active(role,active)');
    $pdo->exec("CREATE TABLE IF NOT EXISTS global_audit_logs(
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NULL,action VARCHAR(100) NOT NULL,
      target_type VARCHAR(80) NULL,target_id VARCHAR(100) NULL,ip_address VARCHAR(64) NULL,user_agent VARCHAR(500) NULL,
      metadata TEXT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_global_audit_created(created_at),INDEX idx_global_audit_action(action),
      FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
    )ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS global_settings(
      setting_key VARCHAR(100) PRIMARY KEY,setting_value TEXT NULL,updated_by INT UNSIGNED NULL,
      updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      FOREIGN KEY(updated_by) REFERENCES users(id) ON DELETE SET NULL
    )ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
};
