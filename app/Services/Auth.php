<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

final class Auth
{
    public static function user(): ?array
    {
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        static $user;
        if ($user === null) {
            $stmt = Database::connection()->prepare('SELECT id, name, email, role, status FROM users WHERE id = ? AND deleted_at IS NULL');
            $stmt->execute([(int) $_SESSION['user_id']]);
            $user = $stmt->fetch() ?: false;
        }
        return $user ?: null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function isSuperAdmin(?array $user = null): bool
    {
        $user ??= self::user();
        return $user !== null && $user['role'] === 'super_admin' && $user['status'] === 'active';
    }

    public static function attempt(string $email, string $password): bool
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? AND role = "super_admin" AND status = "active" AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([mb_strtolower(trim($email))]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'], $params['httponly']);
        }
        session_destroy();
    }
}
