<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/autoload.php';

use App\Controllers\BaseController;
use App\Controllers\ClientController;

function clientAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE clients (id INTEGER PRIMARY KEY AUTOINCREMENT, identifier VARCHAR(100) NOT NULL UNIQUE, name TEXT NOT NULL)');
$db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, client_id INTEGER NULL)');
$db->exec('CREATE TABLE client_projects (id INTEGER PRIMARY KEY AUTOINCREMENT, client_id INTEGER NOT NULL, project_id INTEGER NOT NULL)');

$controller = (new ReflectionClass(ClientController::class))->newInstanceWithoutConstructor();
$databaseProperty = new ReflectionProperty(BaseController::class, 'db');
$databaseProperty->setValue($controller, $db);
$generate = new ReflectionMethod(ClientController::class, 'generateIdentifier');
$delete = new ReflectionMethod(ClientController::class, 'deleteClientIfUnreferenced');
$validate = new ReflectionMethod(ClientController::class, 'validate');

$_POST = [
    'name' => 'Cliente sem identificador informado',
    'identifier' => 'valor-manual-que-deve-ser-ignorado',
    'administrator_name' => 'Administradora',
    'email' => 'admin@example.test',
    'status' => 'active',
    'password' => 'senha-segura',
    'password_confirmation' => 'senha-segura',
];
$validated = $validate->invoke($controller, true, null);
clientAssert($validated['errors'] === [], 'Criação sem identificador não passou pela validação.');
clientAssert(!array_key_exists('identifier', $validated), 'Entrada manual de identificador ainda é aceita pelo backend.');

$first = $generate->invoke($controller);
$db->prepare('INSERT INTO clients(identifier,name) VALUES(?,?)')->execute([$first, 'Cliente original']);
$firstId = (int) $db->lastInsertId();
$second = $generate->invoke($controller);
clientAssert($first !== $second, 'Dois clientes receberam o mesmo identificador.');
clientAssert((bool) preg_match('/^cliente-[a-f0-9]{32}$/', $first), 'Formato do identificador incompatível.');
clientAssert(strlen($first) <= 100, 'Identificador excede a coluna existente.');

$db->prepare('UPDATE clients SET name=? WHERE id=?')->execute(['Cliente renomeado', $firstId]);
clientAssert($db->query("SELECT identifier FROM clients WHERE id={$firstId}")->fetchColumn() === $first, 'A edição do nome alterou o identificador.');

$db->prepare('INSERT INTO clients(identifier,name) VALUES(?,?)')->execute([$second, 'Sem vínculos']);
$freeId = (int) $db->lastInsertId();
$db->beginTransaction();
clientAssert($delete->invoke($controller, $freeId) === true, 'Cliente sem dependências não foi excluído.');
$db->commit();
clientAssert((int) $db->query("SELECT COUNT(*) FROM clients WHERE id={$freeId}")->fetchColumn() === 0, 'Exclusão não removeu o cliente.');

$projectIdentifier = $generate->invoke($controller);
$db->prepare('INSERT INTO clients(identifier,name) VALUES(?,?)')->execute([$projectIdentifier, 'Com projeto']);
$projectClientId = (int) $db->lastInsertId();
$db->prepare('INSERT INTO client_projects(client_id,project_id) VALUES(?,1)')->execute([$projectClientId]);
$db->beginTransaction();
clientAssert($delete->invoke($controller, $projectClientId) === false, 'Cliente com projeto foi excluído.');
$db->rollBack();
clientAssert((int) $db->query("SELECT COUNT(*) FROM clients WHERE id={$projectClientId}")->fetchColumn() === 1, 'Bloqueio causou exclusão parcial.');

$userIdentifier = $generate->invoke($controller);
$db->prepare('INSERT INTO clients(identifier,name) VALUES(?,?)')->execute([$userIdentifier, 'Com usuário']);
$userClientId = (int) $db->lastInsertId();
$db->prepare('INSERT INTO users(client_id) VALUES(?)')->execute([$userClientId]);
$db->beginTransaction();
clientAssert($delete->invoke($controller, $userClientId) === false, 'Cliente com usuário foi excluído.');
$db->rollBack();
clientAssert((int) $db->query("SELECT COUNT(*) FROM clients WHERE id={$userClientId}")->fetchColumn() === 1, 'Bloqueio por usuário causou exclusão parcial.');

$errorIdentifier = $generate->invoke($controller);
$db->prepare('INSERT INTO clients(identifier,name) VALUES(?,?)')->execute([$errorIdentifier, 'Erro controlado']);
$errorClientId = (int) $db->lastInsertId();
$db->exec("CREATE TRIGGER prevent_client_delete BEFORE DELETE ON clients WHEN OLD.id={$errorClientId} BEGIN SELECT RAISE(ABORT, 'erro controlado'); END");
$db->beginTransaction();
$failed = false;
try {
    $delete->invoke($controller, $errorClientId);
} catch (Throwable $error) {
    $failed = true;
    $db->rollBack();
}
clientAssert($failed, 'Falha controlada de exclusão não ocorreu.');
clientAssert((int) $db->query("SELECT COUNT(*) FROM clients WHERE id={$errorClientId}")->fetchColumn() === 1, 'Erro de exclusão removeu parcialmente o cliente.');
clientAssert($db->query("SELECT identifier FROM clients WHERE id={$firstId}")->fetchColumn() === $first, 'Cliente antigo foi alterado durante os testes.');

$root = dirname(__DIR__);
$form = file_get_contents($root . '/app/Views/clientes/form.php');
$show = file_get_contents($root . '/app/Views/clientes/show.php');
$routes = file_get_contents($root . '/routes/web.php');
$controllerSource = file_get_contents($root . '/app/Controllers/ClientController.php');
clientAssert(!str_contains($form, 'name="identifier"'), 'O formulário ainda permite editar o identificador.');
clientAssert(str_contains($form, '$client[\'identifier\']') && str_contains($form, 'disabled'), 'Identificador existente não é exibido como somente leitura.');
clientAssert(str_contains($show, 'Excluir cliente') && str_contains($show, 'data-confirm='), 'A confirmação de exclusão não está presente.');
clientAssert(str_contains($show, 'csrf_field()'), 'O formulário de exclusão não contém CSRF.');
clientAssert(str_contains($routes, '$router->post(\'/clientes/{id}/excluir\''), 'A exclusão não usa rota POST.');
clientAssert(!str_contains($routes, '$router->get(\'/clientes/{id}/excluir\''), 'Foi encontrada rota GET de exclusão.');
clientAssert(str_contains($controllerSource, 'UPDATE clients SET name=?,administrator_name=?'), 'Atualização existente do cliente não foi preservada.');

echo "Gestão de clientes aprovada: identificadores automáticos, estabilidade e exclusão protegida.\n";
