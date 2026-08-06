<?php
return [
    'name' => 'GDA Plataforma',
    'timezone' => 'America/Bahia',
    'session_name' => 'gda_super_admin',
    'max_update_mb' => 50,
    'root' => dirname(__DIR__),
    'module_attachment' => [
        'timeout' => max(2,min(15,(int)(getenv('GDA_MODULE_ATTACH_TIMEOUT')?:5))),
        'max_response_bytes' => max(16384,min(1048576,(int)(getenv('GDA_MODULE_ATTACH_MAX_RESPONSE_BYTES')?:262144))),
        'allowed_domain' => getenv('GDA_MODULE_ALLOWED_DOMAIN')?:'gda.app.br',
        'platform_public_url' => getenv('GDA_PLATFORM_PUBLIC_URL')?:'',
    ],
];
