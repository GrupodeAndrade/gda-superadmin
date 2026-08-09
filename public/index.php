<?php
declare(strict_types=1);

$context = require dirname(__DIR__) . '/bootstrap/app.php';
require dirname(__DIR__) . '/app/Helpers/helpers.php';

$runtime = $context['runtime'];
if (empty($runtime['configured'])) {
    require dirname(__DIR__) . '/install/index.php';
    exit;
}

$installationState = App\Services\InstallationState::detect(App\Services\Database::connection());
App\Services\InstallationState::assertInstalledOrEmpty($installationState);
if ($installationState === App\Services\InstallationState::EMPTY) {
    require dirname(__DIR__) . '/install/index.php';
    exit;
}

if (is_file(dirname(__DIR__) . '/storage/maintenance.lock')) {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if (!str_contains($path, '/atualizacoes')) {
        http_response_code(503);
        header('Retry-After: 300');
        require dirname(__DIR__) . '/app/Views/errors/maintenance.php';
        exit;
    }
}

$router = new App\Services\Router();
require dirname(__DIR__) . '/routes/web.php';
$router->dispatch();
