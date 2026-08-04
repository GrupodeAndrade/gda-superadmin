<?php
declare(strict_types=1);

require dirname(__DIR__).'/app/autoload.php';
session_save_path(sys_get_temp_dir());
session_start();

use Gda\ClientArea\ClientContext;
use Gda\Core\Auth\AuthService;
use Gda\Core\Database\MigrationRunner;
use Gda\Modules\Caixas\Repositories\ContributionRepository;
use Gda\Modules\Caixas\Repositories\CycleRepository;
use Gda\Modules\Caixas\Repositories\ParticipantRepository;
use Gda\Modules\Caixas\Services\BackupService;
use Gda\Modules\Caixas\Services\ClientAdministratorService;
use Gda\Modules\Caixas\Services\CycleManagementService;
use Gda\Modules\Caixas\Services\CycleService;
use Gda\Modules\Caixas\Services\MessageService;
use Gda\Modules\Caixas\Services\ParticipantAccessService;
use Gda\Modules\Caixas\Services\SettingsService;

$host=$argv[1]??'127.0.0.1';
$port=(int)($argv[2]??3317);
$password=$argv[3]??'';
$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false];
$server=new PDO("mysql:host={$host};port={$port};charset=utf8mb4",'root',$password,$options);
$database='gda_phase0_foundation';
$server->exec("DROP DATABASE IF EXISTS `{$database}`");
$server->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$db=new PDO("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",'root',$password,$options);

$checks=0;
$assert=static function(bool $condition,string $message)use(&$checks):void{if(!$condition)throw new RuntimeException($message);$checks++;};
$blocked=static function(callable $operation):bool{try{$operation();return false;}catch(Throwable){return true;}};

$db->exec((string)file_get_contents(dirname(__DIR__).'/database/schema.sql'));
$migrationDir=dirname(__DIR__).'/database/migrations';
$migrations=array_map('basename',glob($migrationDir.'/*.php')?:[]);sort($migrations,SORT_NATURAL);
$assert($migrations===['001_updater_infrastructure.php','002_client_roles.php','003_multi_tenant_foundation.php','004_global_administration.php','005_multi_cycle_management.php','006_participant_access_links.php'],'Conjunto de migrações diferente de 001 a 006.');
$runner=new MigrationRunner($db);
$first=$runner->runDeclared($migrations,$migrationDir);
$second=$runner->runDeclared($migrations,$migrationDir);
$assert($first===$migrations&&$second===[],'Migrações não são idempotentes.');

$required=['companies','users','cycles','participants','cycle_participants','cycle_points','contributions','payouts','message_templates','message_queue','payment_receipts','app_settings','global_audit_logs','global_settings','app_updates','schema_migrations'];
$present=$db->query('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')->fetchAll(PDO::FETCH_COLUMN);
foreach($required as$table)$assert(in_array($table,$present,true),"Tabela obrigatória ausente: {$table}");

$db->exec("INSERT INTO companies(name,identifier,status)VALUES('Empresa A','empresa-a','active'),('Empresa B','empresa-b','active')");
$hash=password_hash('Client-password-123',PASSWORD_DEFAULT);
$stmt=$db->prepare("INSERT INTO users(company_id,name,email,password,role,active)VALUES(1,'Admin A','admin-a@example.test',?,'client_admin',1),(2,'Admin B','admin-b@example.test',?,'client_admin',1)");$stmt->execute([$hash,$hash]);
$ctx=new ClientContext(1);
$cycles=new CycleRepository($db,$ctx);
$cycleService=new CycleService($cycles);
$cycleA=$cycleService->create($db,$ctx,'Caixa A',2026,8,20,false);
$cycleA2=$cycleService->create($db,$ctx,'Caixa A 2',2026,9,25,true);
$cycleB=(new CycleService(new CycleRepository($db,new ClientContext(2))))->create($db,new ClientContext(2),'Caixa B',2026,8,30,false);
$assert($cycleA>0&&$cycleA2>0&&$cycleB>0&&$cycles->findOwned($cycleB)===null,'Multi-caixa ou isolamento de empresa falhou.');

$participants=new ParticipantRepository($db,$ctx);
$adminParticipant=$participants->create(['name'=>'Administração','phone'=>null,'email'=>null,'pix_type'=>'email','pix_key'=>'pix@example.test','notes'=>null,'access_token'=>null]);
$participant=$participants->create(['name'=>'Participante A','phone'=>'11999999999','email'=>'p@example.test','pix_type'=>'cpf','pix_key'=>'12345678901','notes'=>'Teste','access_token'=>null]);
$participantB=(new ParticipantRepository($db,new ClientContext(2)))->create(['name'=>'Participante B','phone'=>null,'email'=>null,'pix_type'=>null,'pix_key'=>null,'notes'=>null,'access_token'=>null]);
$db->prepare('INSERT INTO cycle_participants(cycle_id,participant_id)VALUES(?,?),(?,?)')->execute([$cycleA,$adminParticipant,$cycleA,$participant]);
$db->prepare('INSERT INTO cycle_points(cycle_id,day,participant_id)VALUES(?,?,?),(?,?,?)')->execute([$cycleA,1,$adminParticipant,$cycleA,2,$participant]);
$assert($participants->find($participant)!==null&&$participants->find($participantB)===null,'Participantes cruzaram empresas.');
$assert((string)$db->query("SELECT pix_key FROM participants WHERE id={$participant}")->fetchColumn()==='12345678901','PIX não foi persistido.');

$db->prepare('INSERT INTO contributions(cycle_id,participant_id,amount,paid_at,notes,created_by)VALUES(?,?,20,NOW(),?,1)')->execute([$cycleA,$participant,'Aporte teste']);
$contributionId=(int)$db->lastInsertId();
$db->prepare("INSERT INTO payouts(cycle_id,point_day,participant_id,amount,status,paid_at,created_by)VALUES(?,?,?,580,'paid',NOW(),1)")->execute([$cycleA,2,$participant]);
$db->prepare("INSERT INTO message_templates(cycle_id,code,name,body)VALUES(?,'reminder','Lembrete','Olá {nome}')")->execute([$cycleA]);
$db->prepare("INSERT INTO message_queue(cycle_id,participant_id,template_code,message_text,status)VALUES(?,?,'reminder','Olá Participante A','sent')")->execute([$cycleA,$participant]);
$db->prepare("INSERT INTO payment_receipts(cycle_id,participant_id,amount,paid_at,original_name,stored_name,mime_type,file_size,status,contribution_id,reviewed_by,reviewed_at)VALUES(?,?,20,NOW(),'teste.pdf','teste-seguro.pdf','application/pdf',10,'approved',?,1,NOW())")->execute([$cycleA,$participant,$contributionId]);
$assert((new ContributionRepository($db,$ctx))->find($contributionId)!==null,'Aporte/histórico não encontrado.');
$assert((int)$db->query("SELECT COUNT(*) FROM payouts WHERE cycle_id={$cycleA} AND status='paid'")->fetchColumn()===1,'Pagamento não persistido.');
$assert((int)$db->query("SELECT COUNT(*) FROM payment_receipts WHERE cycle_id={$cycleA} AND status='approved'")->fetchColumn()===1,'Comprovante não persistido.');
$assert(count((new MessageService())->queueForCompany($db,$ctx))===1,'Fila ou histórico de mensagens inválido.');

$settings=new SettingsService($db,$ctx);$settings->set('pix_key','pix-config@example.test');$assert($settings->get('pix_key')==='pix-config@example.test','Configuração isolada não persistiu.');
$auth=new AuthService(new Gda\Modules\Caixas\Repositories\UserRepository($db));$_SESSION=[];$assert($auth->attempt('admin-a@example.test','Client-password-123')&&($_SESSION['user']['company_id']??0)===1,'Login cliente falhou.');
$admins=new ClientAdministratorService($db,1,1,'client_admin');$newAdmin=$admins->create(['name'=>'Admin adicional','email'=>'admin-extra@example.test','password'=>'Strong-password-123','password_confirmation'=>'Strong-password-123','active'=>1]);$assert($newAdmin>0&&count($admins->all())===2,'Administradores do cliente falharam.');

$access=new ParticipantAccessService($db);$token=$access->generate(1,$cycleA,$participant);$resolved=$access->resolve($token,false);$assert(($resolved['participant_id']??0)===$participant,'Portal/link do participante falhou.');$access->setEnabled(1,$cycleA,$participant,false);$assert($access->resolve($token,false)===null,'Link desativado continuou válido.');
$assert($blocked(fn()=>$access->generate(1,$cycleA,$participantB)),'Link permitiu participante de outra empresa.');

$management=new CycleManagementService($db,$cycles);$assert($management->archiveCycle($cycleA2)&&$management->reactivateCycle($cycleA2),'Arquivar ou reativar falhou.');
$empty=$cycleService->create($db,$ctx,'Caixa vazio',2026,10,10,false);$emptyAdmin=$participants->create(['name'=>'Administração','phone'=>null,'email'=>null,'pix_type'=>null,'pix_key'=>null,'notes'=>null,'access_token'=>null]);$db->prepare('INSERT INTO cycle_participants(cycle_id,participant_id)VALUES(?,?)')->execute([$empty,$emptyAdmin]);$db->prepare('INSERT INTO cycle_points(cycle_id,day,participant_id)VALUES(?,1,?)')->execute([$empty,$emptyAdmin]);$assert($management->canDeleteCycle($empty),'Caixa vazio não foi reconhecido.');$management->deleteEmptyCycle($empty);$assert($cycles->findOwned($empty)===null,'Caixa vazio não foi excluído.');
$assert(!$management->canDeleteCycle($cycleA),'Caixa com movimentação foi considerado vazio.');

(new BackupService())->assertBelongsToCompany(['company_id'=>1],$ctx);$assert(true,'Backup da própria empresa foi rejeitado.');
$assert($blocked(fn()=>(new BackupService())->assertBelongsToCompany(['company_id'=>2],$ctx)),'Backup de outra empresa foi aceito.');
$updater=(string)file_get_contents(dirname(__DIR__).'/app/updater.php');
$assert(str_contains($updater,"\$manifest['product'] !== 'gda-caixas'"),'Contrato do atualizador não está isolado em gda-caixas.');
$html=(string)file_get_contents(dirname(__DIR__).'/app/layout.php').(string)file_get_contents(dirname(__DIR__).'/app/AdminGlobal/Views/header.php');
$css=(string)file_get_contents(dirname(__DIR__).'/public/assets/style.css');
$assert(str_contains($html,'width=device-width')&&str_contains($css,'@media'),'Fundação responsiva não foi encontrada.');

echo "Phase 0 functional tests: {$checks} checks passed on MariaDB ".$server->query('SELECT VERSION()')->fetchColumn().PHP_EOL;
