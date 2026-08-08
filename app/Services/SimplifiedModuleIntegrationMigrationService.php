<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class SimplifiedModuleIntegrationMigrationService
{
    private const ID='20260808_simplified_module_integration';
    public static function run(?PDO $db=null):void
    {
        $db??=Database::connection();$done=$db->prepare("SELECT COUNT(*) FROM migrations WHERE migration_id=? AND status='executed'");$done->execute([self::ID]);if((int)$done->fetchColumn()>0)return;
        $columns=['integration_status'=>"VARCHAR(30) NOT NULL DEFAULT 'nao_integrado'",'machine_credential_encrypted'=>'TEXT NULL','pairing_request_id'=>'CHAR(36) NULL','integrated_at'=>'DATETIME NULL'];
        foreach($columns as$name=>$definition){$check=$db->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='project_connections' AND column_name=?");$check->execute([$name]);if((int)$check->fetchColumn()===0)$db->exec("ALTER TABLE project_connections ADD COLUMN $name $definition");}
        $db->exec("CREATE TABLE IF NOT EXISTS module_pairing_receipts(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,request_id CHAR(36) NOT NULL UNIQUE,module_uuid VARCHAR(190) NOT NULL,pairing_token_hash CHAR(64) NOT NULL,module_url VARCHAR(500) NOT NULL,status ENUM('processing','validated','confirmed','failed') NOT NULL DEFAULT 'processing',error_code VARCHAR(100) NULL,received_at DATETIME NOT NULL,validated_at DATETIME NULL,confirmed_at DATETIME NULL,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX idx_pairing_module_status(module_uuid,status),CONSTRAINT fk_pairing_module FOREIGN KEY(module_uuid) REFERENCES project_connections(module_instance_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $db->exec("CREATE TABLE IF NOT EXISTS module_access_issuances(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,module_uuid VARCHAR(190) NOT NULL,jti_hash CHAR(64) NOT NULL UNIQUE,issued_by_user_id BIGINT UNSIGNED NOT NULL,issued_at DATETIME NOT NULL,expires_at DATETIME NOT NULL,INDEX idx_access_module_expiry(module_uuid,expires_at),CONSTRAINT fk_access_module FOREIGN KEY(module_uuid) REFERENCES project_connections(module_instance_id),CONSTRAINT fk_access_user FOREIGN KEY(issued_by_user_id) REFERENCES users(id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $stmt=$db->prepare("INSERT INTO migrations(migration_id,version,description,status,executed_at)VALUES(?,'1.0.0','Adiciona descoberta, pareamento e acesso federado de módulos.','executed',NOW()) ON DUPLICATE KEY UPDATE status='executed',executed_at=COALESCE(executed_at,NOW())");$stmt->execute([self::ID]);
    }
}
