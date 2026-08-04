<?php
declare(strict_types=1);
namespace Gda\Modules\Caixas\Policies;

use Gda\Core\Auth\PermissionService;

final class ClientAdministrationPolicy
{
    public function __construct(private PermissionService $permissions) {}
    public function administerModule(): bool { return $this->permissions->allows('caixas.administer'); }
    public function operate(): bool { return $this->permissions->allows('caixas.operate'); }
    public function createBackup(): bool { return $this->permissions->allows('caixas.backup'); }
}
