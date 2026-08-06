<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class DashboardService
{
    public function __construct(private PDO $database, private string $root) {}
    public function overview(): array { return ['stats'=>$this->stats(),'modules'=>$this->modules(),'clients'=>$this->clients(),'activities'=>$this->activities(),'platform'=>$this->platform(),'systemChecks'=>$this->systemChecks()]; }
    private function stats(): array { $stats=$this->database->query("SELECT (SELECT COUNT(*) FROM clients WHERE deleted_at IS NULL) total_clients,(SELECT COUNT(*) FROM clients WHERE status='active' AND deleted_at IS NULL) active_clients,(SELECT COUNT(*) FROM clients WHERE status='inactive' AND deleted_at IS NULL) inactive_clients,(SELECT COUNT(*) FROM projects WHERE deleted_at IS NULL) total_projects,(SELECT COUNT(*) FROM projects WHERE status IN ('active','production') AND deleted_at IS NULL) production_projects,(SELECT COUNT(*) FROM projects WHERE status='deploying' AND deleted_at IS NULL) deploying_projects")->fetch();return array_map('intval',$stats?:[]); }
    private function modules(): array { return $this->database->query("SELECT p.*,COUNT(DISTINCT CASE WHEN cp.status='active' AND c.deleted_at IS NULL THEN cp.client_id END) client_count FROM projects p LEFT JOIN client_projects cp ON cp.project_id=p.id LEFT JOIN clients c ON c.id=cp.client_id WHERE p.deleted_at IS NULL GROUP BY p.id ORDER BY CASE p.status WHEN 'active' THEN 0 WHEN 'production' THEN 0 WHEN 'deploying' THEN 1 WHEN 'development' THEN 2 WHEN 'maintenance' THEN 3 ELSE 4 END,p.name LIMIT 4")->fetchAll(); }
    private function clients(): array { return $this->database->query("SELECT c.*,COUNT(DISTINCT CASE WHEN cp.status='active' THEN cp.project_id END) project_count FROM clients c LEFT JOIN client_projects cp ON cp.client_id=c.id WHERE c.deleted_at IS NULL GROUP BY c.id ORDER BY c.created_at DESC LIMIT 5")->fetchAll(); }
    private function activities(): array { return $this->database->query("SELECT l.*,u.name user_name FROM activity_logs l LEFT JOIN users u ON u.id=l.user_id ORDER BY l.created_at DESC LIMIT 10")->fetchAll(); }
    private function platform(): array { $version=json_decode((string)@file_get_contents($this->root.'/version.json'),true);$backups=glob($this->root.'/storage/backups/*.zip')?:[];$lastBackupAt=$backups?max(array_map('filemtime',$backups)):null;$lastUpdate=$this->database->query("SELECT new_version,updated_at FROM system_updates WHERE status='completed' ORDER BY updated_at DESC LIMIT 1")->fetch()?:null;return ['version'=>(string)($version['version']??'?'),'last_backup_at'=>$lastBackupAt?:null,'last_update'=>$lastUpdate]; }
    private function systemChecks(): array { $checks=[['label'=>'Banco de dados','state'=>'operational','text'=>'Operacional']];foreach(['Diretório de backups'=>'storage/backups','Diretório de cache'=>'storage/cache','Diretório de logs'=>'storage/logs','Diretório temporário'=>'storage/temp','Diretório de atualizações'=>'storage/updates'] as $label=>$path)$checks[]=['label'=>$label,...self::pathState($this->root.'/'.$path)];$environment=array_map(static fn(string $name):bool=>getenv($name)!==false,['DB_HOST','DB_PORT','DB_DATABASE','DB_USERNAME','DB_PASSWORD']);$configured=!in_array(false,$environment,true)||is_file($this->root.'/config/local.php');$checks[]=['label'=>'Configuração da aplicação','state'=>$configured?'operational':'attention','text'=>$configured?'Operacional':'Atenção'];return $checks; }
    public static function pathState(string $path): array { if(!is_dir($path))return ['state'=>'unavailable','text'=>'Indisponível'];if(!is_writable($path))return ['state'=>'attention','text'=>'Atenção'];return ['state'=>'writable','text'=>'Gravável']; }
}
