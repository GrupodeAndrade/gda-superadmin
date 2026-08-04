<?php
declare(strict_types=1);

require __DIR__ . '/autoload.php';

$appConfig = require dirname(__DIR__) . '/config/app.php';
$localFile = dirname(__DIR__) . '/config/local.php';
$localConfig = is_file($localFile) ? require $localFile : ['installed' => false];

date_default_timezone_set($appConfig['timezone']);
ini_set('default_charset', 'UTF-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_name($appConfig['session_name']);
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

set_exception_handler(static function (Throwable $e) use ($appConfig): void {
    $line = sprintf("[%s] %s em %s:%d\n%s\n", date('c'), $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString());
    @file_put_contents($appConfig['root'] . '/storage/logs/error.log', $line, FILE_APPEND | LOCK_EX);
    http_response_code(500);
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=UTF-8');
    }
    $errorView = $appConfig['root'] . '/app/Views/errors/500.php';
    is_file($errorView) ? require $errorView : print('Erro interno.');
});

return ['app' => $appConfig, 'local' => $localConfig];

