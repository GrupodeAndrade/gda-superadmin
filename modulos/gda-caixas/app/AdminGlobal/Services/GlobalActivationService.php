<?php
declare(strict_types=1);
namespace Gda\AdminGlobal\Services;
use Gda\AdminGlobal\Repositories\GlobalAuditRepository;use Gda\AdminGlobal\Repositories\GlobalUserRepository;

final class GlobalActivationService{
 public function __construct(private GlobalUserRepository $users,private GlobalAuditRepository $audit,private string $token){}
 public function available():bool{return!$this->users->hasGlobal();}
 public function activate(string $provided,string $name,string $email,string $password):int{if(!$this->available()||$this->token===''||!hash_equals($this->token,$provided))throw new \RuntimeException('Ativação indisponível ou token inválido.');if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<12)throw new \InvalidArgumentException('Dados inválidos; use senha com ao menos 12 caracteres.');$id=$this->users->createFirst($name,$email,$password);$this->audit->record('global_activation',$id,'user',$id,['result'=>'success']);return$id;}
}
