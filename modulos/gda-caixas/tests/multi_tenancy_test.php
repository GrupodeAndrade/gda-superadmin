<?php
declare(strict_types=1);

require dirname(__DIR__).'/app/autoload.php';

use Gda\ClientArea\ClientContext;
use Gda\Core\Http\RequireClientContextMiddleware;
use Gda\Modules\Caixas\Repositories\ContributionRepository;
use Gda\Modules\Caixas\Repositories\CycleRepository;
use Gda\Modules\Caixas\Repositories\ParticipantRepository;
use Gda\Modules\Caixas\Services\BackupService;
use Gda\Modules\Caixas\Services\CycleService;
use Gda\Modules\Caixas\Services\MessageService;

$checks=0;
$test=static function(bool $condition,string $message)use(&$checks):void{if(!$condition)throw new RuntimeException($message);$checks++;};
$blocked=static function(callable $operation):bool{try{$operation();return false;}catch(Throwable){return true;}};

$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
try{
    $db->exec('CREATE TABLE companies(id INTEGER PRIMARY KEY,name TEXT,status TEXT);CREATE TABLE users(id INTEGER PRIMARY KEY,company_id INTEGER NULL,role TEXT,active INTEGER DEFAULT 1);CREATE TABLE cycles(id INTEGER PRIMARY KEY AUTOINCREMENT,company_id INTEGER,name TEXT,year INTEGER,month INTEGER,daily_amount REAL,admin_pays INTEGER,status TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP);CREATE TABLE participants(id INTEGER PRIMARY KEY AUTOINCREMENT,company_id INTEGER,name TEXT,phone TEXT,email TEXT,pix_type TEXT,pix_key TEXT,notes TEXT,access_token TEXT,active INTEGER DEFAULT 1);CREATE TABLE contributions(id INTEGER PRIMARY KEY,cycle_id INTEGER,participant_id INTEGER,amount REAL);CREATE TABLE message_queue(id INTEGER PRIMARY KEY,cycle_id INTEGER,participant_id INTEGER,message_text TEXT);CREATE TABLE app_settings(company_id INTEGER,setting_key TEXT,setting_value TEXT,PRIMARY KEY(company_id,setting_key));');
    $db->exec("INSERT INTO companies VALUES(1,'Cliente A','active'),(2,'Cliente B','active'),(3,'Cliente inativo','inactive');INSERT INTO users VALUES(10,1,'client_admin',1),(20,2,'client_admin',1),(30,NULL,'client_admin',1),(40,3,'client_admin',1),(50,NULL,'global_admin',1);INSERT INTO cycles(company_id,name,year,month,daily_amount,admin_pays,status) VALUES(1,'Caixa A',2026,7,20,0,'active'),(2,'Caixa B',2026,7,20,0,'active');INSERT INTO participants(company_id,name,access_token) VALUES(1,'Participante A','a'),(2,'Participante B','b');INSERT INTO contributions VALUES(100,1,1,20),(200,2,2,30);INSERT INTO message_queue VALUES(1,1,1,'Mensagem A'),(2,2,2,'Mensagem B');INSERT INTO app_settings VALUES(1,'pix','A'),(2,'pix','B');");

    $_SESSION=['auth_scope'=>'client','user'=>['id'=>10,'role'=>'client_admin','company_id'=>1]];
    $_GET['company_id']=2;$_POST['company_id']=2;
    $context=ClientContext::fromAuthenticatedSession();
    $test($context->companyId()===1,'GET/POST alteraram o contexto.');
    (new RequireClientContextMiddleware($db,$context))->handle();$test(true,'Cliente ativo deveria ser aceito.');
    $cycles=new CycleRepository($db,$context);
    $test($cycles->active(1)['name']==='Caixa A','Cliente A não encontrou seu ciclo.');
    $test($cycles->active(2)['name']==='Caixa A','ID de ciclo externo não foi ignorado com fallback seguro.');
    $participants=new ParticipantRepository($db,$context);
    $test($participants->find(1)['name']==='Participante A','Participante A ausente.');
    $test($participants->find(2)===null,'Cliente A visualizou participante B.');
    $contributions=new ContributionRepository($db,$context);
    $test($contributions->find(200)===null,'Cliente A visualizou contribuição B.');
    $test(!$contributions->delete(200),'Cliente A alterou contribuição B.');
    $test($db->query('SELECT COUNT(*) FROM contributions WHERE id=200')->fetchColumn()==1,'Contribuição B foi apagada.');
    $newCycle=(new CycleService($cycles))->create($db,$context,'Novo A',2026,8,25,false);
    $test((int)$db->query("SELECT company_id FROM cycles WHERE id={$newCycle}")->fetchColumn()===1,'Novo ciclo não usou empresa da sessão.');
    $newParticipant=$participants->create(['name'=>'Novo participante','phone'=>null,'email'=>null,'pix_type'=>null,'pix_key'=>null,'notes'=>null,'access_token'=>'new']);
    $test((int)$db->query("SELECT company_id FROM participants WHERE id={$newParticipant}")->fetchColumn()===1,'Novo participante não usou empresa da sessão.');
    $test(count((new MessageService())->queueForCompany($db,$context))===1,'Mensagens cruzaram empresas.');
    $stmt=$db->prepare('SELECT setting_value FROM app_settings WHERE company_id=? AND setting_key=?');$stmt->execute([$context->companyId(),'pix']);
    $test($stmt->fetchColumn()==='A','Configuração cruzou empresas.');
    (new BackupService())->assertBelongsToCompany(['company_id'=>1],$context);$test(true,'Backup próprio rejeitado.');
    $test($blocked(fn()=>(new BackupService())->assertBelongsToCompany(['company_id'=>2],$context)),'Backup externo aceito.');
    $_SESSION=['auth_scope'=>'client','user'=>['id'=>30,'role'=>'client_admin','company_id'=>null]];$test($blocked(fn()=>(new RequireClientContextMiddleware($db,ClientContext::fromAuthenticatedSession()))->handle()),'Usuário sem empresa aceito.');
    $_SESSION=['auth_scope'=>'client','user'=>['id'=>40,'role'=>'client_admin','company_id'=>3]];$test($blocked(fn()=>(new RequireClientContextMiddleware($db,ClientContext::fromAuthenticatedSession()))->handle()),'Empresa inativa aceita.');
    $_SESSION=['auth_scope'=>'client','user'=>['id'=>50,'role'=>'global_admin','company_id'=>null]];$test($blocked(fn()=>(new RequireClientContextMiddleware($db,ClientContext::fromAuthenticatedSession()))->handle()),'Global admin entrou na área operacional.');

    $migration=file_get_contents(dirname(__DIR__).'/database/migrations/003_multi_tenant_foundation.php');
    $test(substr_count($migration,'DROP TABLE')===0&&substr_count($migration,'TRUNCATE')===0,'Migração contém operação destrutiva.');
    $test(str_contains($migration,'legacy-default')&&str_contains($migration,'INSERT IGNORE INTO tenant_migration_log'),'Migração não caracteriza preservação idempotente do legado.');
    $source=file_get_contents(dirname(__DIR__).'/app/pages.php');
    $test(!preg_match("/DELETE FROM contributions WHERE id=\?['\"]\)->execute\(\[\$id\]\)/",$source),'DELETE de contribuição sem ciclo.');

    echo "Multi-tenant isolation tests: {$checks} checks passed\n";
}finally{unset($db);}
