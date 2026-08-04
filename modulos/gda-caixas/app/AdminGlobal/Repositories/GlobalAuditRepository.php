<?php
declare(strict_types=1);
namespace Gda\AdminGlobal\Repositories;
use PDO;

final class GlobalAuditRepository{
 public function __construct(private PDO $db){}
 public function record(string $action,?int $userId=null,?string $type=null,int|string|null $targetId=null,array $metadata=[]):void{$safe=array_intersect_key($metadata,array_flip(['result','status','version','reason','changed_keys']));$s=$this->db->prepare('INSERT INTO global_audit_logs(user_id,action,target_type,target_id,ip_address,user_agent,metadata)VALUES(?,?,?,?,?,?,?)');$s->execute([$userId,$action,$type,$targetId===null?null:(string)$targetId,substr((string)($_SERVER['REMOTE_ADDR']??''),0,64),substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500),$safe?json_encode($safe,JSON_UNESCAPED_UNICODE):null]);}
 public function recent(int $limit=100):array{$limit=max(1,min(500,$limit));return$this->db->query("SELECT a.*,u.name user_name FROM global_audit_logs a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.id DESC LIMIT {$limit}")->fetchAll();}
}
