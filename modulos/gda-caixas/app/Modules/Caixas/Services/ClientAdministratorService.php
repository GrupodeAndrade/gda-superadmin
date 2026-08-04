<?php
declare(strict_types=1);
namespace Gda\Modules\Caixas\Services;

use PDO;
use RuntimeException;

final class ClientAdministratorService
{
    public function __construct(private PDO $db,private int $companyId,private int $actorId,string $actorRole='client_admin') { if($actorRole!=='client_admin')throw new RuntimeException('Ação exclusiva do administrador do cliente.'); }

    public function all(): array
    {
        $stmt=$this->db->prepare("SELECT id,name,email,active,created_at FROM users WHERE company_id=? AND role='client_admin' ORDER BY active DESC,name,id");$stmt->execute([$this->companyId]);return$stmt->fetchAll();
    }

    public function create(array $input): int
    {
        $name=trim((string)($input['name']??''));$email=strtolower(trim((string)($input['email']??'')));$password=(string)($input['password']??'');$confirmation=(string)($input['password_confirmation']??'');
        if($name===''||strlen($name)>120)throw new RuntimeException('Informe um nome válido com até 120 caracteres.');if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>160)throw new RuntimeException('Informe um e-mail válido.');$this->validatePassword($password,$confirmation);$this->ensureUniqueEmail($email);
        try{$stmt=$this->db->prepare("INSERT INTO users(company_id,name,email,password,role,active)VALUES(?,?,?,?, 'client_admin',?)");$stmt->execute([$this->companyId,$name,$email,password_hash($password,PASSWORD_DEFAULT),!empty($input['active'])?1:0]);}catch(\PDOException){throw new RuntimeException('Não foi possível cadastrar o administrador. Verifique se o e-mail já está em uso.');}return(int)$this->db->lastInsertId();
    }

    public function updateName(int $id,string $name): void
    {
        $name=trim($name);if($name===''||strlen($name)>120)throw new RuntimeException('Informe um nome válido com até 120 caracteres.');$this->owned($id);$stmt=$this->db->prepare("UPDATE users SET name=? WHERE id=? AND company_id=? AND role='client_admin'");$stmt->execute([$name,$id,$this->companyId]);
    }

    public function setActive(int $id,bool $active): void
    {
        $target=$this->owned($id);if(!$active){if($id===$this->actorId)throw new RuntimeException('Você não pode inativar o próprio acesso.');$stmt=$this->db->prepare("SELECT COUNT(*) FROM users WHERE company_id=? AND role='client_admin' AND active=1");$stmt->execute([$this->companyId]);if((int)$stmt->fetchColumn()<=1)throw new RuntimeException('A empresa deve manter ao menos um administrador ativo.');}
        $stmt=$this->db->prepare("UPDATE users SET active=? WHERE id=? AND company_id=? AND role='client_admin'");$stmt->execute([$active?1:0,$target['id'],$this->companyId]);
    }

    public function resetPassword(int $id,string $password,string $confirmation): void
    {
        $this->owned($id);if($id===$this->actorId)throw new RuntimeException('Use a seção Alterar senha para modificar a própria senha.');$this->validatePassword($password,$confirmation);$stmt=$this->db->prepare("UPDATE users SET password=? WHERE id=? AND company_id=? AND role='client_admin'");$stmt->execute([password_hash($password,PASSWORD_DEFAULT),$id,$this->companyId]);error_log('Client administrator password reset: company='.$this->companyId.' target='.$id.' actor='.$this->actorId);
    }

    private function owned(int $id): array
    {
        $stmt=$this->db->prepare("SELECT id,name,email,active FROM users WHERE id=? AND company_id=? AND role='client_admin' LIMIT 1");$stmt->execute([$id,$this->companyId]);$row=$stmt->fetch();if(!$row)throw new RuntimeException('Administrador não encontrado neste ambiente.');return$row;
    }
    private function ensureUniqueEmail(string $email): void
    {
        $stmt=$this->db->prepare('SELECT COUNT(*) FROM users WHERE email=?');$stmt->execute([$email]);if((int)$stmt->fetchColumn()>0)throw new RuntimeException('Este e-mail já está cadastrado.');
    }
    private function validatePassword(string $password,string $confirmation): void
    {
        if(strlen($password)<12)throw new RuntimeException('A senha deve ter pelo menos 12 caracteres.');if(!hash_equals($password,$confirmation))throw new RuntimeException('A confirmação da senha não confere.');
    }
}
