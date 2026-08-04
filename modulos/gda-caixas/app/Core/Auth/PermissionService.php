<?php
declare(strict_types=1);
namespace Gda\Core\Auth;

final class PermissionService
{
    public function __construct(private RoleService $roles) {}
    public function allows(string $permission): bool
    {
        $role = $this->roles->currentRole();
        if ($role === RoleService::GLOBAL_ADMIN) return str_starts_with($permission, 'global.');
        if ($role === RoleService::CLIENT_ADMIN) return in_array($permission,[
            'caixas.view','caixas.operate','caixas.administer','caixas.backup'
        ],true);
        if ($role === RoleService::OPERATOR) return in_array($permission,['caixas.view','caixas.operate'],true);
        return false;
    }
}
