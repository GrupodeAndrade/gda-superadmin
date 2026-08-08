<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class PlatformIdentityMigrationService
{
    private const ID = '20260808_create_platform_identity';

    public static function run(?PDO $database = null): void
    {
        $database ??= Database::connection();
        $database->exec("CREATE TABLE IF NOT EXISTS platform_identity (
            id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
            platform_uuid CHAR(36) NOT NULL UNIQUE,
            installation_id VARCHAR(190) NOT NULL UNIQUE,
            private_key_pem TEXT NOT NULL,
            public_key_pem TEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        (new PlatformIdentityStore($database))->loadOrCreate();

        $statement = $database->prepare("INSERT INTO migrations (migration_id,version,description,status,executed_at) VALUES (?,'1.0.0','Cria a identidade técnica permanente da plataforma.','executed',NOW()) ON DUPLICATE KEY UPDATE status='executed',executed_at=COALESCE(executed_at,NOW())");
        $statement->execute([self::ID]);
    }
}
