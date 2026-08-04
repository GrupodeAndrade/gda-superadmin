<?php
declare(strict_types=1);

session_save_path(sys_get_temp_dir());
session_start();
require dirname(__DIR__) . '/bootstrap/autoload.php';

use App\Services\Csrf;

$token = Csrf::token();
if (!Csrf::verify($token)) throw new RuntimeException('Token CSRF válido foi rejeitado.');
if (Csrf::verify('token-invalido')) throw new RuntimeException('Token CSRF inválido foi aceito.');
if (Csrf::verify(null)) throw new RuntimeException('Token CSRF ausente foi aceito.');

echo "CSRF aprovado: válido aceito; inválido e ausente rejeitados.\n";
