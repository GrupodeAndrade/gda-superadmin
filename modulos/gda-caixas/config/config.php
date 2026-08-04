<?php
$localFile = __DIR__ . '/local.php';
$local = is_file($localFile) ? require $localFile : [];

$defaults = [
    'app_name' => 'GDA Caixas',
    'app_url' => 'https://caixas.gda.app.br',
    'base_url' => '',
    'force_https' => false,
    'upload_max_bytes' => 5 * 1024 * 1024,
    'admin_global' => [
        'activation_token' => '',
    ],
    'db' => [
        'host' => '',
        'port' => '3306',
        'database' => '',
        'username' => '',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
];

return array_replace_recursive($defaults, is_array($local) ? $local : []);
