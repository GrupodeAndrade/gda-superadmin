<?php
declare(strict_types=1);
namespace Gda\Core\Auth;

use Gda\Core\Security\SessionManager;
use Gda\Modules\Caixas\Repositories\UserRepository;

final class AuthService
{
    public function __construct(private UserRepository $users) {}

    public function attempt(string $email, string $password): bool
    {
        $user = $this->users->findByEmail(trim($email));
        if (!$user || !in_array($user['role']??'', ['client_admin','operator'],true) || empty($user['company_id']) || !password_verify($password, $user['password'])) return false;
        SessionManager::regenerate();
        $_SESSION = ['auth_scope'=>'client','user'=>['id'=>(int)$user['id'], 'name'=>$user['name'], 'email'=>$user['email'], 'role'=>$user['role'], 'company_id'=>(int)$user['company_id'], 'authenticated_at'=>time()]];
        return true;
    }

    public function check(): bool { return !empty($_SESSION['user']['id']); }
    public function user(): ?array { return $_SESSION['user'] ?? null; }
    public function logout(): void { SessionManager::logout(); }
}
