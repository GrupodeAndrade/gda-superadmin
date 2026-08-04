<?php
return function(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS app_updates (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      version_from VARCHAR(30) NOT NULL,
      version_to VARCHAR(30) NOT NULL,
      package_name VARCHAR(255) NULL,
      status ENUM('started','completed','failed','rolled_back') NOT NULL DEFAULT 'started',
      backup_file VARCHAR(255) NULL,
      details TEXT NULL,
      started_at DATETIME NOT NULL,
      finished_at DATETIME NULL,
      INDEX idx_updates_status(status),
      INDEX idx_updates_version(version_to)
    ) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (migration VARCHAR(190) PRIMARY KEY, applied_at DATETIME NOT NULL) ENGINE=InnoDB");
};
