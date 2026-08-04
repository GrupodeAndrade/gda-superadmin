<?php
declare(strict_types=1);
namespace Gda\AdminGlobal;

use Gda\Core\Auth\PermissionService;

final class GlobalAdministrationPolicy
{
    public function __construct(private PermissionService $permissions) {}
    private const ALLOWED=['dashboard','clients.list','clients.view','clients.status','clients.validate','diagnostics.view','audit.view','settings.manage','updates.manage','cycles.view','session.logout'];
    public function allows(string $ability):bool{return in_array($ability,self::ALLOWED,true)&&$this->permissions->allows('global.'.$ability);}
    public function operateClient():bool{return false;}
    public function impersonate():bool{return false;}
}
