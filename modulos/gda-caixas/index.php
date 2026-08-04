<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/layout.php';
require __DIR__ . '/app/pages.php';

(new \Gda\Core\Http\SecurityHeadersMiddleware())->handle();
if(update_is_maintenance_active()){$state=update_maintenance_state()??[];http_response_code(503);header('Retry-After: 300');header('Cache-Control: no-store');echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>Manutenção</title><body><main style="max-width:680px;margin:12vh auto;padding:24px;font-family:sans-serif"><h1>Sistema em manutenção</h1><p>Uma atualização estrutural está em andamento ou aguarda recuperação administrativa.</p><p>Tente novamente mais tarde.</p></main></body></html>';exit;}
$router = new \Gda\Core\Http\Router();
$routes = [
    'login'=>['page_login',true,['GET','POST']], 'install'=>[static fn()=>require __DIR__.'/database/install.php',true,['GET','POST']],
    'participant_portal'=>['page_participant_portal',true,['GET']], 'receipt_upload'=>['receipt_upload',true,['POST']],
    'receipt_file'=>['receipt_file',true,['GET']], 'dashboard'=>['page_dashboard',false,['GET']],
    'participants'=>['page_participants',false,['GET']], 'participant'=>['page_participant',false,['GET']],
    'participant_save'=>['participant_save',false,['POST']], 'participant_edit'=>['page_participant_edit',false,['GET']],
    'participant_update'=>['participant_update',false,['POST']], 'participant_delete'=>['participant_delete',false,['POST']],
    'contribution_undo'=>['contribution_undo',false,['POST']], 'contribution'=>['page_contribution',false,['GET']],
    'contribution_save'=>['contribution_save',false,['POST']], 'points'=>['page_points',false,['GET']],
    'point_save'=>['point_save',false,['POST']], 'payouts'=>['page_payouts',false,['GET']], 'payout_save'=>['payout_save',false,['POST']],
    'messages'=>['page_messages',false,['GET']], 'reports'=>['page_reports',false,['GET']], 'receipts'=>['page_receipts',false,['GET']],
    'receipt_review'=>['receipt_review',false,['POST']], 'receipt'=>['page_receipt',false,['GET']],
    'message_template_save'=>['message_template_save',false,['POST']], 'message_template_toggle'=>['message_template_toggle',false,['POST']],
    'message_template_delete'=>['message_template_delete',false,['POST']], 'message_queue_add'=>['message_queue_add',false,['POST']],
    'message_mark'=>['message_mark',false,['POST']], 'settings'=>['page_client_settings',false,['GET']], 'administrators'=>['page_client_administrators',false,['GET']],
    'administrator_create'=>['administrator_create',false,['POST']], 'administrator_name_update'=>['administrator_name_update',false,['POST']],
    'administrator_status'=>['administrator_status',false,['POST']], 'administrator_password_reset'=>['administrator_password_reset',false,['POST']],
    'password_update'=>['password_update',false,['POST']], 'cycle_name_update'=>['cycle_name_update',false,['POST']],
    'admin_pix_update'=>['admin_pix_update',false,['POST']],
    'participant_link_generate'=>['participant_link_generate',false,['POST']], 'participant_link_rotate'=>['participant_link_rotate',false,['POST']],
    'participant_link_disable'=>['participant_link_disable',false,['POST']], 'participant_link_enable'=>['participant_link_enable',false,['POST']],
    'cycle_switch'=>['cycle_switch',false,['POST']], 'cycle_create'=>['cycle_create',false,['POST']],
    'cycle_update'=>['cycle_update',false,['POST']], 'cycle_archive'=>['cycle_archive',false,['POST']],
    'cycle_reactivate'=>['cycle_reactivate',false,['POST']], 'cycle_delete'=>['cycle_delete_empty',false,['POST']],
    'cycle_settings_update'=>['cycle_settings_update',false,['POST']], 'cycle_period_update'=>['cycle_period_update',false,['POST']],
    'logout'=>[static function (): void {
        verify_csrf();
        (new \Gda\Core\Auth\AuthService(new \Gda\Modules\Caixas\Repositories\UserRepository(db())))->logout();
        redirect('login');
    },false,['POST']],
];
$technicalRoutes = [
    'developer_tools'=>['GET'], 'updates'=>['GET'], 'update_install'=>['POST'],
    'update_backup_download'=>['GET'], 'connect_status'=>['GET'], 'connect_ip_save'=>['POST'],
    'branding_update'=>['POST'], 'backup_create'=>['POST'], 'backup_restore'=>['POST'], 'backup_export'=>['GET'],
];
foreach ($technicalRoutes as $name => $methods) {
    $routes[$name] = [static function (): void {
        http_response_code(403);
        exit('Acesso restrito à Administração Global da GDA.');
    }, false, $methods];
}
foreach ($routes as $name => [$handler,$public,$methods]) {
    $router->add($name, is_string($handler) ? static fn() => $handler() : $handler, $public, $methods);
}

$requestPath=(string)(parse_url((string)($_SERVER['REQUEST_URI']??''),PHP_URL_PATH)??'');
if(preg_match('#/p/([A-Za-z0-9_-]{43})/comprovante/([a-f0-9]{48}\.[a-z0-9]+)$#',$requestPath,$publicReceipt)){$_GET['access_token']=$publicReceipt[1];$_GET['receipt_token']=$publicReceipt[2];$_GET['page']='receipt_file';}
elseif(preg_match('#/p/([A-Za-z0-9_-]{43})/?$#',$requestPath,$publicLink)){$_GET['access_token']=$publicLink[1];$_GET['page']='participant_portal';}
$page = (string)($_GET['page'] ?? (empty($_SESSION['user']) ? 'login' : 'dashboard'));
try {
    if ($page !== 'install') { ensure_app_schema(); }
    $router->dispatch($page, (string)($_SERVER['REQUEST_METHOD'] ?? 'GET'), static function () use ($page): void {
        if (in_array($page,['updates','update_install','update_backup_download'],true)) {
            auth_required();
            try { (new \Gda\Modules\Caixas\Services\UpdateService())->assertGlobalAdministrator(); }
            catch (RuntimeException $e) { http_response_code(403); exit('Atualizações estruturais são exclusivas da Administração Global.'); }
            return;
        }
        require_client_context();
        $writeRoutes=['participant_save','participant_update','participant_delete','contribution_undo','contribution_save','point_save','payout_save','message_template_save','message_template_toggle','message_template_delete','message_queue_add','message_mark','receipt_review'];
        if(in_array($page,$writeRoutes,true)&&!cycle_is_writable(current_cycle())){
            http_response_code(409);exit('Caixa arquivado: disponível somente para consulta.');
        }
    });
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Unknown database')) redirect('install');
    throw $e;
}
