<?php
// Fallback para instalações tradicionais. Em Docker/Coolify, use as variáveis
// DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME e DB_PASSWORD; elas têm precedência.
return [
    'installed' => false,
    'app_name' => 'GDA Plataforma',
    'app_url' => 'http://localhost/gda-plataforma',
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'gda_plataforma',
        'user' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
    ],
];
