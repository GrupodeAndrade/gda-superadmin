<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/autoload.php';

use App\Controllers\BaseController;
use App\Controllers\ClientController;

function clientAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$db->exec('PRAGMA foreign_keys=ON');
$db->exec('CREATE TABLE clients (id INTEGER PRIMARY KEY AUTOINCREMENT, identifier VARCHAR(100) NOT NULL UNIQUE, name TEXT NOT NULL)');
$db->exec("CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, client_id INTEGER NULL, role TEXT NOT NULL, FOREIGN KEY(client_id) REFERENCES clients(id) ON DELETE SET NULL)");
$db->exec('CREATE TABLE projects (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL)');
$db->exec('CREATE TABLE client_projects (id INTEGER PRIMARY KEY AUTOINCREMENT, client_id INTEGER NOT NULL, project_id INTEGER NOT NULL, FOREIGN KEY(client_id) REFERENCES clients(id), FOREIGN KEY(project_id) REFERENCES projects(id))');
$db->exec('CREATE TABLE activity_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NULL, description TEXT, FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL)');
$db->exec('CREATE TABLE module_access_issuances (id INTEGER PRIMARY KEY AUTOINCREMENT, issued_by_user_id INTEGER NOT NULL, FOREIGN KEY(issued_by_user_id) REFERENCES users(id) ON DELETE RESTRICT)');

$controller = (new ReflectionClass(ClientController::class))->newInstanceWithoutConstructor();
$databaseProperty = new ReflectionProperty(BaseController::class, 'db');
$databaseProperty->setValue($controller, $db);
$generate = new ReflectionMethod(ClientController::class, 'generateIdentifier');
$delete = new ReflectionMethod(ClientController::class, 'deleteClientData');
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

$db->exec("INSERT INTO users(client_id,role) VALUES(NULL,'super_admin')");
$superAdminId = (int) $db->lastInsertId();
$db->exec("INSERT INTO projects(name) VALUES('Projeto compartilhado')");
$sharedProjectId = (int) $db->lastInsertId();

$freeIdentifier = $generate->invoke($controller);
$db->prepare('INSERT INTO clients(identifier,name) VALUES(?,?)')->execute([$freeIdentifier, 'Sem vínculos']);
$freeId = (int) $db->lastInsertId();
$db->beginTransaction();
$freeRemoved = $delete->invoke($controller, $freeId);
$db->commit();
clientAssert($freeRemoved['users'] === 0 && $freeRemoved['client_projects'] === 0, 'Exclusão vazia informou dependências inexistentes.');
clientAssert((int) $db->query("SELECT COUNT(*) FROM clients WHERE id={$freeId}")->fetchColumn() === 0, 'Cliente sem vínculos não foi excluído.');

$targetIdentifier = $generate->invoke($controller);
$db->prepare('INSERT INTO clients(identifier,name) VALUES(?,?)')->execute([$targetIdentifier, 'Cliente completo']);
$targetId = (int) $db->lastInsertId();
$db->prepare("INSERT INTO users(client_id,role) VALUES(?,'client_admin')")->execute([$targetId]);
$targetUserId = (int) $db->lastInsertId();
$db->prepare("INSERT INTO users(client_id,role) VALUES(?,'super_admin')")->execute([$targetId]);
$linkedSuperAdminId = (int) $db->lastInsertId();
$db->prepare('INSERT INTO client_projects(client_id,project_id) VALUES(?,?)')->execute([$targetId, $sharedProjectId]);
$db->prepare('INSERT INTO module_access_issuances(issued_by_user_id) VALUES(?)')->execute([$targetUserId]);
$db->prepare('INSERT INTO activity_logs(user_id,description) VALUES(?,?)')->execute([$targetUserId, 'Histórico preservado']);

$otherIdentifier = $generate->invoke($controller);
$db->prepare('INSERT INTO clients(identifier,name) VALUES(?,?)')->execute([$otherIdentifier, 'Outro cliente']);
$otherClientId = (int) $db->lastInsertId();
$db->prepare("INSERT INTO users(client_id,role) VALUES(?,'client_admin')")->execute([$otherClientId]);
$otherUserId = (int) $db->lastInsertId();
$db->prepare('INSERT INTO client_projects(client_id,project_id) VALUES(?,?)')->execute([$otherClientId, $sharedProjectId]);

$db->beginTransaction();
$removed = $delete->invoke($controller, $targetId);
$db->commit();
clientAssert($removed === ['module_access_issuances'=>1,'client_projects'=>1,'users'=>1], 'Contagem de dependências removidas incorreta.');
clientAssert((int) $db->query("SELECT COUNT(*) FROM clients WHERE id={$targetId}")->fetchColumn() === 0, 'Cliente com vínculos não foi excluído.');
clientAssert((int) $db->query("SELECT COUNT(*) FROM users WHERE id={$targetUserId}")->fetchColumn() === 0, 'Usuário do cliente não foi removido.');
clientAssert((int) $db->query("SELECT COUNT(*) FROM client_projects WHERE client_id={$targetId}")->fetchColumn() === 0, 'Vínculo do cliente não foi removido.');
clientAssert((int) $db->query("SELECT COUNT(*) FROM projects WHERE id={$sharedProjectId}")->fetchColumn() === 1, 'Projeto compartilhado foi removido.');
clientAssert((int) $db->query("SELECT COUNT(*) FROM clients WHERE id={$otherClientId}")->fetchColumn() === 1, 'Outro cliente foi removido.');
clientAssert((int) $db->query("SELECT COUNT(*) FROM users WHERE id={$otherUserId}")->fetchColumn() === 1, 'Usuário de outro cliente foi removido.');
clientAssert((int) $db->query("SELECT COUNT(*) FROM client_projects WHERE client_id={$otherClientId}")->fetchColumn() === 1, 'Vínculo de outro cliente foi removido.');
clientAssert((int) $db->query("SELECT COUNT(*) FROM users WHERE id={$superAdminId} AND role='super_admin'")->fetchColumn() === 1, 'Super Administrador foi removido.');
clientAssert((int) $db->query("SELECT COUNT(*) FROM users WHERE id={$linkedSuperAdminId} AND role='super_admin' AND client_id IS NULL")->fetchColumn() === 1, 'Super Administrador associado não foi preservado e desvinculado.');
clientAssert($db->query("SELECT user_id FROM activity_logs WHERE description='Histórico preservado'")->fetchColumn() === null, 'Auditoria histórica não foi preservada com usuário nulo.');

$rollbackIdentifier = $generate->invoke($controller);
$db->prepare('INSERT INTO clients(identifier,name) VALUES(?,?)')->execute([$rollbackIdentifier, 'Rollback']);
$rollbackId = (int) $db->lastInsertId();
$db->prepare("INSERT INTO users(client_id,role) VALUES(?,'client_admin')")->execute([$rollbackId]);
$rollbackUserId = (int) $db->lastInsertId();
$db->prepare('INSERT INTO client_projects(client_id,project_id) VALUES(?,?)')->execute([$rollbackId, $sharedProjectId]);
$db->exec("CREATE TRIGGER prevent_client_delete BEFORE DELETE ON clients WHEN OLD.id={$rollbackId} BEGIN SELECT RAISE(ABORT, 'erro controlado'); END");
$db->beginTransaction();
$failed = false;
try {
    $delete->invoke($controller, $rollbackId);
    $db->commit();
} catch (Throwable $error) {
    $failed = true;
    $db->rollBack();
}
clientAssert($failed, 'Falha controlada de exclusão não ocorreu.');
clientAssert((int) $db->query("SELECT COUNT(*) FROM clients WHERE id={$rollbackId}")->fetchColumn() === 1, 'Rollback não restaurou o cliente.');
clientAssert((int) $db->query("SELECT COUNT(*) FROM users WHERE id={$rollbackUserId}")->fetchColumn() === 1, 'Rollback não restaurou o usuário.');
clientAssert((int) $db->query("SELECT COUNT(*) FROM client_projects WHERE client_id={$rollbackId}")->fetchColumn() === 1, 'Rollback não restaurou o vínculo.');

$root = dirname(__DIR__);
$form = file_get_contents($root . '/app/Views/clientes/form.php');
$show = file_get_contents($root . '/app/Views/clientes/show.php');
$routes = file_get_contents($root . '/routes/web.php');
$controllerSource = file_get_contents($root . '/app/Controllers/ClientController.php');
clientAssert(!str_contains($form, 'name="identifier"'), 'O formulário ainda permite editar o identificador.');
clientAssert(str_contains($form, '$client[\'identifier\']') && str_contains($form, 'disabled'), 'Identificador existente não é exibido como somente leitura.');
clientAssert(str_contains($show, 'Todos os usuários, acessos e vínculos pertencentes a ele também serão removidos.'), 'Confirmação completa de exclusão ausente.');
clientAssert(str_contains($show, 'csrf_field()'), 'O formulário de exclusão não contém CSRF.');
clientAssert(str_contains($routes, '$router->post(\'/clientes/{id}/excluir\''), 'A exclusão não usa rota POST.');
clientAssert(!str_contains($routes, '$router->get(\'/clientes/{id}/excluir\''), 'Foi encontrada rota GET de exclusão.');
clientAssert(str_contains($controllerSource, 'UPDATE clients SET name=?,administrator_name=?'), 'Atualização existente do cliente não foi preservada.');

echo "Gestão de clientes aprovada: criação automática e exclusão completa, isolada e transacional.\n";
