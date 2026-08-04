<?php
declare(strict_types=1);
namespace Gda\AdminGlobal\Services;
use Gda\AdminGlobal\Repositories\GlobalAuditRepository;use Gda\AdminGlobal\Repositories\GlobalUserRepository;use Gda\Core\Security\SessionManager;

final class GlobalAuthService{
 public function __construct(private GlobalUserRepository $users,private GlobalAuditRepository $audit){}
 public function attempt(string $email,string $password):bool{$u=$this->users->findGlobalByEmail($email);if(!$u||!(bool)$u['active']||!password_verify($password,$u['password'])){$this->audit->record('global_login_failed',null,null,null,['result'=>'denied']);return false;}SessionManager::regenerate();$_SESSION=['user_id'=>(int)$u['id'],'role'=>'global_admin','auth_scope'=>'global','authenticated_at'=>time()];$this->audit->record('global_login',(int)$u['id'],null,null,['result'=>'success']);return true;}
 public function logout():void{$id=(int)($_SESSION['user_id']??0);if($id)$this->audit->record('global_logout',$id);SessionManager::logout();}
}
