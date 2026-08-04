<?php
declare(strict_types=1);
namespace Gda\AdminGlobal\Repositories;
use PDO;

final class GlobalUserRepository{
 public function __construct(private PDO $db){}
 public function findGlobalByEmail(string $email):?array{$s=$this->db->prepare("SELECT id,email,password,role,company_id,active FROM users WHERE email=? AND role='global_admin' AND company_id IS NULL LIMIT 1");$s->execute([trim($email)]);return$s->fetch()?:null;}
 public function validGlobal(int $id):bool{$s=$this->db->prepare("SELECT COUNT(*) FROM users WHERE id=? AND role='global_admin' AND company_id IS NULL AND active=1");$s->execute([$id]);return(bool)$s->fetchColumn();}
 public function hasGlobal():bool{return(bool)$this->db->query("SELECT COUNT(*) FROM users WHERE role='global_admin'")->fetchColumn();}
 public function createFirst(string $name,string $email,string $password):int{$this->db->beginTransaction();try{$this->db->query("SELECT id FROM users WHERE role='global_admin' FOR UPDATE");if($this->hasGlobal())throw new \RuntimeException('Ativação indisponível.');$s=$this->db->prepare("INSERT INTO users(company_id,name,email,password,role,active)VALUES(NULL,?,?,?,'global_admin',1)");$s->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT)]);$id=(int)$this->db->lastInsertId();$this->db->commit();return$id;}catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw$e;}}
}
