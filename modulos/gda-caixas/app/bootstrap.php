<?php
declare(strict_types=1);

require_once __DIR__ . '/autoload.php';
$config = require __DIR__ . '/../config/config.php';
\Gda\Core\Security\SessionManager::start((bool)($config['force_https'] ?? false));
date_default_timezone_set('America/Sao_Paulo');

const APP_VERSION = '1.0.0';

require_once __DIR__ . '/updater.php';

function config(?string $key = null, $default = null) {
    global $config;
    if ($key === null) return $config;
    $value = $config;
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) return $default;
        $value = $value[$part];
    }
    return $value;
}

function db(): PDO {
    return \Gda\Core\Database\Connection::fromConfig(config('db'));
}
function client_context(): \Gda\ClientArea\ClientContext {
    static $context;
    return $context ??= \Gda\ClientArea\ClientContext::fromAuthenticatedSession();
}
function company_id(): int { return client_context()->companyId(); }
function require_client_context(): void {
    if (empty($_SESSION['user'])) redirect('login');
    try { (new \Gda\Core\Http\RequireClientContextMiddleware(db(), client_context()))->handle(); }
    catch (RuntimeException $e) { http_response_code(403); exit('Acesso ao ambiente do cliente não autorizado.'); }
}
function require_client_admin(): void { if(($_SESSION['user']['role']??'')!=='client_admin'){http_response_code(403);exit('Ação exclusiva do administrador do cliente.');} }


function app_setting(string $key, ?string $default = null): ?string {
    if (!client_context()->hasCompany()) return $default;
    try {
        return settings_service()->get($key, $default);
    } catch (Throwable $e) {
        return $default;
    }
}
function settings_service(): \Gda\Modules\Caixas\Services\SettingsService {
    static $service;
    return $service ??= new \Gda\Modules\Caixas\Services\SettingsService(db(), client_context());
}
function set_app_setting(string $key, ?string $value): void {
    settings_service()->set($key, $value);
}
function gda_logo_url(): ?string {
    $path = app_setting('gda_logo_path');
    if (!$path) return null;
    $absolute = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    return is_file($absolute) ? $path : null;
}


function is_mobile_request(): bool {
    $ua = strtolower((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    return (bool)preg_match('/android|iphone|ipad|ipod|mobile|opera mini|windows phone/', $ua);
}
function local_lan_ip(): string {
    $saved = trim((string)app_setting('connect_lan_ip',''));
    if (filter_var($saved, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return $saved;
    $candidates = [];
    $host = gethostname();
    if ($host) $candidates[] = gethostbyname($host);
    $candidates[] = (string)($_SERVER['SERVER_ADDR'] ?? '');
    foreach ($candidates as $ip) {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && !str_starts_with($ip,'127.')) return $ip;
    }
    return '192.168.0.243';
}
function app_base_path(): string {
    $script = str_replace('\\','/',(string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
    $dir = rtrim(str_replace('\\','/',dirname($script)),'/');
    return $dir === '.' || $dir === '/' ? '' : $dir;
}
function gda_connect_url(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $host = (string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '');
    return ($https?'https':'http').'://'.$host.app_base_path();
}
function record_mobile_access(): void {
    if (!is_mobile_request() || !client_context()->hasCompany()) return;
    try {
        $stmt=db()->prepare('INSERT INTO mobile_access_logs(company_id,ip_address,user_agent,last_seen) VALUES(?,?,?,NOW()) ON DUPLICATE KEY UPDATE user_agent=VALUES(user_agent),last_seen=NOW()');
        $stmt->execute([company_id(),(string)($_SERVER['REMOTE_ADDR']??''),substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500)]);
    } catch(Throwable $e) {}
}
function latest_mobile_access(): ?array {
    try { $stmt=db()->prepare('SELECT * FROM mobile_access_logs WHERE company_id=? ORDER BY last_seen DESC LIMIT 1');$stmt->execute([company_id()]);$r=$stmt->fetch();return $r?:null; } catch(Throwable $e){ return null; }
}

function app_origin(): string {
    $configured = rtrim((string)config('app_url', ''), '/');
    if ($configured !== '') return $configured;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $host = (string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '');
    return ($https ? 'https' : 'http') . '://' . $host . app_base_path();
}
function absolute_url(string $page, array $params = []): string {
    return app_origin() . '/' . ltrim(url($page, $params), '/');
}
function receipt_storage_directory(): string {
    $dir = dirname(__DIR__) . '/storage/receipts';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException('Não foi possível criar a pasta de comprovantes.');
    return $dir;
}
function receipt_status_label(string $status): string {
    return ['pending'=>'Em análise','approved'=>'Aprovado','rejected'=>'Recusado','resubmit'=>'Enviar novamente'][$status] ?? $status;
}
function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function money($value): string { return 'R$ ' . number_format((float)$value, 2, ',', '.'); }
function parse_money($value): float {
    return (new \Gda\Modules\Caixas\Services\ContributionService())->parseAmount($value);
}
function url(string $page, array $params = []): string {
    $query = http_build_query(array_merge(['page' => $page], $params));
    return app_base_path() . '/index.php?' . $query;
}

function participant_access_token(): string { return (new \Gda\Modules\Caixas\Services\ParticipantService())->accessToken(); }
function clean_phone_digits(string $phone): string { return (new \Gda\Modules\Caixas\Services\ParticipantService())->phoneDigits($phone); }
function participant_access_service(): \Gda\Modules\Caixas\Services\ParticipantAccessService { return new \Gda\Modules\Caixas\Services\ParticipantAccessService(db()); }
function client_administrator_service(): \Gda\Modules\Caixas\Services\ClientAdministratorService { return new \Gda\Modules\Caixas\Services\ClientAdministratorService(db(),company_id(),(int)($_SESSION['user']['id']??0),(string)($_SESSION['user']['role']??'')); }
function participant_portal_url(string $token): string { return rtrim(app_origin(),'/').'/p/'.rawurlencode($token); }
function participant_whatsapp_url(array $participant, string $message): string {
    $digits = clean_phone_digits((string)($participant['phone'] ?? ''));
    if ($digits === '') return '';
    if (strlen($digits) <= 11) $digits = '55'.$digits;
    return 'https://wa.me/'.$digits.'?text='.rawurlencode($message);
}

function redirect(string $page, array $params = []): never {
    header('Location: ' . url($page, $params)); exit;
}
function flash(string $type, string $message): void { $_SESSION['flash'][] = compact('type', 'message'); }
function flashes(): array { $items = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $items; }
function csrf_token(): string { return (new \Gda\Core\Security\CsrfService())->token(); }
function verify_csrf(): void {
    if (!(new \Gda\Core\Security\CsrfService())->verify($_POST['csrf'] ?? null)) {
        http_response_code(419); exit('Sessão expirada. Volte e tente novamente.');
    }
}
function auth_required(): void { if (empty($_SESSION['user'])) redirect('login'); }
function current_cycle(): ?array {
    return (new \Gda\Modules\Caixas\Services\CycleService(
        new \Gda\Modules\Caixas\Repositories\CycleRepository(db(), client_context())
    ))->current();
}
function cycle_admin_pays(array $cycle): bool { return (int)($cycle['admin_pays'] ?? 0) === 1; }
function all_active_cycles(): array { $stmt=db()->prepare("SELECT * FROM cycles WHERE company_id=? AND status='active' ORDER BY created_at DESC,id DESC");$stmt->execute([company_id()]);return $stmt->fetchAll(); }
function all_company_cycles(): array { $stmt=db()->prepare("SELECT c.*,(SELECT COUNT(*) FROM cycle_participants cp WHERE cp.cycle_id=c.id) participant_count FROM cycles c WHERE c.company_id=? ORDER BY c.status='active' DESC,c.created_at DESC,c.id DESC");$stmt->execute([company_id()]);return $stmt->fetchAll(); }
function cycle_modality_label(string $modality): string { return ['DIARIO'=>'Diário','SEMANAL'=>'Semanal','QUINZENAL'=>'Quinzenal','MENSAL'=>'Mensal','PERSONALIZADO'=>'Personalizado'][$modality]??$modality; }
function cycle_is_writable(?array $cycle): bool { return $cycle && ($cycle['status']??'')==='active'; }
function cycle_management_service(): \Gda\Modules\Caixas\Services\CycleManagementService { return new \Gda\Modules\Caixas\Services\CycleManagementService(db(),new \Gda\Modules\Caixas\Repositories\CycleRepository(db(),client_context())); }

function month_name(int $month): string {
    $names = [1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'];
    return $names[$month] ?? '';
}
function days_in_cycle(array $cycle): int { return cal_days_in_month(CAL_GREGORIAN, (int)$cycle['month'], (int)$cycle['year']); }
function participant_total_paid(int $cycleId, int $participantId): float {
    $stmt = db()->prepare('SELECT COALESCE(SUM(amount),0) FROM contributions WHERE cycle_id=? AND participant_id=?');
    $stmt->execute([$cycleId, $participantId]); return (float)$stmt->fetchColumn();
}
function participant_point_count(int $cycleId, int $participantId): int {
    $stmt = db()->prepare('SELECT COUNT(*) FROM cycle_points WHERE cycle_id=? AND participant_id=?');
    $stmt->execute([$cycleId, $participantId]);
    return (int)$stmt->fetchColumn();
}
function participant_daily_due(int $cycleId, int $participantId, float $pointValue): float {
    return participant_point_count($cycleId, $participantId) * $pointValue;
}
function administration_id(int $cycleId): ?int {
    $stmt = db()->prepare('SELECT participant_id FROM cycle_points WHERE cycle_id=? AND day=1 LIMIT 1');
    $stmt->execute([$cycleId]);
    $id = $stmt->fetchColumn();
    return $id === false || $id === null ? null : (int)$id;
}

function is_administration(int $cycleId, int $participantId): bool {
    $adminId = administration_id($cycleId);
    return $adminId !== null && $adminId === $participantId;
}

function administration_for_cycle(int $cycleId, ?int $companyId = null): ?array {
    $sql = "SELECT p.* FROM cycles c JOIN cycle_points pt ON pt.cycle_id=c.id AND pt.day=1 JOIN participants p ON p.id=pt.participant_id AND p.company_id=c.company_id WHERE c.id=?";
    $args = [$cycleId];
    if ($companyId !== null) { $sql .= ' AND c.company_id=?'; $args[] = $companyId; }
    $sql .= ' LIMIT 1';
    $stmt = db()->prepare($sql); $stmt->execute($args);
    return $stmt->fetch() ?: null;
}

function assigned_point_count(int $cycleId): int {
    $stmt = db()->prepare('SELECT COUNT(*) FROM cycle_points WHERE cycle_id=? AND participant_id IS NOT NULL');
    $stmt->execute([$cycleId]);
    return (int)$stmt->fetchColumn();
}

function paying_point_count(array $cycle): int {
    $cycleId = (int)$cycle['id'];
    $total = assigned_point_count($cycleId);
    if (cycle_admin_pays($cycle)) return $total;

    $adminId = administration_id($cycleId);
    if ($adminId === null) return $total;

    $stmt = db()->prepare('SELECT COUNT(*) FROM cycle_points WHERE cycle_id=? AND participant_id=?');
    $stmt->execute([$cycleId, $adminId]);
    return max(0, $total - (int)$stmt->fetchColumn());
}

function contributions_received_on(int $cycleId, string $date): float {
    $stmt = db()->prepare('SELECT COALESCE(SUM(amount),0) FROM contributions WHERE cycle_id=? AND DATE(paid_at)=?');
    $stmt->execute([$cycleId, $date]);
    return (float)$stmt->fetchColumn();
}

function cycle_gross_payout(array $cycle): float {
    return days_in_cycle($cycle) * (float)$cycle['daily_amount'];
}
function cycle_admin_commission(float $pointValue): float {
    return max(0, $pointValue);
}
function cycle_net_payout(array $cycle): float {
    return max(0, cycle_gross_payout($cycle) - cycle_admin_commission((float)$cycle['daily_amount']));
}

function day_fill(float $totalPaid, float $dailyAmount, int $day): float {
    if ($dailyAmount <= 0) return 0;
    $remaining = $totalPaid - (($day - 1) * $dailyAmount);
    return max(0, min(1, $remaining / $dailyAmount));
}
function cycle_cash(int $cycleId): float {
    $stmt = db()->prepare('SELECT COALESCE(SUM(amount),0) FROM contributions WHERE cycle_id=?');
    $stmt->execute([$cycleId]); $in = (float)$stmt->fetchColumn();
    $stmt = db()->prepare("SELECT COALESCE(SUM(amount),0) FROM payouts WHERE cycle_id=? AND status='paid'");
    $stmt->execute([$cycleId]); $out = (float)$stmt->fetchColumn();
    return $in - $out;
}



function backup_root(): string {
    $root = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'backups';
    if (!is_dir($root) && !mkdir($root, 0775, true) && !is_dir($root)) {
        throw new RuntimeException('Não foi possível criar a pasta de backups.');
    }
    return $root;
}

function safe_backup_slug(string $name): string {
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: $name;
    $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $ascii) ?? '', '-'));
    return $slug !== '' ? $slug : 'caixa';
}

function cycle_backup_directory(array $cycle): string {
    $dir = backup_root() . DIRECTORY_SEPARATOR . 'company-' . company_id() . DIRECTORY_SEPARATOR . safe_backup_slug($cycle['name']) . '-' . (int)$cycle['id'];
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Não foi possível criar a pasta de backup do caixa.');
    }
    return $dir;
}

function build_cycle_backup(int $cycleId, string $reason='manual'): array {
    $stmt=db()->prepare('SELECT * FROM cycles WHERE id=? AND company_id=?');$stmt->execute([$cycleId,company_id()]);$cycle=$stmt->fetch();
    if(!$cycle) throw new RuntimeException('Caixa não encontrado para backup.');
    $tables=[];
    foreach(['cycle_participants','cycle_points','contributions','payouts'] as $table){
        $q=db()->prepare("SELECT * FROM `$table` WHERE cycle_id=?");$q->execute([$cycleId]);$tables[$table]=$q->fetchAll();
    }
    $participantIds=array_values(array_unique(array_map('intval',array_column($tables['cycle_participants'],'participant_id'))));
    $participants=[];
    if($participantIds){$marks=implode(',',array_fill(0,count($participantIds),'?'));$q=db()->prepare("SELECT * FROM participants WHERE company_id=? AND id IN ($marks)");$q->execute(array_merge([company_id()],$participantIds));$participants=$q->fetchAll();}
    return ['format'=>'gda-caixas-cycle-backup','version'=>'v'.APP_VERSION,'company_id'=>company_id(),'generated_at'=>date(DATE_ATOM),'reason'=>$reason,'cycle'=>$cycle,'participants'=>$participants,'tables'=>$tables];
}

function save_cycle_backup(int $cycleId, string $reason='automatico'): string {
    $data=build_cycle_backup($cycleId,$reason);$cycle=$data['cycle'];$dir=cycle_backup_directory($cycle);
    $stamp=date('Y-m-d_H-i-s');$safeReason=safe_backup_slug($reason);$file=$dir.DIRECTORY_SEPARATOR."backup-{$stamp}-{$safeReason}.json";
    $json=json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    if($json===false || file_put_contents($file,$json,LOCK_EX)===false) throw new RuntimeException('Não foi possível gravar o backup.');
    return $file;
}

function automatic_cycle_backup(?array $cycle, string $reason): void {
    if(!$cycle) return;
    try { save_cycle_backup((int)$cycle['id'],$reason); }
    catch(Throwable $e){ error_log('Falha no backup automático: '.$e->getMessage()); }
}

function list_cycle_backups(array $cycle): array {
    $dir=cycle_backup_directory($cycle);$files=glob($dir.DIRECTORY_SEPARATOR.'*.json') ?: [];
    usort($files,fn($a,$b)=>filemtime($b)<=>filemtime($a));return $files;
}

function restore_cycle_backup_file(string $file, int $expectedCycleId): void {
    if(!is_file($file)) throw new RuntimeException('Arquivo de backup não encontrado.');
    $data=json_decode((string)file_get_contents($file),true);
    if(!is_array($data)||($data['format']??'')!=='gda-caixas-cycle-backup') throw new RuntimeException('Backup inválido ou incompatível.');
    $cycle=$data['cycle']??null;if(!is_array($cycle)||(int)($cycle['id']??0)!==$expectedCycleId||(int)($data['company_id']??0)!==company_id()||(int)($cycle['company_id']??0)!==company_id()) throw new RuntimeException('Este backup pertence a outro caixa ou empresa.');
    $owned=db()->prepare('SELECT COUNT(*) FROM cycles WHERE id=? AND company_id=?');$owned->execute([$expectedCycleId,company_id()]);if(!(int)$owned->fetchColumn())throw new RuntimeException('Caixa não encontrado.');
    $pdo=db();$pdo->beginTransaction();
    try{
        foreach(($data['participants']??[]) as $row){
            if((int)($row['company_id']??0)!==company_id())throw new RuntimeException('Participante de outra empresa no backup.');
            $pdo->prepare('INSERT INTO participants(id,company_id,name,phone,email,pix_type,pix_key,notes,active,created_at) VALUES(?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),phone=VALUES(phone),email=VALUES(email),pix_type=VALUES(pix_type),pix_key=VALUES(pix_key),notes=VALUES(notes),active=VALUES(active)')->execute([$row['id'],company_id(),$row['name'],$row['phone']??null,$row['email']??null,$row['pix_type']??null,$row['pix_key']??null,$row['notes']??null,$row['active']??1,$row['created_at']??date('Y-m-d H:i:s')]);
        }
        $pdo->prepare('UPDATE cycles SET name=?,year=?,month=?,daily_amount=?,admin_pays=?,status=? WHERE id=? AND company_id=?')->execute([$cycle['name'],$cycle['year'],$cycle['month'],$cycle['daily_amount'],$cycle['admin_pays']??0,$cycle['status']??'active',$expectedCycleId,company_id()]);
        foreach(['payouts','contributions','cycle_points','cycle_participants'] as $table){$pdo->prepare("DELETE FROM `$table` WHERE cycle_id=?")->execute([$expectedCycleId]);}
        foreach(($data['tables']['cycle_participants']??[]) as $r)$pdo->prepare('INSERT INTO cycle_participants(cycle_id,participant_id,joined_at) VALUES(?,?,?)')->execute([$expectedCycleId,$r['participant_id'],$r['joined_at']??date('Y-m-d H:i:s')]);
        foreach(($data['tables']['cycle_points']??[]) as $r)$pdo->prepare('INSERT INTO cycle_points(cycle_id,day,participant_id) VALUES(?,?,?)')->execute([$expectedCycleId,$r['day'],$r['participant_id']]);
        foreach(($data['tables']['contributions']??[]) as $r)$pdo->prepare('INSERT INTO contributions(cycle_id,participant_id,amount,paid_at,notes,created_by,created_at) VALUES(?,?,?,?,?,?,?)')->execute([$expectedCycleId,$r['participant_id'],$r['amount'],$r['paid_at'],$r['notes']??null,$r['created_by']??null,$r['created_at']??date('Y-m-d H:i:s')]);
        foreach(($data['tables']['payouts']??[]) as $r)$pdo->prepare('INSERT INTO payouts(cycle_id,point_day,participant_id,amount,status,paid_at,notes,created_by,created_at) VALUES(?,?,?,?,?,?,?,?,?)')->execute([$expectedCycleId,$r['point_day'],$r['participant_id'],$r['amount'],$r['status'],$r['paid_at']??null,$r['notes']??null,$r['created_by']??null,$r['created_at']??date('Y-m-d H:i:s')]);
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function ensure_app_schema(): void {
    static $done = false;
    if ($done) return;
    $done = true;
    $pdo = db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS companies (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,gda_client_id VARCHAR(100) NULL UNIQUE,name VARCHAR(160) NOT NULL,
      identifier VARCHAR(100) NOT NULL UNIQUE,status ENUM('active','inactive') NOT NULL DEFAULT 'active',
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE IF NOT EXISTS app_settings (
      company_id INT UNSIGNED NOT NULL,
      setting_key VARCHAR(100) NOT NULL,
      setting_value TEXT NULL,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY(company_id,setting_key), INDEX idx_app_settings_company(company_id)
    ) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE IF NOT EXISTS mobile_access_logs (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      company_id INT UNSIGNED NULL,
      ip_address VARCHAR(64) NOT NULL,
      user_agent VARCHAR(500) NULL,
      last_seen DATETIME NOT NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_mobile_access_logs_company(company_id), UNIQUE KEY uniq_mobile_company_ip(company_id,ip_address)
    ) ENGINE=InnoDB");
    $cycleColumns = $pdo->query("SHOW COLUMNS FROM cycles")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('admin_pays', $cycleColumns, true)) $pdo->exec("ALTER TABLE cycles ADD COLUMN admin_pays TINYINT(1) NOT NULL DEFAULT 0 AFTER daily_amount");
    if (!in_array('modality', $cycleColumns, true)) $pdo->exec("ALTER TABLE cycles ADD COLUMN modality ENUM('DIARIO','SEMANAL','QUINZENAL','MENSAL','PERSONALIZADO') NOT NULL DEFAULT 'DIARIO' AFTER name");
    if (!in_array('start_date', $cycleColumns, true)) $pdo->exec('ALTER TABLE cycles ADD COLUMN start_date DATE NULL AFTER modality');
    if (!in_array('notes', $cycleColumns, true)) $pdo->exec('ALTER TABLE cycles ADD COLUMN notes TEXT NULL AFTER status');
    $columns = $pdo->query("SHOW COLUMNS FROM participants")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('pix_type', $columns, true)) $pdo->exec("ALTER TABLE participants ADD COLUMN pix_type VARCHAR(30) NULL AFTER email");
    if (!in_array('pix_key', $columns, true)) $pdo->exec("ALTER TABLE participants ADD COLUMN pix_key VARCHAR(255) NULL AFTER pix_type");
    if (!in_array('notes', $columns, true)) $pdo->exec("ALTER TABLE participants ADD COLUMN notes TEXT NULL AFTER pix_key");
    if (!in_array('access_token', $columns, true)) $pdo->exec("ALTER TABLE participants ADD COLUMN access_token VARCHAR(64) NULL UNIQUE AFTER notes");
    $pdo->exec("CREATE TABLE IF NOT EXISTS message_templates (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      cycle_id INT UNSIGNED NULL,
      code VARCHAR(60) NOT NULL,
      name VARCHAR(120) NOT NULL,
      body TEXT NOT NULL,
      active TINYINT(1) NOT NULL DEFAULT 1,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      UNIQUE KEY uniq_template_cycle_code(cycle_id,code),
      FOREIGN KEY(cycle_id) REFERENCES cycles(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");
    $templateColumns = $pdo->query("SHOW COLUMNS FROM message_templates")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('category', $templateColumns, true)) $pdo->exec("ALTER TABLE message_templates ADD COLUMN category VARCHAR(50) NOT NULL DEFAULT 'Aviso' AFTER name");
    if (!in_array('title', $templateColumns, true)) $pdo->exec("ALTER TABLE message_templates ADD COLUMN title VARCHAR(160) NULL AFTER category");
    $pdo->exec("CREATE TABLE IF NOT EXISTS message_queue (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      cycle_id INT UNSIGNED NOT NULL,
      participant_id INT UNSIGNED NOT NULL,
      template_code VARCHAR(60) NOT NULL,
      message_text TEXT NOT NULL,
      status ENUM('waiting','sent','cancelled','failed') NOT NULL DEFAULT 'waiting',
      scheduled_at DATETIME NULL,
      sent_at DATETIME NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY(cycle_id) REFERENCES cycles(id) ON DELETE CASCADE,
      FOREIGN KEY(participant_id) REFERENCES participants(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");
    $pdo->exec("CREATE TABLE IF NOT EXISTS payment_receipts (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      cycle_id INT UNSIGNED NOT NULL,
      participant_id INT UNSIGNED NOT NULL,
      amount DECIMAL(10,2) NOT NULL,
      paid_at DATETIME NOT NULL,
      original_name VARCHAR(255) NOT NULL,
      stored_name VARCHAR(255) NOT NULL UNIQUE,
      mime_type VARCHAR(100) NOT NULL,
      file_size INT UNSIGNED NOT NULL,
      notes VARCHAR(500) NULL,
      status ENUM('pending','approved','rejected','resubmit') NOT NULL DEFAULT 'pending',
      review_notes VARCHAR(500) NULL,
      contribution_id BIGINT UNSIGNED NULL,
      reviewed_by INT UNSIGNED NULL,
      reviewed_at DATETIME NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_receipt_cycle_status(cycle_id,status),
      INDEX idx_receipt_participant(participant_id),
      FOREIGN KEY(cycle_id) REFERENCES cycles(id) ON DELETE CASCADE,
      FOREIGN KEY(participant_id) REFERENCES participants(id) ON DELETE CASCADE,
      FOREIGN KEY(contribution_id) REFERENCES contributions(id) ON DELETE SET NULL,
      FOREIGN KEY(reviewed_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB");
    if(client_context()->hasCompany()){$rename=$pdo->prepare("UPDATE participants SET name='Administração' WHERE company_id=? AND name='Dono do Caixa'");$rename->execute([company_id()]);}
    $defaults=[
      ['daily_reminder','Lembrete diário','Olá, {nome}! Sua contribuição de hoje no caixa {caixa} é de {valor_diario}. Chave PIX: {chave_pix}. Acompanhe: {link_participante}'],
      ['payment_confirmed','Pagamento confirmado','Olá, {nome}! Seu pagamento de {valor_pago} foi registrado com sucesso no caixa {caixa}.'],
      ['payment_pending','Contribuição pendente','Olá, {nome}! Sua contribuição de hoje no caixa {caixa} ainda não foi registrada. Valor: {valor_diario}. PIX: {chave_pix}.'],
      ['payout_soon','Recebimento próximo','Olá, {nome}! Seu ponto no caixa {caixa} está previsto para {data_ponto}.']
    ];
    $st=$pdo->prepare("INSERT IGNORE INTO message_templates(cycle_id,code,name,category,body) VALUES(NULL,?,?,'Cobrança',?)");
    foreach($defaults as $d)$st->execute($d);
}

function reset_test_data(): void {
    throw new LogicException('Reset global desabilitado: testes devem usar banco descartável separado.');
}
