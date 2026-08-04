<?php
declare(strict_types=1);
namespace Gda\Core\Auth;

final class RoleService
{
    public const GLOBAL_ADMIN = 'global_admin';
    public const CLIENT_ADMIN = 'client_admin';
    public const OPERATOR = 'operator';

    public function currentRole(): ?string { return ($_SESSION['auth_scope']??null)==='global'?($_SESSION['role']??null):($_SESSION['user']['role']??null); }
}
