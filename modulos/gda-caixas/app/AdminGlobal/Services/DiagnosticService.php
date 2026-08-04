<?php
declare(strict_types=1);
namespace Gda\AdminGlobal\Services;
use PDO;

final class DiagnosticService{
 public function __construct(private PDO $db,private string $root){}
 public function report():array{$version=(string)$this->db->query('SELECT VERSION()')->fetchColumn();$checks=['companies_without_valid_status'=>"SELECT COUNT(*) FROM companies WHERE status NOT IN('active','inactive')",'client_users_without_company'=>"SELECT COUNT(*) FROM users WHERE role IN('client_admin','operator') AND company_id IS NULL",'orphan_cycles'=>'SELECT COUNT(*) FROM cycles c LEFT JOIN companies co ON co.id=c.company_id WHERE co.id IS NULL','orphan_participants'=>'SELECT COUNT(*) FROM participants p LEFT JOIN companies co ON co.id=p.company_id WHERE co.id IS NULL','orphan_contributions'=>'SELECT COUNT(*) FROM contributions c LEFT JOIN cycles cy ON cy.id=c.cycle_id WHERE cy.id IS NULL'];$integrity=[];foreach($checks as$key=>$sql)$integrity[$key]=(int)$this->db->query($sql)->fetchColumn();$applied=$this->db->query('SELECT migration FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);$files=array_map('basename',glob($this->root.'/database/migrations/*.{php,sql}',GLOB_BRACE)?:[]);return['database'=>'connected','mariadb'=>$version,'php'=>PHP_VERSION,'extensions'=>['pdo_mysql'=>extension_loaded('pdo_mysql'),'zip'=>extension_loaded('zip'),'fileinfo'=>extension_loaded('fileinfo'),'mbstring'=>extension_loaded('mbstring')],'directories'=>['storage'=>is_writable($this->root.'/storage'),'backups'=>is_writable($this->root.'/storage/backups'),'updates'=>is_writable($this->root.'/storage/updates')],'module_version'=>APP_VERSION,'pending_migrations'=>array_values(array_diff($files,$applied)),'integrity'=>$integrity];}
}
