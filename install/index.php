<?php
declare(strict_types=1);

use App\Services\Csrf;

$errors = [];
$platformName = 'GDA Plataforma';
$appUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\');
$values = [
    'db_host' => $_POST['db_host'] ?? 'localhost',
    'db_port' => $_POST['db_port'] ?? '3306',
    'db_name' => $_POST['db_name'] ?? '',
    'db_user' => $_POST['db_user'] ?? '',
    'admin_name' => $_POST['admin_name'] ?? '',
    'admin_email' => $_POST['admin_email'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['_token'] ?? null)) $errors[] = 'A sessão expirou. Recarregue a página.';
    foreach (['db_host','db_port','db_name','db_user','admin_name','admin_email','admin_password','admin_password_confirmation'] as $field) {
        if (trim((string) ($_POST[$field] ?? '')) === '') $errors[] = 'Preencha todos os campos obrigatórios.';
    }
    if (!filter_var($_POST['admin_email'] ?? '', FILTER_VALIDATE_EMAIL)) $errors[] = 'Informe um e-mail válido.';
    if (!filter_var($appUrl, FILTER_VALIDATE_URL)) $errors[] = 'Não foi possível determinar uma URL válida para a aplicação.';
    if (strlen((string) ($_POST['admin_password'] ?? '')) < 10) $errors[] = 'A senha deve ter pelo menos 10 caracteres.';
    if (($_POST['admin_password'] ?? '') !== ($_POST['admin_password_confirmation'] ?? '')) $errors[] = 'A confirmação da senha não confere.';
    $errors = array_values(array_unique($errors));

    if (!$errors) {
        $tmp = base_path('config/local.php.tmp');
        $localFile = base_path('config/local.php');
        $localCreated = false;
        try {
            if (is_file($localFile)) throw new RuntimeException('config/local.php já existe. O instalador não pode sobrescrevê-lo.');
            if (!is_writable(base_path('config'))) throw new RuntimeException('A pasta config não possui permissão de escrita.');
            $probe = base_path('config/.gda-install-' . bin2hex(random_bytes(6)) . '.tmp');
            if (file_put_contents($probe, 'teste', LOCK_EX) === false || !is_file($probe) || !unlink($probe)) {
                @unlink($probe);
                throw new RuntimeException('Não foi possível criar e remover um arquivo de teste em config/.');
            }
            $dbName = preg_replace('/[^a-zA-Z0-9_$-]/', '', (string) $_POST['db_name']);
            if ($dbName !== $_POST['db_name']) throw new RuntimeException('O nome do banco contém caracteres inválidos.');
            $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $_POST['db_host'], (int) $_POST['db_port'], $dbName);
            $pdo = new PDO($dsn, $_POST['db_user'], $_POST['db_password'] ?? '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $existing = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=? AND table_name IN ('users','clients','projects','settings')");
            $existing->execute([$dbName]);
            if ((int) $existing->fetchColumn() > 0) {
                throw new RuntimeException('Foi detectada uma instalação parcial. Use um banco vazio ou remova com segurança as tabelas incompletas antes de tentar novamente.');
            }
            $schema = file_get_contents(base_path('database/schema.sql'));
            $pdo->exec($schema);
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('INSERT INTO users (name,email,password_hash,role,status) VALUES (?,?,?,"super_admin","active")');
            $stmt->execute([trim($_POST['admin_name']), mb_strtolower(trim($_POST['admin_email'])), password_hash($_POST['admin_password'], PASSWORD_DEFAULT)]);
            $project = $pdo->prepare('INSERT INTO projects (name,full_name,code,description,icon,version,url,admin_url,category,status,display_order) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
            $project->execute(['Caixas','GDA Caixas','gda-caixas','Gerenciamento de caixas diários, semanais, quinzenais, mensais e personalizados.','wallet','1.0.0','https://caixas.gda.app.br','https://caixas.gda.app.br/admin-gda','Gestão financeira','deploying',1]);
            $settings = [
                'platform_name' => $platformName, 'app_url' => rtrim($appUrl, '/'),
                'company_name' => 'Grupo DeAndrade', 'admin_email' => mb_strtolower(trim($_POST['admin_email'])),
                'timezone' => 'America/Bahia', 'caixas_url' => 'https://caixas.gda.app.br',
                'caixas_admin_url' => 'https://caixas.gda.app.br/admin-gda',
                'max_update_mb' => '50', 'maintenance_mode' => '0', 'maintenance_message' => 'Sistema em manutenção. Tente novamente em instantes.',
                'version' => '0.1.0',
            ];
            $settingStmt = $pdo->prepare('INSERT INTO settings (setting_key,setting_value,type) VALUES (?,?,"string")');
            foreach ($settings as $key => $value) $settingStmt->execute([$key,$value]);
            $config = "<?php\nreturn " . var_export([
                'installed' => true,
                'app_name' => $platformName,
                'app_url' => rtrim($appUrl, '/'),
                'db' => ['host' => $_POST['db_host'], 'port' => (int) $_POST['db_port'], 'name' => $dbName, 'user' => $_POST['db_user'], 'password' => $_POST['db_password'] ?? '', 'charset' => 'utf8mb4'],
            ], true) . ";\n";
            if ((PHP_SAPI === 'cli' && getenv('GDA_TEST_INSTALL_WRITE_FAILURE') === '1') || file_put_contents($tmp, $config, LOCK_EX) === false) {
                throw new RuntimeException('Não foi possível preparar config/local.php.');
            }
            if (is_file($localFile) || !rename($tmp, $localFile)) {
                throw new RuntimeException('Não foi possível gravar config/local.php. Verifique as permissões.');
            }
            $localCreated = true;
            $pdo->commit();
            flash('success', 'Instalação concluída. Entre com sua conta de Super Administrador.');
            redirect('login');
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            @unlink($tmp);
            if ($localCreated) @unlink($localFile);
            @file_put_contents(base_path('storage/logs/install.log'), '[' . date('c') . '] ' . $e->getMessage() . "\n", FILE_APPEND);
            $safeMessage = str_contains($e->getMessage(), 'instalação parcial')
                ? $e->getMessage()
                : 'Não foi possível concluir a instalação. Verifique os dados do banco e as permissões.';
            $errors[] = $safeMessage;
        }
    }
}
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Instalar — GDA Plataforma</title><link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>"></head>
<body class="auth-page"><main class="install-card"><div class="brand"><span>GDA Soluções</span><h1>GDA Plataforma</h1><p>Super Administrador</p><p>Versão 0.1.0</p></div>
<?php foreach ($errors as $error): ?><div class="alert error"><?= e($error) ?></div><?php endforeach; ?>
<form method="post" class="form-grid" data-loading><?= csrf_field() ?>
<h2 class="full">Banco de Dados</h2>
<label>Host *<input name="db_host" value="<?= e($values['db_host']) ?>" required></label>
<label>Porta *<input type="number" name="db_port" value="<?= e($values['db_port']) ?>" required></label>
<label>Nome do banco *<input name="db_name" value="<?= e($values['db_name']) ?>" required></label>
<label>Usuário *<input name="db_user" value="<?= e($values['db_user']) ?>" required></label>
<label class="full">Senha<input type="password" name="db_password" autocomplete="new-password"></label>
<h2 class="full">Super Administrador</h2>
<label>Nome *<input name="admin_name" value="<?= e($values['admin_name']) ?>" required></label>
<label>E-mail *<input type="email" name="admin_email" value="<?= e($values['admin_email']) ?>" required></label>
<label>Senha *<input type="password" name="admin_password" minlength="10" required></label>
<label>Confirmar senha *<input type="password" name="admin_password_confirmation" minlength="10" required></label>
<button class="btn primary full" type="submit">Instalar GDA Plataforma</button></form></main>
<script src="<?= e(base_url('assets/js/app.js')) ?>"></script></body></html>
