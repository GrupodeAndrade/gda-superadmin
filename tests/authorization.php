<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/autoload.php';

use App\Services\Auth;

$cases = [
    'super_admin ativo pode acessar' => [true, ['role' => 'super_admin', 'status' => 'active']],
    'client_admin não pode acessar' => [false, ['role' => 'client_admin', 'status' => 'active']],
    'support não pode acessar' => [false, ['role' => 'support', 'status' => 'active']],
    'financial não pode acessar' => [false, ['role' => 'financial', 'status' => 'active']],
    'super_admin inativo não pode acessar' => [false, ['role' => 'super_admin', 'status' => 'inactive']],
];

foreach ($cases as $description => [$expected, $user]) {
    if (Auth::isSuperAdmin($user) !== $expected) {
        fwrite(STDERR, "Falhou: {$description}\n");
        exit(1);
    }
}

echo "Autorização aprovada: " . count($cases) . " casos.\n";
