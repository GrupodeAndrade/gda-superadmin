<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/autoload.php';
require dirname(__DIR__) . '/app/Helpers/helpers.php';

use App\Services\Database;
use App\Services\ModuleMigrationService;
$host = getenv('GDA_TEST_DB_HOST') ?: '127.0.0.1';
$port = (int) (getenv('GDA_TEST_DB_PORT') ?: 3407);
$user = getenv('GDA_TEST_DB_USER') ?: 'root';
$root = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, getenv('GDA_TEST_DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$schema = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
$runPrefix = 'gda_caixas_test_' . bin2hex(random_bytes(5)) . '_';
$databases = array_map(fn(string $suffix): string => $runPrefix . $suffix, ['legacy','empty','duplicate','partial','failure','installer']);
$results = [];

function useDatabase(PDO $root, string $name, string $schema): PDO
{
    global $runPrefix, $host, $port, $user;
    $name = $runPrefix . preg_replace('/^gda_/', '', $name);
    if (!preg_match('/^gda_caixas_test_[a-f0-9]{10}_[a-z]+$/', $name)) throw new RuntimeException('Nome descartável inválido.');
    $root->exec("DROP DATABASE IF EXISTS `{$name}`");
    $root->exec("CREATE DATABASE `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo = new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, getenv('GDA_TEST_DB_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->exec($schema);
    $property = new ReflectionProperty(Database::class, 'connection');
    $property->setValue(null, $pdo);
    return $pdo;
}

function addClient(PDO $db, string $identifier): int
{
    $stmt = $db->prepare("INSERT INTO clients(identifier,name,administrator_name,email,status) VALUES(?,?,?,?, 'active')");
    $stmt->execute([$identifier, ucfirst($identifier), 'Admin', $identifier . '@test.local']);
    return (int) $db->lastInsertId();
}

function addProject(PDO $db, string $name, string $code, string $url, string $version='0.1.0'): int
{
    $stmt=$db->prepare("INSERT INTO projects(name,code,description,version,url,status) VALUES(?,?,?, ?,?,'active')");
    $stmt->execute([$name,$code,'Legado',$version,$url]);
    return (int)$db->lastInsertId();
}

function linkProject(PDO $db,int $client,int $project,string $status='active'): void
{
    $db->prepare("INSERT INTO client_projects(client_id,project_id,status,activated_at,settings) VALUES(?,?,?,NOW(),JSON_OBJECT('origem','teste'))")->execute([$client,$project,$status]);
}

function assertTrue(bool $condition,string $message): void { if(!$condition) throw new RuntimeException($message); }
function caixas(PDO $db): array { $rows=$db->query("SELECT * FROM projects WHERE code='gda-caixas'")->fetchAll();assertTrue(count($rows)===1,'Deve existir exatamente um gda-caixas.');return $rows[0]; }

try {
    // A/B: legado e reexecução.
    $db=useDatabase($root,'gda_legacy',$schema);$c1=addClient($db,'cliente-a');$c2=addClient($db,'cliente-b');$legacy=addProject($db,'Caixa Diário','caixa-diario','https://caixadiario.gda.app.br');linkProject($db,$c1,$legacy);linkProject($db,$c2,$legacy);
    $beforeLinks=(int)$db->query('SELECT COUNT(*) FROM client_projects')->fetchColumn();ModuleMigrationService::run();$p=caixas($db);$afterLinks=(int)$db->query('SELECT COUNT(*) FROM client_projects')->fetchColumn();assertTrue((int)$p['id']===$legacy,'ID legado não preservado.');assertTrue($afterLinks===$beforeLinks,'Vínculos do legado mudaram.');assertTrue($p['version']==='1.0.0'&&$p['admin_url']==='https://caixas.gda.app.br/admin-gda'&&$p['status']==='deploying','Cadastro final incorreto.');
    ModuleMigrationService::run();assertTrue((int)$db->query("SELECT COUNT(*) FROM projects WHERE code='gda-caixas'")->fetchColumn()===1,'Reexecução duplicou projeto.');assertTrue((int)$db->query('SELECT COUNT(*) FROM client_projects')->fetchColumn()===$afterLinks,'Reexecução alterou vínculos.');$results['legacy_reexecution']=['id_before'=>$legacy,'id_after'=>(int)$p['id'],'links_before'=>$beforeLinks,'links_after'=>$afterLinks];

    // C: base vazia.
    $db=useDatabase($root,'gda_empty',$schema);ModuleMigrationService::run();$p=caixas($db);ModuleMigrationService::run();assertTrue((int)$db->query('SELECT COUNT(*) FROM projects')->fetchColumn()===1,'Base vazia criou duplicidade.');$results['empty']=['projects'=>1,'id'=>(int)$p['id'],'links'=>0];

    // D: duplicidade com vínculos sobrepostos e separados.
    $db=useDatabase($root,'gda_duplicate',$schema);$c1=addClient($db,'dup-a');$c2=addClient($db,'dup-b');$c3=addClient($db,'dup-c');$legacy=addProject($db,'Caixa Diário','caixa-diario','https://old.local');$duplicate=addProject($db,'Caixas','gda-caixas','https://caixas.gda.app.br','1.0.0');linkProject($db,$c1,$legacy);linkProject($db,$c2,$legacy,'inactive');linkProject($db,$c2,$duplicate,'active');linkProject($db,$c3,$duplicate);$beforeLinks=(int)$db->query('SELECT COUNT(*) FROM client_projects')->fetchColumn();ModuleMigrationService::run();$p=caixas($db);$afterLinks=(int)$db->query('SELECT COUNT(*) FROM client_projects')->fetchColumn();assertTrue((int)$p['id']===$legacy,'Duplicidade não preservou ID legado.');assertTrue($afterLinks===3,'Consolidação não produziu um vínculo por cliente.');assertTrue((int)$db->query("SELECT COUNT(*) FROM client_projects WHERE project_id={$legacy}")->fetchColumn()===3,'Vínculos não convergiram ao legado.');assertTrue((int)$db->query("SELECT COUNT(*) FROM projects WHERE id={$duplicate}")->fetchColumn()===0,'Duplicado não removido.');$results['duplicate']=['legacy_id'=>$legacy,'duplicate_id'=>$duplicate,'links_before'=>$beforeLinks,'links_after'=>$afterLinks,'unique_clients'=>3];

    // E: parcial.
    $db=useDatabase($root,'gda_partial',$schema);$c=addClient($db,'partial');$legacy=addProject($db,'Caixas','caixa-diario','https://caixadiario.gda.app.br','0.1.0');linkProject($db,$c,$legacy);ModuleMigrationService::run();$p=caixas($db);assertTrue((int)$p['id']===$legacy&&$p['url']==='https://caixas.gda.app.br'&&$p['version']==='1.0.0','Estado parcial não convergiu.');ModuleMigrationService::run();$results['partial']=['id_before'=>$legacy,'id_after'=>(int)$p['id'],'links'=>(int)$db->query('SELECT COUNT(*) FROM client_projects')->fetchColumn()];

    // F: falha após merge, antes das exclusões; tudo deve voltar.
    $db=useDatabase($root,'gda_failure',$schema);$c1=addClient($db,'fail-a');$c2=addClient($db,'fail-b');$legacy=addProject($db,'Caixa Diário','caixa-diario','https://old.local');$duplicate=addProject($db,'Caixas','gda-caixas','https://caixas.gda.app.br');linkProject($db,$c1,$legacy);linkProject($db,$c2,$duplicate);putenv('GDA_TEST_MIGRATION_FAIL_AFTER_MERGE=1');$failed=false;try{ModuleMigrationService::run();}catch(Throwable $e){$failed=true;assertTrue($e->getMessage()==='Falha controlada na consolidação.','Mensagem inesperada.');}finally{putenv('GDA_TEST_MIGRATION_FAIL_AFTER_MERGE');}assertTrue($failed,'Falha controlada não ocorreu.');assertTrue((int)$db->query('SELECT COUNT(*) FROM projects')->fetchColumn()===2,'Rollback removeu projeto.');assertTrue((int)$db->query('SELECT COUNT(*) FROM client_projects')->fetchColumn()===2,'Rollback alterou vínculos.');assertTrue((int)$db->query("SELECT COUNT(*) FROM projects WHERE id={$duplicate} AND code='gda-caixas'")->fetchColumn()===1,'Rollback não restaurou slug duplicado.');ModuleMigrationService::run();$p=caixas($db);assertTrue((int)$p['id']===$legacy,'Reexecução após falha não convergiu.');$results['controlled_failure']=['rolled_back'=>true,'projects_after_failure'=>2,'links_after_failure'=>2,'retry_id'=>(int)$p['id']];

    // Instalação nova: schema + seed equivalente ao instalador.
    $db=useDatabase($root,'gda_installer',$schema);$db->prepare("INSERT INTO projects(name,full_name,code,description,icon,version,url,admin_url,category,status,display_order) VALUES(?,?,?,?,?,?,?,?,?,?,?)")->execute(['Caixas','GDA Caixas','gda-caixas','Gerenciamento de caixas diários, semanais, quinzenais, mensais e personalizados.','wallet','1.0.0','https://caixas.gda.app.br','https://caixas.gda.app.br/admin-gda','Gestão financeira','deploying',1]);ModuleMigrationService::run();$p=caixas($db);assertTrue((int)$db->query('SELECT COUNT(*) FROM projects')->fetchColumn()===1,'Instalador criou módulo indevido.');$results['installer']=['projects'=>1,'code'=>$p['code'],'version'=>$p['version'],'status'=>$p['status']];

    echo json_encode(['mariadb_version'=>$root->query('SELECT VERSION()')->fetchColumn(),'scenarios'=>$results],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),"\n";
} finally {
    putenv('GDA_TEST_MIGRATION_FAIL_AFTER_MERGE');
    foreach($databases as $name) $root->exec("DROP DATABASE IF EXISTS `{$name}`");
}
