<?php
// Copie para config/local.php e preencha somente no ambiente de destino.
return [
    'admin_global' => [
        // Use um valor longo e aleatório apenas até criar o primeiro global_admin.
        'activation_token' => 'SUBSTITUA_POR_UM_TOKEN_ALEATORIO',
    ],
    'app_url' => 'https://caixas.gda.app.br',
    'base_url' => '',
    'force_https' => true,
    'db' => [
        'host' => 'db.exemplo.invalid',
        'port' => '3306',
        'database' => 'nome_do_banco',
        'username' => 'usuario_do_banco',
        'password' => 'SUBSTITUA_PELA_SENHA',
        'charset' => 'utf8mb4',
    ],
];
