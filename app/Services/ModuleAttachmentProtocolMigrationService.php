<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class ModuleAttachmentProtocolMigrationService
{
    private const ID = '20260807_extend_project_connections_protocol';
    public static function run(?PDO $db = null): void
    {
        $db ??= Database::connection();
        $done=$db->prepare("SELECT COUNT(*) FROM migrations WHERE migration_id=? AND status='executed'");$done->execute([self::ID]);if((int)$done->fetchColumn()>0)return;
        $columns=['module_code'=>'VARCHAR(100) NULL','module_slug'=>'VARCHAR(100) NULL','module_type'=>'VARCHAR(100) NULL','module_status'=>'VARCHAR(50) NULL','api_version'=>'VARCHAR(30) NULL','health_json'=>'JSON NULL','compatibility_json'=>'JSON NULL','minimum_version'=>'VARCHAR(30) NULL','last_communication_at'=>'DATETIME NULL','last_module_update_at'=>'DATETIME NULL'];
        foreach($columns as $name=>$definition){$check=$db->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='project_connections' AND column_name=?");$check->execute([$name]);if((int)$check->fetchColumn()===0)$db->exec("ALTER TABLE project_connections ADD COLUMN $name $definition");}
        $stmt=$db->prepare("INSERT INTO migrations(migration_id,version,description,status,executed_at) VALUES(?,'1.0.0','Estende conexões para o protocolo oficial em duas etapas.','executed',NOW()) ON DUPLICATE KEY UPDATE status='executed',executed_at=COALESCE(executed_at,NOW())");$stmt->execute([self::ID]);
    }
}
