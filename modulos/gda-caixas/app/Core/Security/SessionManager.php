<?php
declare(strict_types=1);
namespace Gda\Core\Security;

final class SessionManager
{
    public static function start(bool $forceHttps = false): void
    {
        self::startScoped('GDA_CLIENT_SESSION','/',$forceHttps);
    }

    public static function startGlobal(bool $forceHttps = false): void
    {
        self::startScoped('GDA_GLOBAL_SESSION','/admin-gda',$forceHttps);
    }

    private static function startScoped(string $name,string $path,bool $forceHttps): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        $secure = $forceHttps || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Lax');
        session_name($name);
        session_set_cookie_params(['lifetime'=>0, 'path'=>$path, 'secure'=>$secure, 'httponly'=>true, 'samesite'=>'Lax']);
        session_start();
    }

    public static function regenerate(): void { session_regenerate_id(true); }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }
}
