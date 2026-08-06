<?php
declare(strict_types=1);

use App\Services\Csrf;

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_path(string $path = ''): string
{
    return dirname(__DIR__, 2) . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function base_url(string $path = ''): string
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $base = rtrim(dirname($script), '/.');
    if (str_ends_with($base, '/public')) {
        $base = substr($base, 0, -7);
    }
    return ($base ?: '') . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . base_url($path));
    exit;
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function old(string $key, string $default = ''): string
{
    return e($_SESSION['_old'][$key] ?? $default);
}

function method_field(string $method): string
{
    return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
}

function project_status_label(string $status): string
{
    return match ($status) {
        'active' => 'Ativo',
        'deploying' => 'Em implantação',
        default => 'Inativo',
    };
}

function dashboard_project_status_label(string $status): string { return match($status){'active','production'=>'Em produção','deploying'=>'Em implantação','development'=>'Em desenvolvimento','maintenance'=>'Em manutenção',default=>'Inativo'}; }
function dashboard_status_class(string $status): string { return in_array($status,['active','production','deploying','development','inactive','maintenance'],true)?$status:'inactive'; }
function dashboard_activity_label(string $action): string { return match($action){'login'=>'Login realizado com sucesso','login_invalid'=>'Tentativa de login recusada','logout'=>'Sessão encerrada','client_created'=>'Cliente criado','client_updated'=>'Cliente atualizado','client_status'=>'Status do cliente alterado','project_updated'=>'Projeto atualizado','project_status'=>'Status do projeto alterado','project.attached'=>'Projeto anexado','project.attachment_failed'=>'Falha ao anexar projeto','project.connection_updated'=>'Conexão do projeto atualizada','update_analyzed'=>'Atualização analisada','update_installed'=>'Atualização concluída','update_restored'=>'Atualização restaurada','update_failed'=>'Atualização não concluída','rollback_failed'=>'Restauração não concluída','settings_updated'=>'Configuração alterada','profile_updated'=>'Perfil administrativo atualizado','module_migrated'=>'Módulo atualizado',default=>'Atividade administrativa'}; }
function dashboard_excerpt(?string $text,int $limit=130): string { $text=trim((string)$text);if($text==='')return 'Sem descrição cadastrada.';return mb_strlen($text)<=$limit?$text:rtrim(mb_substr($text,0,$limit-1)).'…'; }
function project_connection_status_label(string $status): string { return match($status){'attached'=>'Anexado','verified'=>'Verificado','pending'=>'Conexão pendente','error'=>'Conexão com erro','revoked'=>'Revogado',default=>'Desconhecido'}; }
function project_capabilities_label(array $capabilities): string { $labels=['sso'=>'SSO','client_provisioning'=>'Provisionamento de clientes','remote_updates'=>'Atualizações remotas','health_check'=>'Verificação de saúde'];$enabled=[];foreach($labels as $key=>$label)if(($capabilities[$key]??false)===true)$enabled[]=$label;return $enabled?implode(', ',$enabled):'Nenhum recurso de integração ativo'; }
