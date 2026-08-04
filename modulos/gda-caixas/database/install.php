<?php
$dbCfg=config('db');
try {
    $pdo=new PDO("mysql:host={$dbCfg['host']};port={$dbCfg['port']};dbname={$dbCfg['database']};charset={$dbCfg['charset']}",$dbCfg['username'],$dbCfg['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $sql=file_get_contents(__DIR__.'/schema.sql');$pdo->exec($sql);run_pending_migrations(__DIR__.'/migrations');ensure_app_schema();
    $hasUsers=(int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn()>0;
    if(!$hasUsers && $_SERVER['REQUEST_METHOD']==='POST'){
        verify_csrf();$name=trim((string)($_POST['name']??''));$email=trim((string)($_POST['email']??''));$password=(string)($_POST['password']??'');$confirm=(string)($_POST['password_confirm']??'');
        if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<8||$password!==$confirm){flash('error','Preencha os dados corretamente. A senha deve ter pelo menos 8 caracteres e coincidir com a confirmação.');header('Location: index.php?page=install');exit;}
        $pdo->beginTransaction();
        try {
            $identifier='client-'.bin2hex(random_bytes(6));
            $stmt=$pdo->prepare("INSERT INTO companies(gda_client_id,name,identifier,status) VALUES(NULL,?,?,'active')");$stmt->execute([$name,$identifier]);$companyId=(int)$pdo->lastInsertId();
            $stmt=$pdo->prepare("INSERT INTO users(company_id,name,email,password,role) VALUES(?,?,?,?, 'client_admin')");$stmt->execute([$companyId,$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
            $pdo->commit();
        } catch(Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); throw $e; }
        $hasUsers=true;
    }
    if($hasUsers && !(int)$pdo->query('SELECT COUNT(*) FROM cycles')->fetchColumn()){$companyId=(int)$pdo->query("SELECT company_id FROM users WHERE role='client_admin' AND company_id IS NOT NULL ORDER BY id LIMIT 1")->fetchColumn();$year=(int)date('Y');$month=(int)date('n');$stmt=$pdo->prepare("INSERT INTO cycles(company_id,name,year,month,daily_amount,status) VALUES(?,?,?,?,?, 'active')");$stmt->execute([$companyId,'Ciclo '.month_name($month).' '.$year,$year,$month,20]);$cycleId=(int)$pdo->lastInsertId();$pdo->prepare("INSERT INTO participants(company_id,name,active,access_token) VALUES(?,'Administração',1,NULL)")->execute([$companyId]);$ownerId=(int)$pdo->lastInsertId();$pdo->prepare('INSERT INTO cycle_participants(cycle_id,participant_id) VALUES(?,?)')->execute([$cycleId,$ownerId]);$pdo->prepare('INSERT INTO cycle_points(cycle_id,day,participant_id) VALUES(?,?,?)')->execute([$cycleId,1,$ownerId]);}
    if(!$hasUsers){render_header('Configurar administrador');echo '<section class="login-card"><h1>Criar administrador</h1><p>A conexão com o banco funcionou. Agora defina o acesso principal do sistema.</p><form method="post"><input type="hidden" name="csrf" value="'.e(csrf_token()).'"><label>Nome<input name="name" required></label><label>E-mail<input type="email" name="email" autocomplete="username" required></label><label>Senha<input type="password" name="password" minlength="8" autocomplete="new-password" required></label><label>Confirmar senha<input type="password" name="password_confirm" minlength="8" autocomplete="new-password" required></label><button class="btn primary">Concluir instalação</button></form></section>';render_footer();exit;}
    render_header('Instalação concluída');echo '<section class="login-card"><h1>Instalação concluída</h1><p>Banco conectado, tabelas criadas e acesso administrativo configurado.</p><a class="btn primary" href="index.php?page=login">Ir para o login</a></section>';render_footer();
} catch(Throwable $e) {
    http_response_code(500);render_header('Falha na instalação');echo '<section class="login-card"><h1>Não foi possível instalar</h1><p>Revise o arquivo <code>config/local.php</code>, especialmente banco, usuário e senha.</p><div class="alert error">'.e($e->getMessage()).'</div></section>';render_footer();
}
