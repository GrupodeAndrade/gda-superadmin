<?php
declare(strict_types=1);

require dirname(__DIR__).'/bootstrap/autoload.php';

use App\Services\PlatformIdentity;
use App\Services\PlatformIdentityStore;

$checks = 0;
function identityOk(bool $condition, string $message): void
{
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}

foreach (['GDA_PLATFORM_UUID','GDA_PLATFORM_INSTALLATION_ID','GDA_PLATFORM_PRIVATE_KEY','GDA_PLATFORM_PUBLIC_URL'] as $name) putenv($name);

$database = new PDO('sqlite::memory:');
$database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$database->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$database->exec("CREATE TABLE platform_identity(id INTEGER PRIMARY KEY,platform_uuid TEXT UNIQUE,installation_id TEXT UNIQUE,private_key_pem TEXT,public_key_pem TEXT,created_at TEXT,updated_at TEXT);CREATE TABLE settings(setting_key TEXT UNIQUE,setting_value TEXT);INSERT INTO settings VALUES('app_url','https://settings.gda.test')");

$first = (new PlatformIdentityStore($database))->loadOrCreate();
identityOk(preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $first['platform_uuid']) === 1, 'UUID v4 inválido.');
identityOk(preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $first['installation_id']) === 1, 'Installation ID automático inválido.');
$details = openssl_pkey_get_details(openssl_pkey_get_private($first['private_key_pem']));
identityOk(is_array($details) && ($details['bits'] ?? 0) >= 3072 && hash_equals(trim($details['key']), trim($first['public_key_pem'])), 'Par RSA automático inválido.');

$second = (new PlatformIdentityStore($database))->loadOrCreate();
identityOk($second === $first, 'A identidade foi regenerada durante a reinicialização.');
identityOk((int) $database->query('SELECT COUNT(*) FROM platform_identity')->fetchColumn() === 1, 'Mais de uma identidade foi persistida.');

$identity = PlatformIdentity::load($database, ['app_url'=>null]);
identityOk($identity->uuid() === $first['platform_uuid'] && $identity->installationId() === $first['installation_id'], 'A identidade persistida não foi reutilizada.');
identityOk($identity->publicUrl() === 'https://settings.gda.test', 'A URL normal da aplicação não foi reutilizada.');
$runtimeIdentity = PlatformIdentity::load($database, ['app_url'=>'https://runtime.gda.test']);
identityOk($runtimeIdentity->publicUrl() === 'https://runtime.gda.test', 'APP_URL não teve precedência sobre a configuração salva.');
$secret = 'segredo-de-integracao-nao-real';
identityOk($identity->decrypt($identity->encrypt($secret)) === $secret, 'A identidade automática não protege credenciais existentes.');
$signed = $identity->signGlobalAccess('f2941dd0-d5a7-41ec-9e8f-a840d13d4a98');
identityOk(substr_count($signed['token'], '.') === 2, 'A identidade automática não assina o acesso federado.');

$serialized = json_encode(['uuid'=>$identity->uuid(),'installation_id'=>$identity->installationId(),'public_key'=>$identity->publicKey()]);
identityOk(!str_contains((string) $serialized, 'PRIVATE KEY') && !method_exists($identity, 'privateKey'), 'A chave privada possui saída pública.');

echo "Identidade automática aprovada: $checks verificações.\n";
