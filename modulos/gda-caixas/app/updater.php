<?php

function update_storage_root(): string {
    $dir = dirname(__DIR__) . '/storage/updates';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Não foi possível criar a pasta de atualizações.');
    }
    return $dir;
}

function update_backup_root(): string {
    $dir = update_storage_root() . '/backups';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Não foi possível criar a pasta de backups de atualização.');
    }
    return $dir;
}

function update_lock_path(): string { return update_storage_root().'/update.lock'; }
function update_maintenance_path(): string { return update_storage_root().'/maintenance.json'; }
function update_maintenance_state(): ?array { $path=update_maintenance_path();if(!is_file($path))return null;$data=json_decode((string)file_get_contents($path),true);return is_array($data)?$data:['stage'=>'unknown']; }
function update_activate_maintenance(string $stage,string $message=''): void { $data=['active'=>true,'stage'=>$stage,'message'=>$message,'started_at'=>date(DATE_ATOM),'version'=>APP_VERSION];if(file_put_contents(update_maintenance_path(),json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),LOCK_EX)===false)throw new RuntimeException('Não foi possível ativar o modo de manutenção.'); }
function update_stage(string $stage,string $message=''): void { $state=update_maintenance_state()??[];$state['active']=true;$state['stage']=$stage;$state['message']=$message;$state['updated_at']=date(DATE_ATOM);file_put_contents(update_maintenance_path(),json_encode($state,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE),LOCK_EX); }
function update_acquire_lock(): void { $path=update_lock_path();$handle=@fopen($path,'x');if($handle===false)throw new RuntimeException('Já existe uma atualização em andamento ou aguardando recuperação.');fwrite($handle,json_encode(['created_at'=>date(DATE_ATOM),'version'=>APP_VERSION]));fclose($handle); }
function update_release_runtime(): void { foreach([update_maintenance_path(),update_lock_path()]as$path)if(is_file($path)&&!unlink($path))throw new RuntimeException('Não foi possível remover a trava de atualização.'); }
function update_is_maintenance_active(): bool { return is_file(update_maintenance_path()); }

function version_is_newer(string $version): bool {
    return version_compare(ltrim($version, 'vV'), ltrim(APP_VERSION, 'vV'), '>');
}

function update_table_ensure(): void {
    db()->exec("CREATE TABLE IF NOT EXISTS app_updates (
      id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      version_from VARCHAR(30) NOT NULL,
      version_to VARCHAR(30) NOT NULL,
      package_name VARCHAR(255) NULL,
      status ENUM('started','completed','failed','rolled_back') NOT NULL DEFAULT 'started',
      backup_file VARCHAR(255) NULL,
      details TEXT NULL,
      started_at DATETIME NOT NULL,
      finished_at DATETIME NULL,
      INDEX idx_updates_status(status),
      INDEX idx_updates_version(version_to)
    ) ENGINE=InnoDB");
    db()->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
      migration VARCHAR(190) PRIMARY KEY,
      applied_at DATETIME NOT NULL
    ) ENGINE=InnoDB");
}

function update_write_sql_value($value): string {
    if ($value === null) return 'NULL';
    if (is_bool($value)) return $value ? '1' : '0';
    return db()->quote((string)$value);
}

function create_database_sql_backup(string $destination): void {
    $pdo = db();
    $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
    $out = "-- GDA Caixas - backup automático\n";
    $out .= "-- Criado em " . date('Y-m-d H:i:s') . "\nSET FOREIGN_KEY_CHECKS=0;\n\n";
    foreach ($tables as $table) {
        $safe = str_replace('`', '``', $table);
        $create = $pdo->query("SHOW CREATE TABLE `{$safe}`")->fetch();
        $createSql = $create['Create Table'] ?? array_values($create)[1] ?? '';
        $out .= "DROP TABLE IF EXISTS `{$safe}`;\n{$createSql};\n\n";
        $stmt = $pdo->query("SELECT * FROM `{$safe}`");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns = implode(',', array_map(fn($c) => '`'.str_replace('`','``',$c).'`', array_keys($row)));
            $values = implode(',', array_map('update_write_sql_value', array_values($row)));
            $out .= "INSERT INTO `{$safe}` ({$columns}) VALUES ({$values});\n";
            if (strlen($out) > 1024 * 1024) {
                file_put_contents($destination, $out, FILE_APPEND | LOCK_EX);
                $out = '';
            }
        }
        $out .= "\n";
    }
    $out .= "SET FOREIGN_KEY_CHECKS=1;\n";
    file_put_contents($destination, $out, FILE_APPEND | LOCK_EX);
}

function update_should_exclude(string $relative): bool {
    $relative = str_replace('\\', '/', ltrim($relative, '/'));
    $excluded = [
        'storage/updates',
        '.git',
    ];
    foreach ($excluded as $item) {
        if ($relative === $item || str_starts_with($relative, $item . '/')) return true;
    }
    return false;
}

function update_should_preserve_runtime(string $relative): bool {
    $relative=str_replace('\\','/',ltrim($relative,'/'));
    foreach(['config/local.php','storage/uploads','storage/receipts','storage/sessions','storage/logs','storage/backups','storage/updates']as$item)if($relative===$item||str_starts_with($relative,$item.'/'))return true;
    return false;
}

function create_files_zip_backup(string $destination): void {
    if (!class_exists('ZipArchive')) throw new RuntimeException('A extensão ZIP do PHP não está disponível.');
    $root = dirname(__DIR__);
    $files=[];$directories=[];
    $filter=new RecursiveCallbackFilterIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),function($current)use($root){$relative=str_replace('\\','/',substr($current->getPathname(),strlen($root)+1));return!update_should_exclude($relative);});
    $iterator=new RecursiveIteratorIterator($filter,RecursiveIteratorIterator::SELF_FIRST);
    foreach($iterator as$file){$path=$file->getPathname();$relative=str_replace('\\','/',substr($path,strlen($root)+1));if($file->isDir())$directories[]=$relative;elseif($file->isFile()){if(!is_readable($path))throw new RuntimeException('Backup interrompido: arquivo não pode ser lido.');$files[$path]=$relative;}}
    $zip = new ZipArchive();
    if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Não foi possível criar o backup dos arquivos.');
    }
    foreach($directories as$relative)$zip->addEmptyDir($relative);
    foreach($files as$path=>$relative){$contents=file_get_contents($path);if($contents===false||!$zip->addFromString($relative,$contents)){$zip->close();throw new RuntimeException('Falha ao adicionar arquivo ao backup.');}}
    if(!$zip->close()||!is_file($destination)||filesize($destination)===0)throw new RuntimeException('Falha ao finalizar o backup de arquivos.');
    $verify=new ZipArchive();if($verify->open($destination)!==true)throw new RuntimeException('O backup de arquivos não pode ser reaberto.');
    foreach(['index.php','config/local.php']as$required)if(is_file($root.'/'.$required)&&$verify->locateName($required)===false){$verify->close();throw new RuntimeException('Backup de arquivos incompleto.');}$verify->close();
}

function create_full_update_backup(): array {
    $stamp = date('Ymd-His');
    $dir = update_backup_root() . '/' . $stamp . '-v' . APP_VERSION;
    if (!mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException('Não foi possível criar a pasta do backup.');
    $sql = $dir . '/database.sql';
    $files = $dir . '/files.zip';
    create_files_zip_backup($files);
    create_database_sql_backup($sql);
    if(!is_file($sql)||filesize($sql)===0)throw new RuntimeException('Falha ao validar o backup do banco de dados.');
    $meta = ['created_at'=>date(DATE_ATOM),'version'=>APP_VERSION,'database'=>'database.sql','files'=>'files.zip'];
    file_put_contents($dir . '/backup.json', json_encode($meta, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    return ['directory'=>$dir,'sql'=>$sql,'files'=>$files,'relative'=>basename($dir)];
}

function update_safe_package_entry(string $name): bool {
    return (new \Gda\Modules\Caixas\Services\UpdateService())->packagePathIsSafe($name);
}

function inspect_update_package(string $zipPath): array {
    if (!class_exists('ZipArchive')) throw new RuntimeException('A extensão ZIP do PHP não está disponível.');
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) throw new RuntimeException('Não foi possível abrir o pacote ZIP.');
    $manifestRaw = $zip->getFromName('update.json');
    if ($manifestRaw === false) {
        $zip->close();
        throw new RuntimeException('Pacote inválido: arquivo update.json não encontrado na raiz.');
    }
    $manifest = json_decode($manifestRaw, true);
    if (!is_array($manifest)) {
        $zip->close();
        throw new RuntimeException('Pacote inválido: update.json não contém JSON válido.');
    }
    foreach (['product','version','minimum_version','migrations','database_changes'] as $required) {
        if (empty($manifest[$required])) { $zip->close(); throw new RuntimeException("Pacote inválido: campo {$required} ausente."); }
    }
    if ($manifest['product'] !== 'gda-caixas') {
        $zip->close(); throw new RuntimeException('Este pacote não pertence ao GDA Caixas.');
    }
    $migrations=\Gda\Core\Database\MigrationRunner::declared($manifest);
    if((bool)$manifest['database_changes']!==($migrations!==[])){$zip->close();throw new RuntimeException('Manifesto inválido: database_changes não corresponde às migrações declaradas.');}
    $entries=[];
    for ($i=0; $i<$zip->numFiles; $i++) {
        $entry = $zip->getNameIndex($i);
        if (!update_safe_package_entry($entry)) { $zip->close(); throw new RuntimeException('Pacote contém caminho inseguro: '.$entry); }
        if ($entry === 'config/local.php') { $zip->close(); throw new RuntimeException('Pacote recusado: config/local.php não pode ser distribuído.'); }
        $entries[$entry]=true;
    }
    foreach($migrations as$name)if(!isset($entries['database/migrations/'.$name])){$zip->close();throw new RuntimeException('Migração declarada ausente no pacote: '.$name);}
    $manifest['_package_sha256']=hash_file('sha256',$zipPath);$manifest['_migrations']=$migrations;
    $zip->close();
    return $manifest;
}

function extract_update_package(string $zipPath, string $destination): void {
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) throw new RuntimeException('Não foi possível abrir o pacote para extração.');
    if (!is_dir($destination) && !mkdir($destination, 0775, true) && !is_dir($destination)) {
        $zip->close(); throw new RuntimeException('Não foi possível criar a pasta temporária.');
    }
    for ($i=0; $i<$zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (!update_safe_package_entry($name)) { $zip->close(); throw new RuntimeException('Pacote contém caminho inseguro.'); }
    }
    if (!$zip->extractTo($destination)) { $zip->close(); throw new RuntimeException('Falha ao extrair o pacote.'); }
    $zip->close();
}

function run_pending_migrations(string $migrationDir): array {
    if(!is_dir($migrationDir))return[];$files=glob($migrationDir.'/*.php')?:[];sort($files,SORT_NATURAL);$names=array_map('basename',$files);$manifest=['migrations'=>$names];$declared=\Gda\Core\Database\MigrationRunner::declared($manifest);return(new \Gda\Core\Database\MigrationRunner(db()))->runDeclared($declared,$migrationDir);
}

function recursive_copy_update(string $source, string $destination, string $relative=''): void {
    if (!is_dir($destination) && !mkdir($destination, 0775, true) && !is_dir($destination)) {
        throw new RuntimeException('Não foi possível criar a pasta de destino: '.$relative);
    }
    $items = scandir($source);
    if ($items === false) throw new RuntimeException('Não foi possível ler o pacote extraído.');
    foreach ($items as $item) {
        if ($item === '.' || $item === '..' || $item === 'update.json') continue;
        $rel = ltrim($relative . '/' . $item, '/');
        if (update_should_exclude($rel) || update_should_preserve_runtime($rel)) continue;
        $from = $source . '/' . $item;
        $to = $destination . '/' . $item;
        if (is_dir($from)) recursive_copy_update($from, $to, $rel);
        else {
            if (!is_dir(dirname($to)) && !mkdir(dirname($to), 0775, true) && !is_dir(dirname($to))) throw new RuntimeException('Falha ao criar diretório para '.$rel);
            if (!copy($from, $to)) throw new RuntimeException('Falha ao atualizar o arquivo '.$rel);
        }
    }
}

function install_update_package(string $zipPath, string $originalName): array {
    update_table_ensure();
    $manifest = inspect_update_package($zipPath);
    $targetVersion = (string)$manifest['version'];
    if (!version_is_newer($targetVersion)) throw new RuntimeException('A versão do pacote não é superior à versão instalada.');
    if (!empty($manifest['minimum_version']) && version_compare(APP_VERSION, (string)$manifest['minimum_version'], '<')) {
        throw new RuntimeException('Este pacote exige no mínimo a versão '.e($manifest['minimum_version']).'.');
    }
    $migrations=$manifest['_migrations']??[];
    update_acquire_lock();update_activate_maintenance('creating_backup','Backup obrigatório em andamento.');
    $stmt = db()->prepare("INSERT INTO app_updates(version_from,version_to,package_name,status,backup_file,details,started_at) VALUES(?,?,?,'started',NULL,?,NOW())");
    $stmt->execute([APP_VERSION,$targetVersion,$originalName,json_encode(['stage'=>'creating_backup','package_sha256'=>$manifest['_package_sha256'],'declared_migrations'=>$migrations],JSON_UNESCAPED_UNICODE)]);
    $updateId = (int)db()->lastInsertId();
    $temp = update_storage_root() . '/work-' . bin2hex(random_bytes(6));
    $backup=null;$completed=false;$executed=[];
    try {
        $backup = create_full_update_backup();
        db()->prepare('UPDATE app_updates SET backup_file=?,details=? WHERE id=?')->execute([$backup['relative'],json_encode(['stage'=>'extracting','package_sha256'=>$manifest['_package_sha256'],'declared_migrations'=>$migrations],JSON_UNESCAPED_UNICODE),$updateId]);
        update_stage('extracting','Extraindo pacote em staging.');
        extract_update_package($zipPath, $temp);
        \Gda\Core\Database\MigrationRunner::validateFiles($migrations,$temp.'/database/migrations');
        update_stage('replacing_files','Substituindo arquivos validados.');
        recursive_copy_update($temp, dirname(__DIR__));
        update_stage('running_migrations','Executando migrações declaradas.');
        $executed=(new \Gda\Core\Database\MigrationRunner(db()))->runDeclared($migrations,$temp.'/database/migrations');
        update_stage('finalizing','Registrando conclusão da atualização.');
        db()->prepare("UPDATE app_updates SET status='completed',details=?,finished_at=NOW() WHERE id=?")
            ->execute([json_encode(['stage'=>'completed','migrations'=>$executed,'package_sha256'=>$manifest['_package_sha256']], JSON_UNESCAPED_UNICODE),$updateId]);
        $completed=true;update_release_runtime();
        return ['manifest'=>$manifest,'backup'=>$backup,'migrations'=>$executed,'status'=>'completed','final_version'=>$targetVersion];
    } catch (Throwable $e) {
        $failedStage=update_maintenance_state()['stage']??'desconhecida';
        update_stage('failed','Falha crítica. Restauração manual necessária.');
        db()->prepare("UPDATE app_updates SET status='failed',details=?,finished_at=NOW() WHERE id=?")
            ->execute([json_encode(['stage'=>'failed','message'=>'Atualização interrompida com segurança.','executed_migrations'=>$executed,'backup'=>$backup['relative']??null],JSON_UNESCAPED_UNICODE),$updateId]);
        throw new RuntimeException('Atualização interrompida na etapa '.$failedStage.'. O sistema permanece em manutenção; restaure o backup manualmente.');
    } finally {
        if (is_dir($temp)) update_remove_directory($temp);
    }
}

function update_remove_directory(string $dir): void {
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $file) $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
    @rmdir($dir);
}

function recent_app_updates(): array {
    update_table_ensure();
    return db()->query('SELECT * FROM app_updates ORDER BY id DESC LIMIT 20')->fetchAll();
}
