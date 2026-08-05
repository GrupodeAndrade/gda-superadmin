<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/autoload.php';

use App\Services\Database;
use App\Services\DatabaseConnectionException;
use App\Services\InstallationState;
use App\Services\RuntimeConfig;

function assertRuntime(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function expectSafeFailure(callable $callback, string $scenario): void
{
    try {
        $callback();
    } catch (Throwable $e) {
        foreach (['internal-db', 'runtime-user', 'runtime-password'] as $sensitive) {
            assertRuntime(!str_contains($e->getMessage(), $sensitive), "$scenario revelou informação sensível.");
        }
        return;
    }
    throw new RuntimeException("$scenario deveria falhar de forma fechada.");
}

$environment = [
    'DB_HOST' => 'internal-db',
    'DB_PORT' => '3306',
    'DB_DATABASE' => 'runtime-database',
    'DB_USERNAME' => 'runtime-user',
    'DB_PASSWORD' => 'runtime-password',
    'APP_URL' => 'https://admin.example.test',
];

$runtime = RuntimeConfig::load(dirname(__DIR__), $environment);
assertRuntime($runtime['source'] === 'environment', 'Ambiente não recebeu precedência.');
assertRuntime($runtime['db']['name'] === 'runtime-database', 'Banco do ambiente não foi carregado.');
assertRuntime($runtime['app_url'] === 'https://admin.example.test', 'APP_URL não foi carregada.');

$optionalUrl = $environment;
unset($optionalUrl['APP_URL']);
assertRuntime(RuntimeConfig::load(dirname(__DIR__), $optionalUrl)['app_url'] === null, 'APP_URL opcional foi tratada como obrigatória.');
$emptyPassword = $environment;
$emptyPassword['DB_PASSWORD'] = '';
assertRuntime(RuntimeConfig::load(dirname(__DIR__), $emptyPassword)['db']['password'] === '', 'Senha vazia explicitamente configurada não foi aceita.');

$tempRoot = sys_get_temp_dir() . '/gda-runtime-' . bin2hex(random_bytes(6));
mkdir($tempRoot . '/config', 0777, true);
$localConfig = <<<'PHP'
<?php
return [
    'installed' => true,
    'app_url' => 'https://legacy.example.test',
    'db' => [
        'host' => 'legacy-db',
        'port' => 3306,
        'name' => 'legacy-database',
        'user' => 'legacy-user',
        'password' => 'legacy-password',
        'charset' => 'utf8mb4',
    ],
];
PHP;
file_put_contents($tempRoot . '/config/local.php', $localConfig);

try {
    $preferred = RuntimeConfig::load($tempRoot, $environment);
    assertRuntime($preferred['source'] === 'environment', 'config/local.php sobrepôs o ambiente.');

    $legacy = RuntimeConfig::load($tempRoot, []);
    assertRuntime($legacy['source'] === 'local_file', 'Fallback para config/local.php falhou.');
    assertRuntime($legacy['db']['name'] === 'legacy-database', 'Configuração legada foi alterada.');

    $unconfiguredRoot = $tempRoot . '/empty';
    mkdir($unconfiguredRoot . '/config', 0777, true);
    $unconfigured = RuntimeConfig::load($unconfiguredRoot, []);
    assertRuntime($unconfigured['configured'] === false, 'Ausência de configuração não foi reconhecida.');

    $partial = $environment;
    unset($partial['DB_PASSWORD']);
    expectSafeFailure(fn() => RuntimeConfig::load($tempRoot, $partial), 'Ambiente parcial');

    $invalidPort = $environment;
    $invalidPort['DB_PORT'] = 'invalid';
    expectSafeFailure(fn() => RuntimeConfig::load($tempRoot, $invalidPort), 'Porta inválida');

    $tables = ['users','clients','projects','client_projects','settings','activity_logs','system_updates','migrations','login_attempts'];
    assertRuntime(InstallationState::classify([], 0) === InstallationState::EMPTY, 'Banco vazio não foi reconhecido.');
    assertRuntime(InstallationState::classify(['users'], 1) === InstallationState::PARTIAL, 'Schema parcial não foi bloqueado.');
    assertRuntime(InstallationState::classify($tables, 0) === InstallationState::PARTIAL, 'Banco sem Super Administrador não foi bloqueado.');
    assertRuntime(InstallationState::classify($tables, 1) === InstallationState::INSTALLED, 'Instalação válida não foi reconhecida.');
    expectSafeFailure(fn() => InstallationState::assertInstalledOrEmpty(InstallationState::PARTIAL), 'Schema parcial');

    $credentialsError = new PDOException('driver detail');
    $credentialsError->errorInfo = ['HY000', 1045, 'driver detail'];
    assertRuntime(Database::classifyConnectionFailure($credentialsError) === DatabaseConnectionException::INVALID_CREDENTIALS, 'Credencial inválida não foi classificada.');

    $unavailableError = new PDOException('driver detail');
    $unavailableError->errorInfo = ['HY000', 2002, 'driver detail'];
    assertRuntime(Database::classifyConnectionFailure($unavailableError) === DatabaseConnectionException::UNAVAILABLE, 'Indisponibilidade não foi classificada.');

    foreach ([
        new DatabaseConnectionException(DatabaseConnectionException::INVALID_CREDENTIALS, $credentialsError),
        new DatabaseConnectionException(DatabaseConnectionException::UNAVAILABLE, $unavailableError),
    ] as $safeException) {
        foreach (array_values($environment) as $sensitive) {
            assertRuntime(!str_contains($safeException->getMessage(), (string) $sensitive), 'Mensagem de conexão revelou configuração.');
        }
    }
} finally {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($tempRoot, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    rmdir($tempRoot);
}

echo "Configuração de runtime aprovada: ambiente, legado, estados do banco e falhas seguras.\n";
