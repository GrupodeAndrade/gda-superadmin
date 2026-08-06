<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/autoload.php';
require dirname(__DIR__) . '/app/Helpers/helpers.php';

use App\Services\DashboardService;

function dashboardAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function renderDashboard(array $data): string
{
    extract($data, EXTR_SKIP);
    ob_start();
    require dirname(__DIR__) . '/app/Views/dashboard/index.php';
    return (string) ob_get_clean();
}

$database = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$database->exec('CREATE TABLE users(id INTEGER PRIMARY KEY,name TEXT);');
$database->exec('CREATE TABLE clients(id INTEGER PRIMARY KEY AUTOINCREMENT,identifier TEXT,name TEXT,administrator_name TEXT,email TEXT,status TEXT,created_at TEXT,deleted_at TEXT);');
$database->exec('CREATE TABLE projects(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT,full_name TEXT,code TEXT,description TEXT,icon TEXT,version TEXT,status TEXT,display_order INTEGER,deleted_at TEXT);');
$database->exec('CREATE TABLE client_projects(id INTEGER PRIMARY KEY AUTOINCREMENT,client_id INTEGER,project_id INTEGER,status TEXT);');
$database->exec('CREATE TABLE activity_logs(id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER,action TEXT,description TEXT,result TEXT,created_at TEXT);');
$database->exec('CREATE TABLE system_updates(id INTEGER PRIMARY KEY AUTOINCREMENT,new_version TEXT,status TEXT,updated_at TEXT);');

$testRoot = sys_get_temp_dir() . '/gda-dashboard-' . bin2hex(random_bytes(6));
foreach (['config','storage/backups','storage/cache','storage/logs','storage/temp','storage/updates'] as $directory) mkdir($testRoot . '/' . $directory, 0777, true);
file_put_contents($testRoot . '/config/app.php', '<?php return [];');
file_put_contents($testRoot . '/config/local.php', '<?php return [];');
file_put_contents($testRoot . '/version.json', json_encode(['version' => '1.0.0']));
$_SERVER['SCRIPT_NAME'] = '/index.php';
$visualHtml = null;

try {
    $service = new DashboardService($database, $testRoot);
    $empty = $service->overview();
    dashboardAssert($empty['stats']['total_clients'] === 0, 'Dashboard vazio inventou clientes.');
    dashboardAssert($empty['stats']['total_projects'] === 0, 'Dashboard vazio inventou módulos.');
    dashboardAssert($empty['platform']['last_backup_at'] === null, 'Dashboard vazio inventou backup.');
    dashboardAssert($empty['platform']['last_update'] === null, 'Dashboard vazio inventou atualização.');
    dashboardAssert($empty['platform']['version'] === '1.0.0', 'Versão da plataforma incorreta.');
    dashboardAssert($empty['clients'] === [] && $empty['activities'] === [], 'Estados vazios incorretos.');
    $emptyHtml = renderDashboard($empty);
    dashboardAssert(str_contains($emptyHtml, 'Nenhum backup'), 'Estado vazio de backup ausente.');
    dashboardAssert(str_contains($emptyHtml, 'Nenhuma registrada'), 'Estado vazio de atualização ausente.');
    dashboardAssert(str_contains($emptyHtml, 'Nenhuma atividade registrada recentemente.'), 'Estado vazio de atividades ausente.');

    $database->exec("INSERT INTO users(id,name) VALUES(1,'Super Admin')");
    $client = $database->prepare('INSERT INTO clients(identifier,name,administrator_name,email,status,created_at,deleted_at) VALUES(?,?,?,?,?,?,NULL)');
    for ($i = 1; $i <= 6; $i++) {
        $client->execute(["cliente-$i", $i === 6 ? '<script>alert(1)</script>' : "Cliente $i", 'Admin', "cliente$i@test.local", $i % 2 ? 'active' : 'inactive', sprintf('2026-08-%02d 10:00:00', $i)]);
    }

    $project = $database->prepare('INSERT INTO projects(name,full_name,code,description,icon,version,status,display_order,deleted_at) VALUES(?,?,?,?,?,?,?,?,NULL)');
    $project->execute(['Caixas','GDA Caixas','gda-caixas','Gerenciamento de caixas de diferentes modalidades.','wallet','1.0.0','deploying',1]);
    $project->execute(['Portal','GDA Portal','gda-portal','Portal em produção.','grid','2.0.0','active',2]);
    $project->execute(['Financeiro','GDA Financeiro','gda-financeiro','Módulo financeiro.','grid','1.5.0','production',3]);
    $project->execute(['Legado','GDA Legado','gda-legado','Módulo inativo.','grid','0.9.0','inactive',4]);

    $link = $database->prepare('INSERT INTO client_projects(client_id,project_id,status) VALUES(?,?,?)');
    $link->execute([1,1,'active']);
    $link->execute([1,2,'active']);
    $link->execute([2,1,'active']);
    $link->execute([3,1,'inactive']);
    $link->execute([6,2,'active']);
    $link->execute([6,3,'active']);

    $database->exec("INSERT INTO system_updates(new_version,status,updated_at) VALUES('1.0.1','failed','2026-08-02 09:00:00'),('1.1.0','completed','2026-08-03 11:30:00')");
    $activity = $database->prepare('INSERT INTO activity_logs(user_id,action,description,result,created_at) VALUES(1,?,?,?,?)');
    for ($i = 1; $i <= 12; $i++) {
        $action = $i === 12 ? 'login' : ($i === 11 ? 'client_created' : 'project_updated');
        $activity->execute([$action, "Contexto $i", 'success', sprintf('2026-08-04 %02d:00:00', $i)]);
    }
    $backup = $testRoot . '/storage/backups/gda-backup.zip';
    file_put_contents($backup, 'backup-descartavel');
    touch($backup, strtotime('2026-08-04 12:30:00'));

    $filled = $service->overview();
    dashboardAssert($filled['stats']['total_clients'] === 6, 'Total de clientes incorreto.');
    dashboardAssert($filled['stats']['active_clients'] === 3 && $filled['stats']['inactive_clients'] === 3, 'Status de clientes incorreto.');
    dashboardAssert($filled['stats']['total_projects'] === 4, 'Total de módulos incorreto.');
    dashboardAssert($filled['stats']['production_projects'] === 2, 'Módulos em produção incorretos.');
    dashboardAssert($filled['stats']['deploying_projects'] === 1, 'Módulos em implantação incorretos.');
    dashboardAssert(count($filled['modules']) === 4 && (int) $filled['modules'][0]['client_count'] >= 0, 'Módulos em destaque incorretos.');
    $caixas = array_values(array_filter($filled['modules'], fn(array $module): bool => $module['code'] === 'gda-caixas'))[0];
    dashboardAssert((int) $caixas['client_count'] === 2, 'Clientes vinculados ao módulo incorretos.');
    dashboardAssert(count($filled['clients']) === 5 && $filled['clients'][0]['identifier'] === 'cliente-6', 'Últimos cinco clientes incorretos.');
    dashboardAssert((int) $filled['clients'][0]['project_count'] === 2, 'Módulos liberados por cliente incorretos.');
    dashboardAssert($filled['platform']['last_backup_at'] === strtotime('2026-08-04 12:30:00'), 'Último backup incorreto.');
    dashboardAssert($filled['platform']['last_update']['new_version'] === '1.1.0', 'Última atualização concluída incorreta.');
    dashboardAssert(count($filled['activities']) === 10, 'Limite de atividades recentes incorreto.');
    dashboardAssert($filled['systemChecks'][0]['text'] === 'Operacional', 'Situação do banco incorreta.');
    dashboardAssert(DashboardService::pathState($testRoot . '/storage/cache')['text'] === 'Gravável', 'Diretório gravável não reconhecido.');
    dashboardAssert(DashboardService::pathState($testRoot . '/ausente')['text'] === 'Indisponível', 'Diretório indisponível não reconhecido.');

    dashboardAssert(dashboard_project_status_label('active') === 'Em produção', 'Status active não traduzido.');
    dashboardAssert(dashboard_project_status_label('deploying') === 'Em implantação', 'Status deploying não traduzido.');
    dashboardAssert(dashboard_project_status_label('development') === 'Em desenvolvimento', 'Status development não traduzido.');
    dashboardAssert(dashboard_project_status_label('maintenance') === 'Em manutenção', 'Status maintenance não traduzido.');
    dashboardAssert(dashboard_project_status_label('inactive') === 'Inativo', 'Status inactive não traduzido.');
    dashboardAssert(dashboard_activity_label('login') === 'Login realizado com sucesso', 'Ação de login não traduzida.');

    $filled['modules'][0]['description'] = '<script>alert(1)</script>';
    $html = renderDashboard($filled);
    dashboardAssert(!str_contains($html, '<script>alert(1)</script>') && str_contains($html, '&lt;script&gt;alert(1)&lt;/script&gt;'), 'Conteúdo do Dashboard não foi escapado.');
    foreach (['clientes','clientes?status=active','projetos','projetos/1','atualizacoes','logs'] as $linkPath) {
        dashboardAssert(str_contains($html, $linkPath), "Link obrigatório ausente: $linkPath");
    }
    foreach (['Clientes','Clientes ativos','Projetos','Módulos em produção','Atualizações','Último backup'] as $summaryLabel) {
        dashboardAssert(str_contains($html, '>' . $summaryLabel . '<'), "Indicador obrigatório ausente: $summaryLabel");
    }
    dashboardAssert(substr_count($html, 'dashboard-summary-card') === 6, 'O resumo deve conter exatamente seis cartões.');
    dashboardAssert(substr_count($html, 'dashboard-summary-icon') === 6, 'Cada indicador deve conter um ícone.');
    dashboardAssert(substr_count($html, 'dashboard-summary-value') === 6, 'Cada indicador deve conter um valor independente.');
    dashboardAssert(substr_count($html, 'dashboard-summary-action') === 6, 'Cada indicador deve conter uma ação independente.');
    dashboardAssert(substr_count($html, '<svg viewBox="0 0 24 24"') === 6, 'Os indicadores devem usar seis SVGs seguros.');
    dashboardAssert((bool) preg_match('/<section class="dashboard-summary-grid"[^>]*>(.*?)<\/section>/s', $html, $summaryGrid), 'Wrapper do resumo inválido.');
    dashboardAssert(substr_count($summaryGrid[1], '<a class="dashboard-summary-card"') === 6, 'Os seis cartões devem estar dentro da grade do resumo.');
    dashboardAssert(str_contains($html, 'dashboard-modules-grid'), 'Grade de módulos ausente.');
    dashboardAssert(str_contains($html, 'dashboard-main-grid'), 'Composição de módulos e saúde ausente.');
    dashboardAssert(str_contains($html, 'dashboard-activity-list'), 'Lista de atividades ausente.');
    dashboardAssert(str_contains($html, 'Olá, Super Administrador!'), 'Saudação operacional ausente.');
    foreach (['Banco de dados','Armazenamento','Logs do sistema','Backup','Atualizações'] as $healthLabel) {
        dashboardAssert(str_contains($html, '>' . $healthLabel . '<'), "Verificação de sistema ausente: $healthLabel");
    }

    $css = file_get_contents(dirname(__DIR__) . '/public/assets/css/app.css');
    dashboardAssert(str_contains($css, '@media(max-width:1200px)') && str_contains($css, '@media(max-width:850px)') && str_contains($css, '@media(max-width:600px)'), 'Breakpoints responsivos ausentes.');
    dashboardAssert(str_contains($css, '.dashboard-page{width:100%;min-width:0;overflow:hidden}'), 'Proteção contra rolagem horizontal ausente.');
    dashboardAssert(str_contains($css, '.dashboard-summary-grid{display:grid;grid-template-columns:repeat(3,minmax(220px,1fr))'), 'Grade principal do resumo incorreta.');
    dashboardAssert(str_contains($css, '.dashboard-modules-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr))'), 'Grade de módulos deve ter no máximo duas colunas.');
    dashboardAssert(str_contains($css, '.dashboard-main-grid{display:grid;grid-template-columns:minmax(0,2fr) minmax(270px,1fr)'), 'Proporção entre módulos e saúde incorreta.');
    dashboardAssert(str_contains($css, ':focus-visible'), 'Foco visível dos atalhos ausente.');
    $layout = file_get_contents(dirname(__DIR__) . '/app/Views/layouts/admin.php');
    dashboardAssert(str_contains($layout, "==='Dashboard'") && str_contains($layout, "hash_file('sha256',\$cssPath)"), 'Versionamento condicional do CSS do Dashboard ausente.');

    if (PHP_SAPI === 'cli-server' && isset($_GET['visual'])) {
        $visualHtml = '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dashboard visual</title><link rel="stylesheet" href="/public/assets/css/app.css?v=' . substr(hash_file('sha256', dirname(__DIR__) . '/public/assets/css/app.css'), 0, 12) . '"></head><body>'
            . '<aside class="sidebar" id="sidebar"><a class="logo" href="#"><b>GDA</b><span>Plataforma<small>Super Administrador</small></span></a><nav>'
            . '<a class="active" href="#"><i>⌂</i>Dashboard</a><a href="#"><i>◉</i>Clientes GDA</a><a href="#"><i>◆</i>Projetos GDA</a><a href="#"><i>↻</i>Atualizações</a><a href="#"><i>≡</i>Logs</a><a href="#"><i>⚙</i>Configurações</a>'
            . '</nav><form><button class="nav-logout" type="button">⇥ Sair</button></form><footer>GDA Plataforma v1.0.0</footer></aside>'
            . '<div class="app"><header class="topbar"><button class="menu-toggle" type="button" aria-label="Abrir menu">☰</button><div><strong>Dashboard</strong><small>Grupo DeAndrade</small></div><a class="user-chip" href="#"><span>S</span>Super Admin</a></header>'
            . '<main class="content"><div class="breadcrumb"><a href="#">Início</a> / Dashboard</div>' . $html . '</main></div>'
            . '<script>document.querySelector(".menu-toggle").addEventListener("click",function(){document.querySelector("#sidebar").classList.toggle("open")});</script></body></html>';
    } else {
        echo "Dashboard aprovado: estados vazio e preenchido, hierarquia visual e regras responsivas.\n";
    }
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($testRoot, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    rmdir($testRoot);
}
if ($visualHtml !== null) echo $visualHtml;
