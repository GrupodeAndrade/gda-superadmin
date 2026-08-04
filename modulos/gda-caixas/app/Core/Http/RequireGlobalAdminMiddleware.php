<?php
declare(strict_types=1);
namespace Gda\Core\Http;
use Gda\AdminGlobal\Repositories\GlobalUserRepository;

final class RequireGlobalAdminMiddleware{
 public function __construct(private GlobalUserRepository $users,private int $maxAge=28800){}
 public function handle():void{$id=(int)($_SESSION['user_id']??0);$at=(int)($_SESSION['authenticated_at']??0);if(($_SESSION['auth_scope']??null)!=='global'||($_SESSION['role']??null)!=='global_admin'||$id<1||$at<time()-$this->maxAge||!$this->users->validGlobal($id))throw new \RuntimeException('GLOBAL_AUTH_REQUIRED');}
}
