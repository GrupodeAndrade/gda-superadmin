<?php
function page_login(): void {
    if (!empty($_SESSION['user'])) redirect('dashboard');
    $error = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $auth = new \Gda\Core\Auth\AuthService(new \Gda\Modules\Caixas\Repositories\UserRepository(db()));
        if ($auth->attempt((string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''))) redirect('dashboard');
        $error = 'E-mail ou senha inválidos.';
    }
    render_header('Entrar');
    ?>
    <section class="login-card">
      <?php $loginLogo=gda_logo_url(); if($loginLogo):?><img class="login-logo" src="<?= e($loginLogo) ?>" alt="Logo GDA"><?php else:?><div class="login-monogram">GDA</div><?php endif;?>
      <h1>GDA Caixas</h1><p>Entre para administrar seus caixas.</p>
      <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
      <form method="post"><input type="hidden" name="csrf" value="<?= csrf_token() ?>">
        <label>E-mail<input type="email" name="email" autocomplete="username" required></label>
        <label>Senha<input type="password" name="password" autocomplete="current-password" required></label>
        <button class="btn primary">Entrar</button>
      </form>
    </section>
    <?php render_footer();
}

function page_dashboard(): void {
    $cycle = current_cycle();
    render_header('Dashboard');
    if (!$cycle) { echo '<div class="empty">Nenhum ciclo ativo.</div>'; render_footer(); return; }
    $days = days_in_cycle($cycle);
    $today = ((int)date('Y') === (int)$cycle['year'] && (int)date('n') === (int)$cycle['month']) ? (int)date('j') : 1;
    $adminId = administration_id((int)$cycle['id']);
    $stmt = db()->prepare("SELECT p.*, cp.joined_at FROM participants p JOIN cycle_participants cp ON cp.participant_id=p.id WHERE cp.cycle_id=? AND p.active=1 AND (p.id<>? OR ?=1) ORDER BY p.name");
    $stmt->execute([$cycle['id'], $adminId ?? 0, cycle_admin_pays($cycle)?1:0]); $participants = $stmt->fetchAll();
    $stmt = db()->prepare("SELECT pt.day, p.name participant_name, pt.participant_id FROM cycle_points pt LEFT JOIN participants p ON p.id=pt.participant_id WHERE pt.cycle_id=? ORDER BY pt.day");
    $stmt->execute([$cycle['id']]); $points = $stmt->fetchAll();
    $pointMap = []; foreach ($points as $pt) $pointMap[(int)$pt['day']] = $pt;
    $cash = cycle_cash((int)$cycle['id']);
    $todayPoint = $pointMap[$today] ?? null;
    $totalPoints = paying_point_count($cycle);
    $expectedDay = $totalPoints * (float)$cycle['daily_amount'];
    $receivedToday = contributions_received_on((int)$cycle['id'], date('Y-m-d'));
    ?>
    <div class="dashboard-welcome">Bem-vindo, Administrador!</div>
    <div class="page-head"><div><h1><?= e($cycle['name']) ?></h1><p><?= e(month_name((int)$cycle['month'])) ?> de <?= e($cycle['year']) ?> · Visão geral do caixa selecionado</p></div><a class="btn primary" href="<?= url('contribution') ?>">+ Registrar aporte</a></div>
    <section class="stats">
      <article><span>Valor por ponto/dia</span><strong><?= money($cycle['daily_amount']) ?></strong></article>
      <article><span>Em caixa</span><strong><?= money($cash) ?></strong></article>
      <article><span>Meta diária (<?= $totalPoints ?> ponto<?= $totalPoints===1?'':'s' ?> pagante<?= $totalPoints===1?'':'s' ?>)</span><strong><?= money($expectedDay) ?></strong></article>
      <article><span>Pagamentos recebidos hoje</span><strong><?= money($receivedToday) ?></strong><small><?= $receivedToday >= $expectedDay && $expectedDay > 0 ? 'Meta diária alcançada' : 'Conferência da meta diária' ?></small></article>
      <article><span>Ponto de hoje</span><strong><?= e($todayPoint['participant_name'] ?? 'Não atribuído') ?></strong></article>
    </section>
    <section class="panel dashboard-chart"><div class="panel-title"><h2>Progresso da arrecadação diária</h2><span><?= money($receivedToday) ?> de <?= money($expectedDay) ?></span></div><div class="progress-track"><i style="width:<?= $expectedDay>0?min(100,round(($receivedToday/$expectedDay)*100)):0 ?>%"></i></div></section>
    <section class="panel"><div class="panel-title"><h2>Calendário de pontos</h2><a href="<?= url('points') ?>">Editar pontos</a></div>
      <div class="points-calendar">
      <?php for ($d=1;$d<=$days;$d++): $pt=$pointMap[$d]??null; ?>
        <div class="point-card <?= $d===$today?'today':'' ?> <?= $d===1?'owner-point':'' ?>"><span>Dia <?= $d ?></span><strong><?= e($pt['participant_name'] ?? 'Livre') ?></strong><?php if($d===1):?><small>Administração</small><?php endif;?></div>
      <?php endfor; ?>
      </div>
    </section>
    <section class="panel"><div class="panel-title"><h2>Participantes pagantes</h2><span><?= count($participants) ?> pagantes</span></div>
      <div class="table-wrap"><table><thead><tr><th>Participante</th><th>Pontos</th><th>Deve pagar/dia</th><th>Total pago</th><th>Recebe nos dias</th><th>Progresso diário</th></tr></thead><tbody>
      <?php foreach($participants as $p):
        $paid=participant_total_paid((int)$cycle['id'],(int)$p['id']);
        $stmt2=db()->prepare('SELECT day FROM cycle_points WHERE cycle_id=? AND participant_id=? ORDER BY day');$stmt2->execute([$cycle['id'],$p['id']]);$pointDays=$stmt2->fetchAll(PDO::FETCH_COLUMN);
        $pointCount=count($pointDays); $dailyDue=$pointCount*(float)$cycle['daily_amount'];
      ?>
        <tr><td><a class="name-link" href="<?= url('participant',['id'=>$p['id']]) ?>"><?= e($p['name']) ?></a></td><td><?= $pointCount ?></td><td><?= money($dailyDue) ?></td><td><?= money($paid) ?></td><td><?= $pointDays ? implode(', ',array_map(fn($d)=>sprintf('%02d/%02d',$d,$cycle['month']),$pointDays)):'—' ?></td><td>
          <?php if($dailyDue>0): ?><div class="mini-days"><?php for($d=1;$d<=$days;$d++):$fill=day_fill($paid,$dailyDue,$d);?><span title="Dia <?= $d ?> — <?= round($fill*100) ?>%"><i style="width:<?= round($fill*100) ?>%"></i></span><?php endfor;?></div><?php else:?><small>Sem ponto atribuído</small><?php endif;?>
        </td></tr>
      <?php endforeach; ?></tbody></table></div>
    </section>
    <?php render_footer();
}

function page_participants(): void {
    $cycle=current_cycle();
    $rows=[]; if($cycle){$q=db()->prepare('SELECT p.* FROM participants p JOIN cycle_participants cp ON cp.participant_id=p.id WHERE cp.cycle_id=? ORDER BY p.active DESC,p.name');$q->execute([$cycle['id']]);$rows=$q->fetchAll();}
    render_header('Participantes'); ?>
    <div class="page-head"><div><h1>Participantes</h1><p>Cadastre pessoas uma única vez e atribua um ou mais pontos na tela de Pontos.</p></div></div>
    <section class="split"><div class="panel"><h2>Novo participante</h2><form method="post" action="<?= url('participant_save') ?>"><input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <label>Nome<input name="name" required></label><label>Telefone<input name="phone" class="phone-mask" inputmode="numeric" autocomplete="tel" maxlength="15" placeholder="(99) 99999-9999"></label><label>Tipo da chave PIX<select name="pix_type"><option value="">Selecione</option><option>CPF</option><option>CNPJ</option><option>Celular</option><option>E-mail</option><option>Chave aleatória</option></select></label><label>Chave PIX<input name="pix_key" autocomplete="off"></label><label>Observações<textarea name="notes" rows="3"></textarea></label><button class="btn primary">Salvar participante</button></form></div>
      <div class="panel"><div class="panel-title"><h2>Lista</h2><input class="table-search" data-table-search="#participants-table" placeholder="Pesquisar"></div><div class="table-wrap"><table id="participants-table"><thead><tr><th>Nome</th><th>Contato</th><th>Pontos</th><th>Deve pagar/dia</th><th>Ações</th></tr></thead><tbody><?php foreach($rows as $r): $pc=$cycle?participant_point_count((int)$cycle['id'],(int)$r['id']):0; $isAdmin=$cycle&&is_administration((int)$cycle['id'],(int)$r['id']); $adminPays=$cycle?cycle_admin_pays($cycle):false; $due=($cycle&&(!$isAdmin||$adminPays))?$pc*(float)$cycle['daily_amount']:0;?><tr><td><a class="name-link" href="<?= url('participant',['id'=>$r['id']]) ?>"><?= e($r['name']) ?></a><?= $isAdmin?' <small>(gestão)</small>':'' ?></td><td><?= e($r['phone'] ?: '—') ?></td><td><?= $pc ?></td><td><?= ($isAdmin&&!$adminPays)?'—':money($due) ?></td><td><a class="btn small" href="<?= url('participant_edit',['id'=>$r['id']]) ?>">Editar</a></td></tr><?php endforeach;?></tbody></table></div></div></section>
    <?php render_footer();
}

function participant_save(): void {
    verify_csrf(); $name=trim($_POST['name']??'');
    if($name===''){flash('error','Informe o nome.');redirect('participants');}
    $pdo=db();$pdo->beginTransaction();
    $stmt=$pdo->prepare('INSERT INTO participants(company_id,name,phone,email,pix_type,pix_key,notes,access_token) VALUES(?,?,?,?,?,?,?,?)');
    $stmt->execute([company_id(),$name,trim($_POST['phone']??''),null,trim($_POST['pix_type']??''),trim($_POST['pix_key']??''),trim($_POST['notes']??''),null]);
    $id=(int)$pdo->lastInsertId();$cycle=current_cycle();
    if($cycle){$pdo->prepare('INSERT IGNORE INTO cycle_participants(cycle_id,participant_id) VALUES(?,?)')->execute([$cycle['id'],$id]);}
    $pdo->commit();flash('success','Participante cadastrado. Agora atribua seus pontos na tela Pontos.');redirect('participants');
}

function page_participant_edit(): void {
    $id=(int)($_GET['id']??0); $stmt=db()->prepare('SELECT * FROM participants WHERE id=? AND company_id=?'); $stmt->execute([$id,company_id()]); $p=$stmt->fetch();
    if(!$p){http_response_code(404);exit('Participante não encontrado.');}
    $cycle=current_cycle(); $isAdmin=$cycle && administration_id((int)$cycle['id'])===$id;
    render_header('Editar participante'); ?>
    <div class="page-head"><div><h1>Editar participante</h1><p>Os pontos e o histórico financeiro não serão alterados.</p></div></div>
    <section class="panel narrow"><form method="post" action="<?= url('participant_update') ?>"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="id" value="<?= $id ?>">
      <label>Nome<input name="name" value="<?= e($p['name']) ?>" required <?= $isAdmin?'readonly':'' ?>></label>
      <?php if($isAdmin):?><small>O nome da Administração é protegido porque ela ocupa o primeiro ponto.</small><?php endif;?>
      <label>Telefone<input name="phone" class="phone-mask" inputmode="numeric" autocomplete="tel" maxlength="15" value="<?= e($p['phone']) ?>" placeholder="(99) 99999-9999"></label>
      <label>Tipo da chave PIX<select name="pix_type"><option value="">Selecione</option><?php foreach(['CPF','CNPJ','Celular','E-mail','Chave aleatória'] as $type):?><option <?= $p['pix_type']===$type?'selected':'' ?>><?= e($type) ?></option><?php endforeach;?></select></label>
      <label>Chave PIX<input name="pix_key" value="<?= e($p['pix_key']) ?>" autocomplete="off"></label>
      <label>Observações<textarea name="notes" rows="3"><?= e($p['notes']) ?></textarea></label>
      <div class="form-actions"><button class="btn primary">Salvar alterações</button><a class="btn" href="<?= url('participant',['id'=>$id]) ?>">Cancelar</a></div>
    </form>
    <?php if(!$isAdmin): ?>
      <div class="danger-zone participant-delete-zone">
        <h3>Apagar participante</h3>
        <p>Esta ação remove o participante, seus pontos, aportes e pagamentos somente do caixa atual.</p>
        <form method="post" action="<?= url('participant_delete') ?>" onsubmit="return confirm('Apagar este participante do caixa atual? Esta ação não poderá ser desfeita sem restaurar um backup.');">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="id" value="<?= $id ?>">
          <button class="btn danger">Apagar participante</button>
        </form>
      </div>
    <?php endif; ?>
    </section><?php render_footer();
}

function participant_delete(): void {
    verify_csrf();
    $id=(int)($_POST['id']??0); $cycle=current_cycle();
    if(!$cycle||$id<1){flash('error','Participante ou caixa inválido.');redirect('participants');}
    if(is_administration((int)$cycle['id'],$id)){flash('error','A Administração não pode ser apagada.');redirect('participant_edit',['id'=>$id]);}
    $check=db()->prepare('SELECT COUNT(*) FROM cycle_participants WHERE cycle_id=? AND participant_id=?');$check->execute([$cycle['id'],$id]);
    if(!(int)$check->fetchColumn()){flash('error','Este participante não pertence ao caixa atual.');redirect('participants');}
    automatic_cycle_backup($cycle,'antes-de-apagar-participante');
    $pdo=db();$pdo->beginTransaction();
    try{
        $pdo->prepare('DELETE FROM payouts WHERE cycle_id=? AND participant_id=?')->execute([$cycle['id'],$id]);
        $pdo->prepare('DELETE FROM contributions WHERE cycle_id=? AND participant_id=?')->execute([$cycle['id'],$id]);
        $pdo->prepare('UPDATE cycle_points SET participant_id=NULL WHERE cycle_id=? AND participant_id=?')->execute([$cycle['id'],$id]);
        $pdo->prepare('DELETE FROM cycle_participants WHERE cycle_id=? AND participant_id=?')->execute([$cycle['id'],$id]);
        $other=$pdo->prepare('SELECT COUNT(*) FROM cycle_participants cp INNER JOIN cycles c ON c.id=cp.cycle_id WHERE cp.participant_id=? AND c.company_id=?');$other->execute([$id,company_id()]);
        if(!(int)$other->fetchColumn()) $pdo->prepare('DELETE FROM participants WHERE id=? AND company_id=?')->execute([$id,company_id()]);
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    flash('success','Participante apagado do caixa atual.');redirect('participants');
}

function participant_update(): void {
    verify_csrf(); $id=(int)($_POST['id']??0); $cycle=current_cycle();
    $stmt=db()->prepare('SELECT * FROM participants WHERE id=? AND company_id=?');$stmt->execute([$id,company_id()]);$existing=$stmt->fetch();
    if(!$existing){flash('error','Participante não encontrado.');redirect('participants');}
    $isAdmin=$cycle && administration_id((int)$cycle['id'])===$id;
    $name=$isAdmin?'Administração':trim($_POST['name']??'');
    if($name===''){flash('error','Informe o nome.');redirect('participant_edit',['id'=>$id]);}
    automatic_cycle_backup($cycle,'antes-de-editar-participante');
    $stmt=db()->prepare('UPDATE participants SET name=?,phone=?,pix_type=?,pix_key=?,notes=? WHERE id=? AND company_id=?');
    $stmt->execute([$name,trim($_POST['phone']??''),trim($_POST['pix_type']??''),trim($_POST['pix_key']??''),trim($_POST['notes']??''),$id,company_id()]);
    flash('success','Participante atualizado.');redirect('participant',['id'=>$id]);
}

function page_participant(): void {
    $id=(int)($_GET['id']??0);$stmt=db()->prepare('SELECT * FROM participants WHERE id=? AND company_id=?');$stmt->execute([$id,company_id()]);$p=$stmt->fetch();if(!$p){http_response_code(404);exit('Participante não encontrado.');}
    $cycle=current_cycle();$paid=$cycle?participant_total_paid((int)$cycle['id'],$id):0;$days=$cycle?days_in_cycle($cycle):0;
    $stmt=db()->prepare('SELECT * FROM contributions WHERE participant_id=? AND cycle_id=? ORDER BY paid_at DESC,id DESC');$stmt->execute([$id,$cycle['id']??0]);$history=$stmt->fetchAll();
    $pointDays=[];$pointCount=0;$dailyDue=0;$isAdmin=false;
    if($cycle){
        $stmt=db()->prepare('SELECT day FROM cycle_points WHERE cycle_id=? AND participant_id=? ORDER BY day');$stmt->execute([$cycle['id'],$id]);$pointDays=$stmt->fetchAll(PDO::FETCH_COLUMN);
        $pointCount=count($pointDays);$isAdmin=administration_id((int)$cycle['id'])===$id;$dailyDue=($isAdmin&&!cycle_admin_pays($cycle))?0:$pointCount*(float)$cycle['daily_amount'];
    }
    $linkState=$cycle?participant_access_service()->ownedLink(company_id(),(int)$cycle['id'],$id):null;
    $linkSessionKey=$cycle?((int)$cycle['id'].':'.$id):'';$plainToken=$linkSessionKey!==''?($_SESSION['new_participant_links'][$linkSessionKey]??null):null;if($plainToken)unset($_SESSION['new_participant_links'][$linkSessionKey]);
    $portalUrl=is_string($plainToken)?participant_portal_url($plainToken):'';
    $pendingToday=max(0,$dailyDue-contributions_received_on((int)($cycle['id']??0),date('Y-m-d')));
    $message=$portalUrl?"Olá, {$p['name']}!\n\nVocê foi adicionado ao {$cycle['name']}.\n\nUse este link individual para acompanhar seus aportes, recebimentos e enviar comprovantes:\n\n{$portalUrl}\n\nEste link é pessoal. Não compartilhe com outras pessoas.":'';
    $whatsappUrl=$portalUrl?participant_whatsapp_url($p,$message):'';
    $paymentPix=$cycle?administration_for_cycle((int)$cycle['id'],company_id()):null;
    render_header($p['name']); ?>
    <div class="page-head"><div><h1><?= e($p['name']) ?></h1><p><?= e($p['phone'] ?: 'Sem telefone') ?></p><?php if(!empty($paymentPix['pix_key'])):?><p class="pix-line"><strong>PIX da Administração:</strong> <code id="pix-key"><?= e($paymentPix['pix_key']) ?></code> <button type="button" class="btn small" onclick="copyPixKey('pix-key')">Copiar PIX</button></p><?php else:?><p class="muted">A chave PIX da Administração ainda não foi cadastrada.</p><?php endif;?></div><div class="form-actions"><a class="btn" href="<?= url('participant_edit',['id'=>$id]) ?>">Editar participante</a><?php if($portalUrl):?><a class="btn" target="_blank" href="<?= e($portalUrl) ?>">Ver como cliente</a><?php endif;?><?php if($whatsappUrl):?><a class="btn whatsapp" target="_blank" href="<?= e($whatsappUrl) ?>">Enviar lembrete</a><?php endif;?><?php if(!$isAdmin||($cycle&&cycle_admin_pays($cycle))):?><a class="btn primary" href="<?= url('contribution',['participant_id'=>$id]) ?>">+ Novo aporte</a><?php endif;?></div></div>
    <?php if($cycle&&!$isAdmin):?><section class="panel participant-access-panel"><div class="panel-title"><div><h2>Link individual do participante</h2><p>Acesso exclusivo a este participante no caixa <?= e($cycle['name']) ?>.</p></div><span class="status <?= !empty($linkState['access_enabled'])?'status-sent':'status-cancelled' ?>"><?= empty($linkState['access_token_hash'])?'Não gerado':(!empty($linkState['access_enabled'])?'Ativo':'Desativado') ?></span></div>
      <?php if($portalUrl):?><div class="access-link-once"><p><strong>Copie agora:</strong> por segurança, o token não poderá ser recuperado depois que esta página for fechada.</p><code id="participant-access-link"><?= e($portalUrl) ?></code><div class="form-actions"><button type="button" class="btn primary" onclick="copyPixKey('participant-access-link')">Copiar link</button><?php if($whatsappUrl):?><a class="btn whatsapp" target="_blank" rel="noopener noreferrer" href="<?= e($whatsappUrl) ?>">Enviar pelo WhatsApp</a><?php endif;?><a class="btn" target="_blank" rel="noreferrer" href="<?= e($portalUrl) ?>">Abrir portal</a></div></div><?php elseif(!empty($linkState['access_token_hash'])):?><p>Identificação do link: <code><?= e($linkState['access_token_prefix']) ?>…</code>. O token completo não fica armazenado. Regenere somente se precisar compartilhá-lo novamente.</p><?php else:?><p>Nenhum link foi criado para este vínculo.</p><?php endif;?>
      <p><strong>Último acesso:</strong> <?= !empty($linkState['last_access_at'])?date('d/m/Y H:i',strtotime($linkState['last_access_at'])):'Nunca acessado' ?></p>
      <?php if(($_SESSION['user']['role']??'')==='client_admin'):?><div class="participant-access-actions"><?php if(empty($linkState['access_token_hash'])):?><form method="post" action="<?= url('participant_link_generate') ?>"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="participant_id" value="<?= $id ?>"><button class="btn primary">Gerar link</button></form><?php else:?><form method="post" action="<?= url('participant_link_rotate') ?>" onsubmit="return confirm('Regenerar o link? O link anterior deixará de funcionar imediatamente.');"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="participant_id" value="<?= $id ?>"><button class="btn">Regenerar link</button></form><?php if(!empty($linkState['access_enabled'])):?><form method="post" action="<?= url('participant_link_disable') ?>"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="participant_id" value="<?= $id ?>"><button class="btn danger">Desativar link</button></form><?php else:?><form method="post" action="<?= url('participant_link_enable') ?>"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="participant_id" value="<?= $id ?>"><button class="btn primary">Ativar link atual</button></form><?php endif;?><?php endif;?></div><?php endif;?>
    </section><?php endif;?>
    <?php if($isAdmin && !cycle_admin_pays($cycle)): ?>
      <section class="stats"><article><span>Ponto</span><strong><?= $pointDays?implode(', ',$pointDays):'1' ?></strong></article><article><span>Função</span><strong>Administração</strong></article><article><span>Situação</span><strong>Não pagante</strong></article></section>
      <div class="alert success">Neste caixa, a Administração ocupa o primeiro ponto, mas não realiza aportes.</div>
    <?php else: ?>
      <section class="stats"><article><span>Pontos</span><strong><?= $pointCount ?></strong></article><article><span>Deve pagar por dia</span><strong><?= money($dailyDue) ?></strong></article><article><span>Total pago</span><strong><?= money($paid) ?></strong></article><article><span>Recebe nos dias</span><strong><?= $pointDays?implode(', ',$pointDays):'—' ?></strong></article></section>
      <?php if($dailyDue>0): ?><section class="stats"><article><span>Dias quitados</span><strong><?= floor($paid/$dailyDue) ?></strong></article><article><span>Crédito parcial</span><strong><?= money(fmod($paid,$dailyDue)) ?></strong></article></section>
      <section class="panel"><h2>Calendário de contribuição</h2><div class="participant-calendar"><?php for($d=1;$d<=$days;$d++):$fill=day_fill($paid,$dailyDue,$d);?><div class="day-box"><span>Dia <?= $d ?></span><div class="fill"><i style="width:<?= round($fill*100) ?>%"></i></div><small><?= money($fill*$dailyDue) ?> / <?= money($dailyDue) ?></small></div><?php endfor;?></div></section>
      <?php else:?><div class="alert error">Este participante ainda não possui pontos. Atribua pelo menu Pontos para calcular o valor diário.</div><?php endif;?>
    <?php endif; ?>
    <?php if($portalUrl): ?><section class="panel qr-panel"><div><h2>QR Code do participante</h2><p>Use este código para abrir a área do participante pelo celular.</p></div><img src="https://quickchart.io/qr?size=180&text=<?= rawurlencode($portalUrl) ?>" alt="QR Code de acesso"></section><?php endif; ?>
    <?php if(!$isAdmin||($cycle&&cycle_admin_pays($cycle))): ?><section class="panel"><h2>Histórico de aportes</h2><div class="table-wrap"><table><thead><tr><th>Data</th><th>Valor</th><th>Observação</th><th>Ação</th></tr></thead><tbody><?php foreach($history as $h):?><tr><td><?= date('d/m/Y H:i',strtotime($h['paid_at'])) ?></td><td><?= money($h['amount']) ?></td><td><?= e($h['notes']) ?></td><td><form method="post" action="<?= url('contribution_undo') ?>" onsubmit="return confirm('Desfazer este lançamento de <?= money($h['amount']) ?>?');"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="contribution_id" value="<?= (int)$h['id'] ?>"><input type="hidden" name="participant_id" value="<?= (int)$p['id'] ?>"><a class="btn small" target="_blank" href="<?= url('receipt',['id'=>$h['id']]) ?>">Recibo</a> <button class="btn small danger">Desfazer</button></form></td></tr><?php endforeach;?></tbody></table></div></section><?php endif; ?>
    <?php render_footer();
}

function page_contribution(): void {
    $cycle=current_cycle();
    if(!$cycle){ flash('error','Nenhum ciclo ativo.'); redirect('dashboard'); }$adminId=administration_id((int)$cycle['id']);$stmt=db()->prepare("SELECT p.* FROM participants p JOIN cycle_participants cp ON cp.participant_id=p.id WHERE cp.cycle_id=? AND p.active=1 AND (p.id<>? OR ?=1) ORDER BY p.name");$stmt->execute([$cycle['id'],$adminId??0,cycle_admin_pays($cycle)?1:0]);$participants=$stmt->fetchAll();
    render_header('Novo aporte'); ?>
    <div class="page-head"><div><h1>Registrar aporte</h1><p>O valor será distribuído conforme o valor diário do participante, calculado por sua quantidade de pontos.</p></div></div>
    <section class="panel narrow"><form method="post" action="<?= url('contribution_save') ?>"><input type="hidden" name="csrf" value="<?= csrf_token() ?>">
      <label>Participante<select name="participant_id" id="participant-select" required><option value="">Selecione</option><?php foreach($participants as $p):$pc=participant_point_count((int)$cycle['id'],(int)$p['id']);$due=$pc*(float)$cycle['daily_amount'];?><option value="<?= $p['id'] ?>" data-daily="<?= number_format($due,2,'.','') ?>" <?= (int)($_GET['participant_id']??0)===(int)$p['id']?'selected':'' ?>><?= e($p['name']) ?> — <?= $pc ?> ponto(s) — <?= money($due) ?>/dia</option><?php endforeach;?></select></label>
      <label>Valor do aporte<input type="text" id="contribution-amount" class="money-input" name="amount" inputmode="decimal" autocomplete="off" value="<?= number_format((float)$cycle['daily_amount'], 2, '.', '') ?>" required></label>
      <small>Ao selecionar o participante, o valor sugerido muda para a obrigação diária dele.</small>
      <label>Data e hora<input type="datetime-local" name="paid_at" value="<?= date('Y-m-d\TH:i') ?>" required></label>
      <label>Observação<textarea name="notes" rows="3"></textarea></label><button class="btn primary">Registrar aporte</button>
    </form></section><?php render_footer();
}

function contribution_save(): void { verify_csrf();$cycle=current_cycle();automatic_cycle_backup($cycle,'antes-de-registrar-aporte');$pid=(int)($_POST['participant_id']??0);$amount=parse_money($_POST['amount']??0);if(!$cycle||$pid<1||$amount<=0){flash('error','Dados inválidos.');redirect('contribution');}$check=db()->prepare('SELECT COUNT(*) FROM cycle_participants WHERE cycle_id=? AND participant_id=?');$check->execute([$cycle['id'],$pid]);if(!(int)$check->fetchColumn()){flash('error','Participante não pertence ao ciclo ativo.');redirect('contribution');}if(is_administration((int)$cycle['id'],$pid)&&!cycle_admin_pays($cycle)){flash('error','Neste caixa, a Administração não realiza aportes.');redirect('contribution');}$stmt=db()->prepare('INSERT INTO contributions(cycle_id,participant_id,amount,paid_at,notes,created_by) VALUES(?,?,?,?,?,?)');$stmt->execute([$cycle['id'],$pid,$amount,str_replace('T',' ',$_POST['paid_at']).':00',trim($_POST['notes']??''),$_SESSION['user']['id']]);flash('success','Aporte registrado e distribuído no calendário.');redirect('participant',['id'=>$pid]); }

function page_points(): void {
    $cycle=current_cycle();
    if(!$cycle){ flash('error','Nenhum ciclo ativo.'); redirect('dashboard'); }$days=days_in_cycle($cycle);$adminId=administration_id((int)$cycle['id']);$participants=db()->prepare("SELECT p.* FROM participants p JOIN cycle_participants cp ON cp.participant_id=p.id WHERE cp.cycle_id=? AND p.active=1 AND p.id<>? ORDER BY p.name");$participants->execute([$cycle['id'],$adminId??0]);$participants=$participants->fetchAll();
    $stmt=db()->prepare('SELECT * FROM cycle_points WHERE cycle_id=? ORDER BY day');$stmt->execute([$cycle['id']]);$assigned=[];foreach($stmt->fetchAll() as $x)$assigned[(int)$x['day']]=$x['participant_id'];
    render_header('Pontos'); ?><div class="page-head"><div><h1>Distribuição dos pontos</h1><p>Um participante pode ocupar vários dias. Cada ponto aumenta em <?= money($cycle['daily_amount']) ?> o valor que ele deve pagar por dia.</p></div></div>
    <section class="panel"><form method="post" action="<?= url('point_save') ?>"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><div class="points-editor">
    <?php for($d=1;$d<=$days;$d++):?><label><span>Dia <?= $d ?><?= $d===1?' — Administração':'' ?></span><select name="points[<?= $d ?>]" <?= $d===1?'disabled':'' ?>><option value="">Não atribuído</option><?php foreach($participants as $p):?><option value="<?= $p['id'] ?>" <?= (int)($assigned[$d]??0)===(int)$p['id']?'selected':'' ?>><?= e($p['name']) ?></option><?php endforeach;?></select><?php if($d===1):?><input type="hidden" name="points[1]" value="<?= (int)$adminId ?>"><?php endif;?></label><?php endfor;?></div><button class="btn primary">Salvar pontos</button></form></section><?php render_footer();
}

function point_save(): void {
    verify_csrf();$cycle=current_cycle();automatic_cycle_backup($cycle,'antes-de-alterar-pontos');$points=$_POST['points']??[];$adminId=administration_id((int)$cycle['id']);
    if($adminId)$points[1]=$adminId;
    $pdo=db();$pdo->beginTransaction();$stmt=$pdo->prepare('INSERT INTO cycle_points(cycle_id,day,participant_id) VALUES(?,?,?) ON DUPLICATE KEY UPDATE participant_id=VALUES(participant_id)');
    foreach($points as $day=>$pid){$day=(int)$day;if($day<1||$day>days_in_cycle($cycle))continue;$stmt->execute([$cycle['id'],$day,$pid!==''?(int)$pid:null]);}
    $pdo->commit();flash('success','Pontos atualizados. Os valores diários foram recalculados automaticamente.');redirect('points');
}

function page_payouts(): void {
    $cycle = current_cycle();
    if(!$cycle){ flash('error','Nenhum ciclo ativo.'); redirect('dashboard'); }

    $stmt = db()->prepare(
        "SELECT pt.day, p.name, pt.participant_id, po.id AS payout_id, po.amount, po.status, po.paid_at
         FROM cycle_points pt
         LEFT JOIN participants p ON p.id = pt.participant_id
         LEFT JOIN payouts po ON po.cycle_id = pt.cycle_id AND po.point_day = pt.day
         WHERE pt.cycle_id = ?
         ORDER BY pt.day"
    );
    $stmt->execute([$cycle['id']]);
    $rows = $stmt->fetchAll();

    $grossPayout = cycle_gross_payout($cycle);
    $adminCommission = cycle_admin_commission((float)$cycle['daily_amount']);
    $suggested = cycle_net_payout($cycle);
    $cashAvailable = cycle_cash((int)$cycle['id']);

    render_header('Pagamentos');
    ?>
    <div class="page-head">
        <div>
            <h1>Pagamentos dos pontos</h1>
            <p>O participante recebe o total arrecadado menos uma diária, destinada à comissão da Administração.</p>
        </div>
        <div class="cash-pill">
            Saldo disponível: <strong><?= money($cashAvailable) ?></strong>
        </div>
    </div>

    <section class="panel">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Dia</th>
                        <th>Participante</th>
                        <th>Arrecadação bruta</th>
                        <th>Comissão</th>
                        <th>Valor previsto</th>
                        <th>Valor pago</th>
                        <th>Restante</th>
                        <th>Status</th>
                        <th>Ação</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $status = $row['status'] ?? 'pending';
                    $paidAmount = $row['amount'] !== null ? (float)$row['amount'] : 0.0;
                    $remainingAmount = max(0, $suggested - $paidAmount);
                    $statusText = 'Pendente';

                    if ($status === 'paid' && !empty($row['paid_at'])) {
                        $statusText = ($paidAmount + 0.00001 < $suggested ? 'Parcial' : 'Pago') . ' em ' . date('d/m/Y', strtotime($row['paid_at']));
                    }
                    ?>
                    <tr>
                        <td><?= (int) $row['day'] ?></td>
                        <td><?= e($row['name'] ?? 'Não atribuído') ?></td>
                        <td><?= money($grossPayout) ?></td>
                        <td><?= money($adminCommission) ?></td>
                        <td><?= money($suggested) ?></td>
                        <td><?= $row['amount'] !== null ? money($paidAmount) : '—' ?></td>
                        <td><?= money($remainingAmount) ?></td>
                        <td><?= e($statusText) ?></td>
                        <td>
                            <?php if (!empty($row['participant_id']) && $status !== 'paid'): ?>
                                <form class="inline-form" method="post" action="<?= url('payout_save') ?>">
                                    <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
                                    <input type="hidden" name="day" value="<?= (int) $row['day'] ?>">
                                    <input type="hidden" name="participant_id" value="<?= (int) $row['participant_id'] ?>">
                                    <label>Valor a pagar<input type="text" class="money-input" name="amount" inputmode="decimal" value="<?= number_format($suggested, 2, '.', '') ?>" required aria-label="Valor a pagar"></label>
                                    <button class="btn small">Confirmar pagamento</button>
                                </form>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php
    render_footer();
}

function payout_save(): void {
    verify_csrf();
    require_client_admin();
    $cycle = current_cycle();
    if (!$cycle) { flash('error','Nenhum ciclo ativo.'); redirect('payouts'); }

    $day = (int)($_POST['day'] ?? 0);
    $pid = (int)($_POST['participant_id'] ?? 0);
    $amount = parse_money($_POST['amount'] ?? '');

    $stmt = db()->prepare('SELECT participant_id FROM cycle_points WHERE cycle_id=? AND day=? LIMIT 1');
    $stmt->execute([$cycle['id'], $day]);
    $assignedParticipant = (int)$stmt->fetchColumn();
    if ($day < 1 || $pid < 1 || $assignedParticipant !== $pid) {
        flash('error','Ponto ou participante inválido.'); redirect('payouts');
    }
    if (!is_finite($amount) || $amount <= 0) { flash('error','Informe um valor a pagar válido e maior que zero.'); redirect('payouts'); }
    $existing=db()->prepare('SELECT COUNT(*) FROM payouts WHERE cycle_id=? AND point_day=?');$existing->execute([$cycle['id'],$day]);
    if((int)$existing->fetchColumn()>0){flash('error','Este ponto já possui um pagamento registrado.');redirect('payouts');}
    if ($amount > cycle_cash((int)$cycle['id'])) {
        flash('error','Saldo disponível insuficiente para o valor informado.'); redirect('payouts');
    }

    automatic_cycle_backup($cycle,'antes-de-confirmar-pagamento');
    $stmt=db()->prepare("INSERT INTO payouts(cycle_id,point_day,participant_id,amount,status,paid_at,created_by) VALUES(?,?,?,?, 'paid',NOW(),?)");
    $stmt->execute([$cycle['id'],$day,$pid,$amount,$_SESSION['user']['id']]);
    $remaining=max(0,cycle_net_payout($cycle)-$amount);
    flash('success','Pagamento de '.money($amount).' confirmado.'.($remaining>0?' Restante previsto: '.money($remaining).'.':''));
    redirect('payouts');
}


function contribution_undo(): void {
    verify_csrf();
    $id=(int)($_POST['contribution_id']??0);
    $participantId=(int)($_POST['participant_id']??0);
    $cycle=current_cycle();
    $stmt=db()->prepare('SELECT id,amount FROM contributions WHERE id=? AND participant_id=? AND cycle_id=?');
    $stmt->execute([$id,$participantId,$cycle['id']??0]);
    $row=$stmt->fetch();
    if(!$row){ flash('error','Lançamento não encontrado.'); redirect('participant',['id'=>$participantId]); }
    automatic_cycle_backup($cycle,'antes-de-desfazer-aporte');
    db()->prepare('DELETE FROM contributions WHERE id=? AND cycle_id=?')->execute([$id,$cycle['id']]);
    flash('success','Lançamento de '.money($row['amount']).' desfeito com sucesso.');
    redirect('participant',['id'=>$participantId]);
}

function page_client_settings(): void {
    $cycle = current_cycle();
    $cycles = all_company_cycles();
    $admin = null;
    if ($cycle) {
        $stmt = db()->prepare("SELECT p.* FROM participants p JOIN cycle_participants cp ON cp.participant_id=p.id WHERE cp.cycle_id=? AND p.company_id=? AND p.name='Administração' LIMIT 1");
        $stmt->execute([(int)$cycle['id'], company_id()]);
        $admin = $stmt->fetch() ?: null;
    }
    render_header('Configurações'); ?>
    <div class="page-head"><div><h1>Configurações</h1><p>Dados e regras operacionais do seu caixa.</p></div></div>

    <section class="tool-section cycle-management">
      <div class="section-heading"><h2>Gerenciar caixas</h2><p>Crie, selecione, edite ou arquive os caixas da sua empresa.</p></div>
      <details class="panel cycle-create-panel"><summary><strong>+ Criar novo caixa</strong></summary>
        <form method="post" action="<?= url('cycle_create') ?>" class="cycle-form-grid">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <label>Nome<input name="name" required maxlength="160"></label>
          <label>Modalidade<select name="modality" required><option value="DIARIO">Diário</option></select></label>
          <label>Data de início<input type="date" name="start_date" value="<?= date('Y-m-d') ?>" required></label>
          <label>Valor do ponto<input class="money-input" name="daily_amount" inputmode="decimal" value="20" required></label>
          <label class="check-row"><input type="checkbox" name="admin_pays" value="1"> Administração é pagante</label>
          <label>Status<select name="status"><option value="active">Ativo</option></select></label>
          <label class="full-row">Observações<textarea name="notes" rows="3" maxlength="2000"></textarea></label>
          <div class="full-row"><button class="btn primary">Criar caixa</button></div>
        </form>
      </details>
      <div class="panel"><h3>Caixas existentes</h3><div class="table-wrap"><table><thead><tr><th>Nome</th><th>Modalidade</th><th>Status</th><th>Participantes</th><th>Criado em</th><th>Ações</th></tr></thead><tbody>
      <?php $management=cycle_management_service();foreach($cycles as $listed):$canDelete=$management->canDeleteCycle((int)$listed['id']);?><tr><td><strong><?= e($listed['name']) ?></strong><?php if((int)$listed['id']===(int)($cycle['id']??0)):?><small>Selecionado</small><?php endif;?></td><td><?= e(cycle_modality_label((string)($listed['modality']??'DIARIO'))) ?></td><td><span class="status <?= $listed['status']==='active'?'status-sent':'status-cancelled' ?>"><?= $listed['status']==='active'?'Ativo':'Arquivado' ?></span></td><td><?= (int)$listed['participant_count'] ?></td><td><?= date('d/m/Y',strtotime($listed['created_at'])) ?></td><td class="actions">
        <form method="post" action="<?= url('cycle_switch') ?>"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="cycle_id" value="<?= (int)$listed['id'] ?>"><button class="btn small"><?= $listed['status']==='active'?'Selecionar':'Selecionar para consulta' ?></button></form>
        <?php if($listed['status']==='active'):?><details><summary class="btn small">Editar</summary><form method="post" action="<?= url('cycle_update') ?>" class="cycle-edit-form"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="cycle_id" value="<?= (int)$listed['id'] ?>"><label>Nome<input name="name" value="<?= e($listed['name']) ?>" required></label><label>Data de início<input type="date" name="start_date" value="<?= e($listed['start_date']??'') ?>" required></label><label>Valor do ponto<input class="money-input" name="daily_amount" value="<?= e((string)$listed['daily_amount']) ?>" required></label><label class="check-row"><input type="checkbox" name="admin_pays" value="1" <?= cycle_admin_pays($listed)?'checked':'' ?>> Administração é pagante</label><label>Observações<textarea name="notes" rows="2"><?= e($listed['notes']??'') ?></textarea></label><button class="btn primary small">Salvar edição</button></form></details><form method="post" action="<?= url('cycle_archive') ?>" onsubmit="return confirm('Arquivar este caixa? Ele ficará disponível somente para consulta.');"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="cycle_id" value="<?= (int)$listed['id'] ?>"><button class="btn small">Arquivar</button></form><?php else:?><form method="post" action="<?= url('cycle_reactivate') ?>"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="cycle_id" value="<?= (int)$listed['id'] ?>"><button class="btn primary small">Reativar</button></form><?php endif;?>
        <?php if($canDelete):?><form method="post" action="<?= url('cycle_delete') ?>" onsubmit="return confirm('EXCLUSÃO DEFINITIVA: esta ação não pode ser desfeita. Excluir este caixa vazio?');"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="cycle_id" value="<?= (int)$listed['id'] ?>"><input type="hidden" name="confirmation" value="EXCLUIR"><button class="btn danger small">Excluir</button></form><?php endif;?>
      </td></tr><?php endforeach;?>
      </tbody></table></div></div>
    </section>

    <section class="tool-section" id="data">
      <div class="section-heading"><h2>1. Dados do caixa</h2><p>Seleção, identificação e período do caixa atual.</p></div>
      <div class="settings-grid">
        <div class="panel"><h3>Identificação</h3><?php if($cycle):?><form method="post" action="<?= url('cycle_name_update') ?>"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><label>Nome do caixa<input name="name" value="<?= e($cycle['name']) ?>" required></label><button class="btn primary">Salvar dados</button></form><?php else:?><div class="empty">Nenhum caixa selecionado.</div><?php endif;?></div>
        <div class="panel"><h3>Caixa atual</h3><?php if($cycles): ?><form method="post" action="<?= url('cycle_switch') ?>"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><label>Caixa<select name="cycle_id" required><?php foreach($cycles as $c):?><option value="<?= (int)$c['id'] ?>" <?= $cycle&&(int)$cycle['id']===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?> &mdash; <?= e(month_name((int)$c['month'])) ?>/<?= (int)$c['year'] ?></option><?php endforeach;?></select></label><button class="btn primary">Abrir caixa</button></form><?php else:?><div class="empty">Nenhum caixa cadastrado.</div><?php endif;?></div>
        <div class="panel"><h3>Período</h3><?php if($cycle):?><form method="post" action="<?= url('cycle_period_update') ?>"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><label>Mês<select name="month" required><?php for($m=1;$m<=12;$m++):?><option value="<?= $m ?>" <?= (int)$cycle['month']===$m?'selected':'' ?>><?= e(month_name($m)) ?></option><?php endfor;?></select></label><label>Ano<input type="number" name="year" min="2020" max="2100" value="<?= (int)$cycle['year'] ?>" required></label><button class="btn primary">Salvar período</button></form><?php else:?><div class="empty">Nenhum caixa selecionado.</div><?php endif;?></div>
      </div>
    </section>

    <section class="tool-section" id="rules">
      <div class="section-heading"><h2>2. Regras do caixa</h2><p>Valor diário e regra da Administração pagante.</p></div>
      <div class="panel narrow"><?php if($cycle):?><form method="post" action="<?= url('cycle_settings_update') ?>"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><label>Valor diário<input class="money-input" name="daily_amount" inputmode="decimal" value="<?= e((string)$cycle['daily_amount']) ?>" required></label><label class="check-row"><input type="checkbox" name="admin_pays" value="1" <?= cycle_admin_pays($cycle)?'checked':'' ?>> Administração é pagante</label><small>Quando marcada, a Administração aparece entre os pagantes e pode registrar aportes.</small><p><button class="btn primary">Salvar regras</button></p></form><?php else:?><div class="empty">Nenhum caixa selecionado.</div><?php endif;?></div>
    </section>

    <section class="tool-section" id="pix">
      <div class="section-heading"><h2>3. PIX</h2><p>Chave de recebimento da Administração deste caixa.</p></div>
      <div class="panel narrow"><?php if($admin):?><form method="post" action="<?= url('admin_pix_update') ?>"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><label>Tipo da chave PIX<select name="pix_type"><option value="">Selecione</option><?php foreach(['CPF','CNPJ','Celular','E-mail','Chave aleatória'] as $type):?><option value="<?= e($type) ?>" <?= ($admin['pix_type']??'')===$type?'selected':'' ?>><?= e($type) ?></option><?php endforeach;?></select></label><label>Chave PIX<input name="pix_key" value="<?= e($admin['pix_key']??'') ?>" autocomplete="off"></label><button class="btn primary">Salvar PIX</button></form><?php else:?><div class="empty">A Administração não está vinculada ao caixa atual.</div><?php endif;?></div>
    </section>

    <section class="tool-section" id="password">
      <div class="section-heading"><h2>4. Alterar senha</h2><p>Atualize somente a senha do seu próprio usuário.</p></div>
      <div class="panel narrow"><form method="post" action="<?= url('password_update') ?>"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><label>Senha atual<input type="password" name="current_password" autocomplete="current-password" required></label><label>Nova senha<input type="password" name="new_password" autocomplete="new-password" minlength="8" required></label><label>Confirmar nova senha<input type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required></label><button class="btn primary">Alterar senha</button></form></div>
    </section>
    <?php if(($_SESSION['user']['role']??'')==='client_admin'):?><section class="tool-section" id="administrators"><div class="section-heading"><h2>Administradores do ambiente</h2><p>Cadastre administradores que compartilharão esta mesma empresa e os mesmos caixas.</p></div><div class="panel narrow"><p>Esta é uma solução temporária e não cria isolamento entre administradores.</p><a class="btn primary" href="<?= url('administrators') ?>">Gerenciar administradores</a></div></section><?php endif; ?>
    <?php render_footer();
}

function page_client_administrators(): void {
    require_client_admin();$admins=client_administrator_service()->all();$actor=(int)($_SESSION['user']['id']??0);render_header('Administradores'); ?>
    <div class="page-head"><div><span class="eyebrow">Configurações</span><h1>Administradores do ambiente</h1><p>Todos os administradores abaixo acessam a mesma empresa e os mesmos caixas.</p></div><a class="btn" href="<?= url('settings') ?>">Voltar</a></div>
    <section class="panel"><h2>Novo administrador</h2><form method="post" action="<?= url('administrator_create') ?>" class="administrator-form-grid"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><label>Nome completo<input name="name" maxlength="120" required autocomplete="name"></label><label>E-mail<input type="email" name="email" maxlength="160" required autocomplete="email"></label><label>Senha<input type="password" name="password" minlength="12" required autocomplete="new-password"></label><label>Confirmar senha<input type="password" name="password_confirmation" minlength="12" required autocomplete="new-password"></label><label class="check-row"><input type="checkbox" name="active" value="1" checked> Usuário ativo</label><div><button class="btn primary">Cadastrar administrador</button></div></form></section>
    <section class="panel"><h2>Administradores cadastrados</h2><div class="administrator-grid"><?php foreach($admins as$a):$own=(int)$a['id']===$actor;?><article class="administrator-card"><div class="panel-title"><div><strong><?= e($a['name']) ?></strong><small><?= e($a['email']) ?></small></div><span class="status <?= $a['active']?'status-sent':'status-cancelled' ?>"><?= $a['active']?'Ativo':'Inativo' ?></span></div><dl><dt>Criado em</dt><dd><?= date('d/m/Y H:i',strtotime($a['created_at'])) ?></dd><dt>Último acesso</dt><dd>Não disponível nesta versão</dd></dl>
      <details><summary class="btn small">Editar nome</summary><form method="post" action="<?= url('administrator_name_update') ?>"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><label>Nome completo<input name="name" value="<?= e($a['name']) ?>" maxlength="120" required></label><button class="btn primary small">Salvar nome</button></form></details>
      <?php if(!$own):?><details><summary class="btn small">Redefinir senha</summary><form method="post" action="<?= url('administrator_password_reset') ?>"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><label>Nova senha<input type="password" name="password" minlength="12" required autocomplete="new-password"></label><label>Confirmar senha<input type="password" name="password_confirmation" minlength="12" required autocomplete="new-password"></label><button class="btn primary small">Redefinir senha</button></form></details><?php endif; ?>
      <?php if(!$own):?><form method="post" action="<?= url('administrator_status') ?>"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><input type="hidden" name="active" value="<?= $a['active']?'0':'1' ?>"><button class="btn <?= $a['active']?'danger':'primary' ?> small"><?= $a['active']?'Inativar':'Reativar' ?></button></form><?php else:?><small>Seu próprio acesso não pode ser inativado por esta tela.</small><?php endif; ?></article><?php endforeach;?></div></section>
    <?php render_footer();
}

function administrator_create(): void { verify_csrf();require_client_admin();try{client_administrator_service()->create($_POST);flash('success','Administrador cadastrado na empresa atual.');}catch(Throwable$e){flash('error',$e->getMessage());}redirect('administrators'); }
function administrator_name_update(): void { verify_csrf();require_client_admin();try{client_administrator_service()->updateName((int)($_POST['id']??0),(string)($_POST['name']??''));flash('success','Nome do administrador atualizado.');}catch(Throwable$e){flash('error',$e->getMessage());}redirect('administrators'); }
function administrator_status(): void { verify_csrf();require_client_admin();try{client_administrator_service()->setActive((int)($_POST['id']??0),(string)($_POST['active']??'0')==='1');flash('success','Status do administrador atualizado.');}catch(Throwable$e){flash('error',$e->getMessage());}redirect('administrators'); }
function administrator_password_reset(): void { verify_csrf();require_client_admin();try{client_administrator_service()->resetPassword((int)($_POST['id']??0),(string)($_POST['password']??''),(string)($_POST['password_confirmation']??''));flash('success','Senha do administrador redefinida.');}catch(Throwable$e){flash('error',$e->getMessage());}redirect('administrators'); }

function password_update(): void {
    verify_csrf();
    $userId = (int)($_SESSION['user']['id'] ?? 0);
    $companyId = company_id();
    $current = (string)($_POST['current_password'] ?? '');
    $new = (string)($_POST['new_password'] ?? '');
    $confirmation = (string)($_POST['password_confirmation'] ?? '');
    $stmt = db()->prepare("SELECT password FROM users WHERE id=? AND company_id=? AND role IN('client_admin','operator') AND active=1 LIMIT 1");
    $stmt->execute([$userId,$companyId]);
    $hash = $stmt->fetchColumn();
    if (!$hash || !password_verify($current,(string)$hash)) { flash('error','A senha atual está incorreta.'); redirect('settings',['section'=>'password']); }
    if (strlen($new) < 8) { flash('error','A nova senha deve ter pelo menos 8 caracteres.'); redirect('settings',['section'=>'password']); }
    if ($new !== $confirmation) { flash('error','A confirmação da nova senha não confere.'); redirect('settings',['section'=>'password']); }
    db()->prepare('UPDATE users SET password=? WHERE id=? AND company_id=?')->execute([password_hash($new,PASSWORD_DEFAULT),$userId,$companyId]);
    flash('success','Senha alterada com sucesso.');
    redirect('settings',['section'=>'password']);
}

function page_developer_tools(): void {
    render_header('Ferramentas'); ?>
    <div class="page-head"><div><h1>Ferramentas</h1><p>Gerencie o caixa selecionado, backups e outros caixas em funcionamento.</p></div></div>
    <?php $cycle = current_cycle(); $cycles=all_active_cycles(); ?>

    <section class="tool-section">
      <div class="section-heading"><h2>Caixa atual</h2><p>Configurações exclusivas do caixa selecionado.</p></div>
      <div class="split">
        <div class="panel"><h3>Selecionar caixa</h3><p>Alterne entre caixas sem misturar participantes, pontos ou movimentações.</p>
          <?php if($cycles): ?>
          <form method="post" action="<?= url('cycle_switch') ?>"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><label>Caixa<select name="cycle_id" required><?php foreach($cycles as $c):?><option value="<?= (int)$c['id'] ?>" <?= $cycle&&(int)$cycle['id']===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?> — <?= e(month_name((int)$c['month'])) ?>/<?= (int)$c['year'] ?></option><?php endforeach;?></select></label><button class="btn primary">Abrir caixa</button></form>
          <?php else: ?><div class="empty">Nenhum caixa cadastrado. Crie um novo caixa abaixo.</div><?php endif; ?>
        </div>

        <div class="panel"><h3>Regras e identificação</h3><?php if($cycle):?><form method="post" action="<?= url('cycle_settings_update') ?>"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><label>Nome do caixa<input name="name" value="<?= e($cycle['name']) ?>" required></label><label class="check-row"><input type="checkbox" name="admin_pays" value="1" <?= cycle_admin_pays($cycle)?'checked':'' ?>> Administração é pagante</label><small>Quando marcada, a Administração aparece entre os pagantes e pode registrar aportes. Quando desmarcada, permanece somente no ponto 1.</small><button class="btn primary">Salvar regras</button></form><?php else:?><div class="empty">Selecione ou crie um caixa.</div><?php endif;?></div>

        <div class="panel"><h3>Período do caixa</h3><p>O calendário e os cálculos respeitam o mês e o ano escolhidos.</p>
          <?php if ($cycle): ?>
          <form method="post" action="<?= url('cycle_period_update') ?>">
            <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
            <label>Mês<select name="month" required><?php for ($m=1; $m<=12; $m++): ?><option value="<?= $m ?>" <?= (int)$cycle['month']===$m?'selected':'' ?>><?= e(month_name($m)) ?></option><?php endfor; ?></select></label>
            <label>Ano<input type="number" name="year" min="2020" max="2100" value="<?= (int)$cycle['year'] ?>" required></label>
            <button class="btn primary">Salvar período</button>
          </form>
          <?php else: ?><div class="empty">Nenhum caixa selecionado.</div><?php endif; ?>
        </div>
      </div>
    </section>

    <section class="tool-section">
      <div class="section-heading"><h2>Identidade visual da GDA</h2><p>Configure a logo exibida no topo e na tela de acesso.</p></div>
      <div class="panel identity-panel">
        <div class="logo-preview"><?php $configuredLogo=gda_logo_url(); if($configuredLogo):?><img src="<?= e($configuredLogo) ?>" alt="Logo GDA atual"><?php else:?><span class="brand-monogram large">GDA</span><?php endif;?></div>
        <form method="post" action="<?= url('branding_update') ?>" enctype="multipart/form-data">
          <input type="hidden" name="csrf" value="<?= csrf_token() ?>">
          <label>Logo da GDA<input type="file" name="logo" accept="image/png,image/jpeg,image/webp"></label>
          <small>Formatos aceitos: PNG, JPG e WebP. Recomendado: fundo transparente e formato horizontal.</small>
          <?php if($configuredLogo):?><label class="check-row"><input type="checkbox" name="remove_logo" value="1"> Remover a logo atual e voltar para a sigla GDA</label><?php endif;?>
          <button class="btn primary">Salvar identidade visual</button>
        </form>
      </div>
    </section>

    <section class="tool-section">
      <div class="section-heading"><h2>Backup</h2><p>Proteja e restaure somente os dados do caixa selecionado.</p></div>
      <div class="split">
        <div class="panel"><h3>Backup do caixa</h3><p>Salva uma cópia local em <code>storage/backups</code>, separada por caixa.</p><?php if($cycle): $backupFiles=list_cycle_backups($cycle); ?><form method="post" action="<?= url('backup_create') ?>"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><button class="btn primary">Fazer backup agora</button></form><p><a class="btn" href="<?= url('backup_export') ?>">Baixar uma cópia</a></p><?php else:?><div class="empty">Nenhum caixa selecionado.</div><?php endif;?></div>
        <div class="panel"><h3>Histórico de backups</h3><?php if($cycle):?><?php if($backupFiles):?><div class="backup-list"><?php foreach(array_slice($backupFiles,0,10) as $file):?><form method="post" action="<?= url('backup_restore') ?>" onsubmit="return confirm('Restaurar este backup? O estado atual será salvo automaticamente antes.');"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="filename" value="<?= e(basename($file)) ?>"><span><strong><?= e(basename($file)) ?></strong><small><?= date('d/m/Y H:i:s',filemtime($file)) ?></small></span><button class="btn small">Restaurar</button></form><?php endforeach;?></div><?php else:?><small>Nenhum backup local deste caixa.</small><?php endif;?><?php else:?><div class="empty">Nenhum caixa selecionado.</div><?php endif;?></div>
      </div>
    </section>

    <section class="tool-section">
      <div class="section-heading"><h2>Administração</h2><p>Crie novos caixas ou encerre definitivamente o caixa selecionado.</p></div>
      <div class="split">
        <div class="panel"><h3>Novo caixa</h3><p>Crie outro caixa para um novo grupo ou período.</p><form method="post" action="<?= url('cycle_create') ?>"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><label>Nome do caixa<input name="name" placeholder="Ex.: Caixa Turma 2" required></label><label>Mês<select name="month"><?php for($m=1;$m<=12;$m++):?><option value="<?= $m ?>" <?= $m===(int)date('n')?'selected':'' ?>><?= e(month_name($m)) ?></option><?php endfor;?></select></label><label>Ano<input type="number" name="year" min="2020" max="2100" value="<?= date('Y') ?>" required></label><label>Valor da diária<input class="money-input" name="daily_amount" inputmode="decimal" value="20"></label><label class="check-row"><input type="checkbox" name="admin_pays" value="1"> Administração é pagante neste caixa</label><button class="btn primary">Criar e abrir caixa</button></form></div>
        <div class="panel danger-zone"><h3>Excluir caixa</h3><?php if($cycle):?><p>Exclui somente <strong><?= e($cycle['name']) ?></strong>, incluindo seus aportes, pontos e pagamentos. Os demais caixas não serão alterados.</p><p>Um backup automático será criado antes da exclusão.</p><form method="post" action="<?= url('cycle_delete') ?>" onsubmit="return confirm('ATENÇÃO: excluir definitivamente o caixa selecionado?');"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="cycle_id" value="<?= (int)$cycle['id'] ?>"><label>Digite <strong>EXCLUIR</strong> para confirmar<input name="confirmation" required autocomplete="off"></label><button class="btn danger">Excluir caixa selecionado</button></form><?php else:?><div class="empty">Nenhum caixa selecionado.</div><?php endif;?></div>
      </div>
    </section>
    <?php render_footer();
}


function branding_update(): void {
    verify_csrf();
    $uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'uploads';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        flash('error','Não foi possível criar a pasta para a logo.'); redirect('developer_tools');
    }
    $current = gda_logo_url();
    if (isset($_POST['remove_logo'])) {
        if ($current) { $file=dirname(__DIR__).DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$current); if(is_file($file)) @unlink($file); }
        set_app_setting('gda_logo_path', null);
        flash('success','Logo removida. A sigla GDA voltou a ser exibida.'); redirect('developer_tools');
    }
    if (empty($_FILES['logo']['tmp_name'])) {
        flash('error','Selecione uma imagem para a logo.'); redirect('developer_tools');
    }
    if (($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($_FILES['logo']['size'] ?? 0) > 3 * 1024 * 1024) {
        flash('error','A logo deve ter no máximo 3 MB.'); redirect('developer_tools');
    }
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($_FILES['logo']['tmp_name']);
    $allowed=['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'];
    if (!isset($allowed[$mime]) || @getimagesize($_FILES['logo']['tmp_name'])===false) {
        flash('error','Envie uma imagem PNG, JPG ou WebP válida.'); redirect('developer_tools');
    }
    $relative='public/uploads/gda-logo.'.$allowed[$mime];
    $destination=dirname(__DIR__).DIRECTORY_SEPARATOR.str_replace('/',DIRECTORY_SEPARATOR,$relative);
    foreach(glob($uploadDir.DIRECTORY_SEPARATOR.'gda-logo.*') ?: [] as $old) @unlink($old);
    if (!move_uploaded_file($_FILES['logo']['tmp_name'],$destination)) {
        flash('error','Não foi possível salvar a logo.'); redirect('developer_tools');
    }
    set_app_setting('gda_logo_path',$relative);
    flash('success','Logo da GDA atualizada com sucesso.'); redirect('developer_tools');
}

function cycle_switch(): void {
    verify_csrf(); $id=(int)($_POST['cycle_id']??0);
    $stmt=db()->prepare("SELECT id FROM cycles WHERE id=? AND company_id=? AND status IN('active','archived','closed')");$stmt->execute([$id,company_id()]);
    if(!$stmt->fetchColumn()){flash('error','Caixa não encontrado.');redirect('settings');}
    $_SESSION['current_cycle_id']=$id;flash('success','Caixa selecionado.');redirect('dashboard');
}

function cycle_create(): void {
    verify_csrf();$name=trim((string)($_POST['name']??''));$modality=(string)($_POST['modality']??'');$startDate=(string)($_POST['start_date']??'');$daily=parse_money($_POST['daily_amount']??0);$adminPays=isset($_POST['admin_pays'])?1:0;$notes=trim((string)($_POST['notes']??''));
    $date=DateTimeImmutable::createFromFormat('!Y-m-d',$startDate);$validDate=$date&&$date->format('Y-m-d')===$startDate;
    if($name===''||$modality!=='DIARIO'||!$validDate||$daily<=0){flash('error','Preencha corretamente os dados do novo caixa.');redirect('settings');}
    $duplicate=db()->prepare("SELECT COUNT(*) FROM cycles WHERE company_id=? AND LOWER(name)=LOWER(?)");$duplicate->execute([company_id(),$name]);
    if((int)$duplicate->fetchColumn()>0){flash('error','Já existe um caixa com este nome na empresa.');redirect('settings');}
    $year=(int)$date->format('Y');$month=(int)$date->format('n');
    $pdo=db();$pdo->beginTransaction();try{
        $service=new \Gda\Modules\Caixas\Services\CycleService(new \Gda\Modules\Caixas\Repositories\CycleRepository($pdo,client_context()));
        $cycleId=$service->create($pdo,client_context(),$name,$year,$month,$daily,(bool)$adminPays,$startDate,$notes?:null);
        $stmt=$pdo->prepare("SELECT id FROM participants WHERE company_id=? AND name='Administração' ORDER BY id LIMIT 1");$stmt->execute([company_id()]);$adminId=(int)$stmt->fetchColumn();
        if(!$adminId){$stmt=$pdo->prepare("INSERT INTO participants(company_id,name,active,notes) VALUES(?,'Administração',1,'Responsável pela administração do caixa')");$stmt->execute([company_id()]);$adminId=(int)$pdo->lastInsertId();}
        $pdo->prepare('INSERT IGNORE INTO cycle_participants(cycle_id,participant_id) VALUES(?,?)')->execute([$cycleId,$adminId]);
        $pdo->prepare('INSERT INTO cycle_points(cycle_id,day,participant_id) VALUES(?,?,?)')->execute([$cycleId,1,$adminId]);
        $pdo->commit();$_SESSION['current_cycle_id']=$cycleId;flash('success','Caixa criado com sucesso.');redirect('settings');
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}

function cycle_name_update(): void {
    verify_csrf();
    $cycle=current_cycle();
    $name=trim((string)($_POST['name']??''));
    if(!$cycle||$name===''){flash('error','Informe um nome válido para o caixa.');redirect('settings');}
    db()->prepare('UPDATE cycles SET name=? WHERE id=? AND company_id=?')->execute([$name,$cycle['id'],company_id()]);
    flash('success','Dados do caixa atualizados.');redirect('settings');
}

function cycle_update(): void {
    verify_csrf();
    $id=(int)($_POST['cycle_id']??0);$name=trim((string)($_POST['name']??''));$startDate=(string)($_POST['start_date']??'');$daily=parse_money($_POST['daily_amount']??0);$adminPays=isset($_POST['admin_pays'])?1:0;$notes=trim((string)($_POST['notes']??''));
    $date=DateTimeImmutable::createFromFormat('!Y-m-d',$startDate);$validDate=$date&&$date->format('Y-m-d')===$startDate;
    if($id<1||$name===''||!$validDate||$daily<=0){flash('error','Dados inválidos para edição do caixa.');redirect('settings');}
    $check=db()->prepare('SELECT COUNT(*) FROM cycles WHERE company_id=? AND LOWER(name)=LOWER(?) AND id<>?');$check->execute([company_id(),$name,$id]);
    if((int)$check->fetchColumn()>0){flash('error','Já existe um caixa com este nome na empresa.');redirect('settings');}
    $stmt=db()->prepare("UPDATE cycles SET name=?,start_date=?,daily_amount=?,admin_pays=?,notes=? WHERE id=? AND company_id=? AND status IN('active','archived')");
    $stmt->execute([$name,$startDate,$daily,$adminPays,$notes?:null,$id,company_id()]);
    if($stmt->rowCount()===0){$owned=db()->prepare('SELECT COUNT(*) FROM cycles WHERE id=? AND company_id=?');$owned->execute([$id,company_id()]);if(!(int)$owned->fetchColumn()){http_response_code(404);exit('Caixa não encontrado.');}}
    flash('success','Caixa atualizado com sucesso.');redirect('settings');
}

function cycle_archive(): void {
    verify_csrf();require_client_admin();$id=(int)($_POST['cycle_id']??0);
    if(!cycle_management_service()->archiveCycle($id)){flash('error','Caixa não encontrado ou já arquivado.');redirect('settings');}
    if((int)($_SESSION['current_cycle_id']??0)===$id)unset($_SESSION['current_cycle_id']);
    flash('success','Caixa arquivado. O histórico permanece disponível para consulta.');redirect('settings');
}

function cycle_reactivate(): void {
    verify_csrf();require_client_admin();$id=(int)($_POST['cycle_id']??0);
    if(!cycle_management_service()->reactivateCycle($id)){flash('error','Caixa não encontrado ou já está ativo.');redirect('settings');}
    flash('success','Caixa reativado com sucesso.');redirect('settings');
}

function admin_pix_update(): void {
    verify_csrf();
    $cycle=current_cycle();
    if(!$cycle){flash('error','Nenhum caixa selecionado.');redirect('settings');}
    $stmt=db()->prepare("SELECT p.id FROM participants p JOIN cycle_participants cp ON cp.participant_id=p.id WHERE cp.cycle_id=? AND p.company_id=? AND p.name='Administração' LIMIT 1");
    $stmt->execute([(int)$cycle['id'],company_id()]);
    $adminId=(int)$stmt->fetchColumn();
    if(!$adminId){flash('error','A Administração não está vinculada ao caixa atual.');redirect('settings');}
    $pixType=trim((string)($_POST['pix_type']??''));
    $pixKey=trim((string)($_POST['pix_key']??''));
    $allowed=['','CPF','CNPJ','Celular','E-mail','Chave aleatória'];
    if(!in_array($pixType,$allowed,true)){flash('error','Tipo de chave PIX inválido.');redirect('settings');}
    db()->prepare('UPDATE participants SET pix_type=?,pix_key=? WHERE id=? AND company_id=?')->execute([$pixType?:null,$pixKey?:null,$adminId,company_id()]);
    flash('success','PIX atualizado com sucesso.');redirect('settings');
}

function participant_link_action_context(): array {
    require_client_admin();$cycle=current_cycle();$participantId=(int)($_POST['participant_id']??0);
    if(!$cycle||$participantId<1||is_administration((int)$cycle['id'],$participantId)){throw new RuntimeException('Participante inválido para link individual.');}
    $link=participant_access_service()->ownedLink(company_id(),(int)$cycle['id'],$participantId);
    if(!$link)throw new RuntimeException('Participante não pertence ao caixa selecionado.');
    return[$cycle,$participantId,$link];
}
function participant_link_generate(): void {
    verify_csrf();try{[$cycle,$participantId]=participant_link_action_context();$token=participant_access_service()->generate(company_id(),(int)$cycle['id'],$participantId);$_SESSION['new_participant_links'][$cycle['id'].':'.$participantId]=$token;flash('success','Link individual criado. Copie-o agora.');}catch(Throwable$e){flash('error',$e->getMessage());}redirect('participant',['id'=>(int)($_POST['participant_id']??0)]);
}
function participant_link_rotate(): void {
    verify_csrf();try{[$cycle,$participantId]=participant_link_action_context();$token=participant_access_service()->generate(company_id(),(int)$cycle['id'],$participantId);$_SESSION['new_participant_links'][$cycle['id'].':'.$participantId]=$token;flash('success','Link regenerado. O endereço anterior foi invalidado.');}catch(Throwable$e){flash('error',$e->getMessage());}redirect('participant',['id'=>(int)($_POST['participant_id']??0)]);
}
function participant_link_disable(): void {
    verify_csrf();try{[$cycle,$participantId]=participant_link_action_context();participant_access_service()->setEnabled(company_id(),(int)$cycle['id'],$participantId,false);flash('success','Link desativado sem apagar o histórico.');}catch(Throwable$e){flash('error',$e->getMessage());}redirect('participant',['id'=>(int)($_POST['participant_id']??0)]);
}
function participant_link_enable(): void {
    verify_csrf();try{[$cycle,$participantId]=participant_link_action_context();participant_access_service()->setEnabled(company_id(),(int)$cycle['id'],$participantId,true);flash('success','Link atual reativado.');}catch(Throwable$e){flash('error',$e->getMessage());}redirect('participant',['id'=>(int)($_POST['participant_id']??0)]);
}

function cycle_settings_update(): void {
    verify_csrf();
    $cycle=current_cycle();
    if(!$cycle){flash('error','Nenhum caixa selecionado.');redirect('settings');}
    $daily=parse_money($_POST['daily_amount']??0);
    $adminPays=isset($_POST['admin_pays'])?1:0;
    if($daily<=0){flash('error','Informe um valor diário válido.');redirect('settings');}
    automatic_cycle_backup($cycle,'antes-de-alterar-regras');
    db()->prepare('UPDATE cycles SET daily_amount=?,admin_pays=? WHERE id=? AND company_id=?')->execute([$daily,$adminPays,$cycle['id'],company_id()]);
    flash('success','Regras do caixa atualizadas.');redirect('settings');
}

function cycle_period_update(): void {
    verify_csrf();
    $cycle = current_cycle();
    if (!$cycle) { flash('error','Nenhum ciclo ativo.'); redirect('developer_tools'); }

    $month = (int)($_POST['month'] ?? 0);
    $year = (int)($_POST['year'] ?? 0);
    if ($month < 1 || $month > 12 || $year < 2020 || $year > 2100) {
        flash('error','Mês ou ano inválido.');
        redirect('settings',['section'=>'data']);
    }

    $newDays = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $stmt = db()->prepare('SELECT day FROM cycle_points WHERE cycle_id=? AND day>? AND participant_id IS NOT NULL ORDER BY day');
    $stmt->execute([$cycle['id'], $newDays]);
    $invalidPointDays = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt = db()->prepare('SELECT point_day FROM payouts WHERE cycle_id=? AND point_day>? ORDER BY point_day');
    $stmt->execute([$cycle['id'], $newDays]);
    $invalidPayoutDays = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if ($invalidPointDays || $invalidPayoutDays) {
        $days = array_values(array_unique(array_map('intval', array_merge($invalidPointDays, $invalidPayoutDays))));
        flash('error','Não foi possível alterar: existem pontos ou pagamentos nos dias '.implode(', ', $days).', que não existem no mês escolhido. Reorganize esses dias primeiro.');
        redirect('settings',['section'=>'data']);
    }

    automatic_cycle_backup($cycle,'antes-de-alterar-mes');
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM cycle_points WHERE cycle_id=? AND day>? AND participant_id IS NULL')->execute([$cycle['id'], $newDays]);
        $pdo->prepare('UPDATE cycles SET month=?, year=? WHERE id=? AND company_id=?')->execute([$month,$year,$cycle['id'],company_id()]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    flash('success','Mês do caixa alterado para '.month_name($month).' de '.$year.'.');
    redirect('settings',['section'=>'data']);
}

function cycle_delete_empty(): void {
    verify_csrf();require_client_admin();$cycleId=(int)($_POST['cycle_id']??0);
    if(strtoupper(trim((string)($_POST['confirmation']??'')))!=='EXCLUIR'){flash('error','Confirmação inválida.');redirect('settings');}
    try{$next=cycle_management_service()->deleteEmptyCycle($cycleId);}
    catch(RuntimeException $e){if($e->getMessage()==='not_found'){http_response_code(404);exit('Caixa não encontrado.');}if($e->getMessage()==='has_history'){http_response_code(409);exit('Este caixa já possui movimentações e não pode ser excluído. Arquive-o para preservar o histórico.');}throw$e;}
    if((int)($_SESSION['current_cycle_id']??0)===$cycleId){if($next)$_SESSION['current_cycle_id']=$next;else unset($_SESSION['current_cycle_id']);}
    flash('success','Caixa vazio excluído definitivamente.');redirect('settings');
}

function backup_create(): void {
    verify_csrf();$cycle=current_cycle();if(!$cycle){flash('error','Nenhum caixa selecionado.');redirect('developer_tools');}
    $file=save_cycle_backup((int)$cycle['id'],'manual');flash('success','Backup salvo em storage/backups/'.basename(dirname($file)).'/'.basename($file));redirect('developer_tools');
}

function backup_restore(): void {
    verify_csrf();$cycle=current_cycle();if(!$cycle){flash('error','Nenhum caixa selecionado.');redirect('developer_tools');}
    $filename=basename((string)($_POST['filename']??''));$file=cycle_backup_directory($cycle).DIRECTORY_SEPARATOR.$filename;
    try{save_cycle_backup((int)$cycle['id'],'antes-de-restaurar');restore_cycle_backup_file($file,(int)$cycle['id']);flash('success','Backup restaurado com sucesso.');}
    catch(Throwable $e){flash('error','Não foi possível restaurar: '.$e->getMessage());}
    redirect('developer_tools');
}

function backup_export(): void {
    require_client_context();ensure_app_schema();$cycle=current_cycle();if(!$cycle){http_response_code(404);exit('Nenhum caixa selecionado.');}
    $data=build_cycle_backup((int)$cycle['id'],'download-manual');
    $filename=safe_backup_slug($cycle['name']).'-backup-'.date('Y-m-d-His').'.json';
    header('Content-Type: application/json; charset=utf-8');header('Content-Disposition: attachment; filename="'.$filename.'"');
    echo json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;
}


function page_participant_portal(): void {
    header('Cache-Control: no-store, private, max-age=0');header('Pragma: no-cache');header('Referrer-Policy: no-referrer');header('X-Content-Type-Options: nosniff');header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https://quickchart.io; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; form-action 'self'; frame-ancestors 'self'");
    $token=trim((string)($_GET['access_token']??''));$link=participant_access_service()->resolve($token);
    if(!$link){http_response_code(404);$saved=$_SESSION['user']??null;unset($_SESSION['user']);render_header('Link indisponível');if($saved)$_SESSION['user']=$saved;echo '<section class="login-card"><h1>Link indisponível</h1><p>Este link é inválido, foi desativado ou não está mais disponível. Solicite um novo endereço à administração do seu caixa.</p></section>';render_footer();return;}
    $cycleId=(int)$link['cycle_id'];$participantId=(int)$link['participant_id'];$cycle=['id'=>$cycleId,'company_id'=>(int)$link['company_id'],'name'=>$link['cycle_name'],'modality'=>$link['modality'],'start_date'=>$link['start_date'],'year'=>$link['year'],'month'=>$link['month'],'daily_amount'=>$link['daily_amount'],'admin_pays'=>$link['admin_pays'],'status'=>$link['cycle_status']];
    $pointStmt=db()->prepare('SELECT day FROM cycle_points WHERE cycle_id=? AND participant_id=? ORDER BY day');$pointStmt->execute([$cycleId,$participantId]);$pointDays=$pointStmt->fetchAll(PDO::FETCH_COLUMN);$pointCount=count($pointDays);$dailyDue=$pointCount*(float)$cycle['daily_amount'];
    $paid=participant_total_paid($cycleId,$participantId);$days=days_in_cycle($cycle);$daysPaid=$dailyDue>0?(int)floor($paid/$dailyDue):0;$pendingDays=max(0,$days-$daysPaid);
    $hist=db()->prepare('SELECT amount,paid_at,notes FROM contributions WHERE cycle_id=? AND participant_id=? ORDER BY paid_at DESC,id DESC LIMIT 12');$hist->execute([$cycleId,$participantId]);$history=$hist->fetchAll();
    $paymentPix=administration_for_cycle($cycleId,(int)$link['company_id']);$expectedReceive=$pointCount*cycle_net_payout($cycle);$receivedStmt=db()->prepare("SELECT COALESCE(SUM(amount),0) FROM payouts WHERE cycle_id=? AND participant_id=? AND status='paid'");$receivedStmt->execute([$cycleId,$participantId]);$received=(float)$receivedStmt->fetchColumn();$receiveRemaining=max(0,$expectedReceive-$received);
    $receiptStmt=db()->prepare('SELECT stored_name,original_name,amount,status,created_at FROM payment_receipts WHERE cycle_id=? AND participant_id=? ORDER BY created_at DESC');$receiptStmt->execute([$cycleId,$participantId]);$receipts=$receiptStmt->fetchAll();
    $messageStmt=db()->prepare('SELECT message_text,status,created_at FROM message_queue WHERE cycle_id=? AND participant_id=? ORDER BY created_at DESC LIMIT 10');$messageStmt->execute([$cycleId,$participantId]);$messages=$messageStmt->fetchAll();
    $saved=$_SESSION['user']??null;unset($_SESSION['user']);render_header('Meu Caixa');if($saved)$_SESSION['user']=$saved; ?>
    <div class="client-shell">
      <header class="client-hero"><div><small><?= e($link['company_name']) ?> · GDA Caixas</small><h1>Olá, <?= e($link['participant_name']) ?>!</h1><p><?= e($cycle['name']) ?> · <?= e(cycle_modality_label($cycle['modality'])) ?> · <?= e(month_name((int)$cycle['month'])) ?> de <?= e($cycle['year']) ?></p></div><span class="client-badge"><?= $cycle['status']==='archived'?'Caixa arquivado':'Área do participante' ?></span></header>
      <section class="client-summary">
        <article><span>Contribuição diária</span><strong><?= money($dailyDue) ?></strong></article>
        <article><span>Total pago</span><strong><?= money($paid) ?></strong></article>
        <article><span>Dias quitados</span><strong><?= $daysPaid ?> de <?= $days ?></strong></article>
        <article><span>Dias pendentes</span><strong><?= $pendingDays ?></strong></article>
      </section>
      <section class="client-card receive-card"><div><span>Seu(s) ponto(s)</span><strong><?= $pointDays?implode(', ',array_map(fn($d)=>sprintf('%02d/%02d',$d,$cycle['month']),$pointDays)):'Ainda não atribuído' ?></strong></div></section>
      <section class="client-summary"><article><span>Valor previsto para receber</span><strong><?= money($expectedReceive) ?></strong></article><article><span>Valor já recebido</span><strong><?= money($received) ?></strong></article><article><span>Restante</span><strong><?= money($receiveRemaining) ?></strong></article><article><span>Valor por ponto/dia</span><strong><?= money($cycle['daily_amount']) ?></strong></article></section>
      <?php if(!empty($paymentPix['pix_key'])):?><section class="client-card pix-card"><div><span>Chave PIX da Administração para pagamento</span><strong id="client-pix-key"><?= e($paymentPix['pix_key']) ?></strong><small><?= e($paymentPix['pix_type']?:'PIX') ?></small></div><button type="button" class="btn primary" onclick="copyPixKey('client-pix-key')">Copiar PIX</button></section><?php else:?><section class="client-card pix-card"><p class="client-empty">A chave PIX da Administração ainda não foi cadastrada.</p></section><?php endif;?>
      <?php if($dailyDue>0):?><section class="client-card"><h2>Progresso do mês</h2><div class="client-days"><?php for($d=1;$d<=$days;$d++):$fill=day_fill($paid,$dailyDue,$d);?><div class="client-day <?= $fill>=1?'paid':($fill>0?'partial':'') ?>"><span><?= $d ?></span><small><?= $fill>=1?'Pago':($fill>0?'Parcial':'Pendente') ?></small></div><?php endfor;?></div></section><?php endif;?>
      <section class="client-card"><h2>Últimos pagamentos</h2><?php if(!$history):?><p class="client-empty">Nenhum pagamento registrado ainda.</p><?php else:?><div class="client-history"><?php foreach($history as $h):?><div><span><?= date('d/m/Y H:i',strtotime($h['paid_at'])) ?></span><strong><?= money($h['amount']) ?></strong><small><?= e($h['notes']?:'Aporte registrado') ?></small></div><?php endforeach;?></div><?php endif;?></section>
      <section class="client-card"><h2>Meus comprovantes</h2><?php if(!$receipts):?><p class="client-empty">Nenhum comprovante enviado.</p><?php else:?><div class="client-history"><?php foreach($receipts as$r):?><div><span><?= date('d/m/Y H:i',strtotime($r['created_at'])) ?> · <?= e(receipt_status_label($r['status'])) ?></span><strong><?= money($r['amount']) ?></strong><small><a rel="noreferrer" target="_blank" href="<?= e(rtrim(app_origin(),'/').'/p/'.rawurlencode($token).'/comprovante/'.rawurlencode($r['stored_name'])) ?>"><?= e($r['original_name']) ?></a></small></div><?php endforeach;?></div><?php endif;?></section>
      <?php if($cycle['status']==='active'):?><section class="client-card receipt-upload-card"><h2>Enviar comprovante</h2><form method="post" enctype="multipart/form-data" action="<?= url('receipt_upload') ?>"><input type="hidden" name="csrf" value="<?= csrf_token() ?>"><input type="hidden" name="access_token" value="<?= e($token) ?>"><label>Valor<input class="money-input" name="amount" inputmode="decimal" required></label><label>Data e hora<input type="datetime-local" name="paid_at" value="<?= date('Y-m-d\TH:i') ?>" required></label><label>Arquivo<input type="file" name="receipt_file" accept="image/jpeg,image/png,image/webp,application/pdf" required><small>JPG, PNG, WEBP ou PDF, até 5 MB.</small></label><label>Observação<textarea name="notes" maxlength="500"></textarea></label><button class="btn primary">Enviar comprovante</button></form></section><?php endif;?>
      <?php if($messages):?><section class="client-card"><h2>Mensagens para você</h2><div class="client-history"><?php foreach($messages as$m):?><div><span><?= date('d/m/Y H:i',strtotime($m['created_at'])) ?></span><small><?= nl2br(e($m['message_text'])) ?></small></div><?php endforeach;?></div></section><?php endif;?>
      <footer class="client-footer">Consulte esta página sempre que quiser acompanhar seu caixa.</footer>
    </div>
    <?php render_footer();
}

function message_template_for_cycle(int $cycleId,string $code): ?array {
    $st=db()->prepare('SELECT * FROM message_templates WHERE code=? AND active=1 AND (cycle_id=? OR cycle_id IS NULL) ORDER BY cycle_id DESC LIMIT 1');
    $st->execute([$code,$cycleId]);return $st->fetch()?:null;
}
function build_participant_message(array $cycle,array $participant,string $code): string {
    $tpl=message_template_for_cycle((int)$cycle['id'],$code);
    if(!$tpl)return '';
    $paid=participant_total_paid((int)$cycle['id'],(int)$participant['id']);
    $point=db()->prepare('SELECT day FROM cycle_points WHERE cycle_id=? AND participant_id=? ORDER BY day LIMIT 1');$point->execute([$cycle['id'],$participant['id']]);$day=$point->fetchColumn();
    $paymentPix=administration_for_cycle((int)$cycle['id'],isset($cycle['company_id'])?(int)$cycle['company_id']:company_id());
    $vars=[
      '{nome}'=>$participant['name'],'{telefone}'=>$participant['phone']?:'não cadastrado','{caixa}'=>$cycle['name'],'{mes}'=>month_name((int)$cycle['month']),'{valor_diario}'=>money($cycle['daily_amount']),
      '{valor_total_ponto}'=>money(cycle_gross_payout($cycle)),'{valor_pago}'=>money($paid),'{dias_pagos}'=>($due=participant_daily_due((int)$cycle['id'],(int)$participant['id'],(float)$cycle['daily_amount']))>0?(string)floor($paid/$due):'0','{dias_pendentes}'=>$due>0?(string)max(0,days_in_cycle($cycle)-floor($paid/$due)):'0','{chave_pix}'=>$paymentPix['pix_key']??'não cadastrada',
      '{link_participante}'=>'solicite seu link individual à Administração',
      '{data_ponto}'=>$day?sprintf('%02d/%02d/%04d',$day,$cycle['month'],$cycle['year']):'a definir'
    ];
    return strtr($tpl['body'],$vars);
}
function page_messages(): void {
    $cycle=current_cycle();render_header('Central de Mensagens');
    if(!$cycle){echo '<div class="panel"><p>Crie ou selecione um caixa primeiro.</p></div>';render_footer();return;}
    $pdo=db();
    $ps=$pdo->prepare('SELECT p.* FROM participants p JOIN cycle_participants cp ON cp.participant_id=p.id WHERE cp.cycle_id=? AND p.active=1 ORDER BY p.name');$ps->execute([$cycle['id']]);$participants=$ps->fetchAll();
    $ts=$pdo->prepare('SELECT * FROM message_templates WHERE cycle_id=? OR cycle_id IS NULL ORDER BY cycle_id DESC,name');$ts->execute([$cycle['id']]);
    $templates=[];foreach($ts->fetchAll() as $t)if(!isset($templates[$t['code']]))$templates[$t['code']]=$t;
    $activeTemplates=array_filter($templates,fn($t)=>(int)$t['active']===1);
    $qs=$pdo->prepare('SELECT q.*,p.name participant_name,p.phone FROM message_queue q JOIN participants p ON p.id=q.participant_id WHERE q.cycle_id=? ORDER BY q.id DESC LIMIT 100');$qs->execute([$cycle['id']]);$queue=$qs->fetchAll();
    $counts=['waiting'=>0,'sent'=>0,'failed'=>0,'cancelled'=>0];foreach($queue as $q)$counts[$q['status']]++;
    $variables=['{nome}','{telefone}','{caixa}','{mes}','{valor_diario}','{valor_total_ponto}','{valor_pago}','{chave_pix}','{data_ponto}','{dias_pagos}','{dias_pendentes}','{link_participante}'];
    ?>
    <div class="page-heading"><div><h1>Central de Mensagens</h1><p>Crie modelos personalizados, prepare envios e acompanhe o histórico.</p></div></div>
    <section class="stats-grid message-stats"><article><span>Aguardando</span><strong><?= $counts['waiting'] ?></strong></article><article><span>Enviadas</span><strong><?= $counts['sent'] ?></strong></article><article><span>Falharam</span><strong><?= $counts['failed'] ?></strong></article><article><span>Canceladas</span><strong><?= $counts['cancelled'] ?></strong></article></section>
    <div class="grid two-cols">
      <section class="panel"><h2>Nova mensagem</h2>
        <form method="post" action="<?= url('message_queue_add') ?>" class="form-grid">
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <label>Participante<select name="participant_id" required><option value="">Selecione</option><?php foreach($participants as $p): if(is_administration((int)$cycle['id'],(int)$p['id']))continue;?><option value="<?= $p['id'] ?>"><?= e($p['name']) ?><?= $p['phone']?' · '.e($p['phone']):' · sem telefone' ?></option><?php endforeach;?></select></label>
          <label>Modelo<select name="template_code" required><option value="">Selecione</option><?php foreach($activeTemplates as $t):?><option value="<?= e($t['code']) ?>"><?= e($t['name']) ?> · <?= e($t['category']) ?></option><?php endforeach;?></select></label>
          <button class="btn primary" type="submit">Adicionar à fila</button>
        </form><p class="muted">A mensagem será preenchida com os dados atuais do participante.</p>
      </section>
      <section class="panel"><div class="panel-title"><h2>Novo modelo</h2></div>
        <form method="post" action="<?= url('message_template_save') ?>" class="form-grid message-template-form" data-template-form>
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
          <label>Nome do modelo<input name="name" maxlength="120" placeholder="Ex.: Lembrete semanal" required></label>
          <label>Categoria<select name="category"><option>Cobrança</option><option>Financeiro</option><option>Aviso</option><option>Relacionamento</option><option>Marketing</option><option>Outro</option></select></label>
          <label>Título opcional<input name="title" maxlength="160" placeholder="Ex.: Lembrete do GDA Caixas"></label>
          <label>Mensagem<textarea name="body" rows="8" placeholder="Digite a mensagem e use as variáveis abaixo." required data-template-body></textarea></label>
          <div class="variable-box"><strong>Inserir variável</strong><div class="variable-chips"><?php foreach($variables as $v):?><button type="button" class="variable-chip" data-insert-variable="<?= e($v) ?>"><?= e($v) ?></button><?php endforeach;?></div></div>
          <div class="template-preview"><strong>Pré-visualização</strong><div data-template-preview>Digite uma mensagem para visualizar.</div></div>
          <button class="btn primary" type="submit">Criar modelo</button>
        </form>
      </section>
    </div>
    <section class="panel"><div class="panel-title"><div><h2>Modelos de mensagens</h2><p class="muted">Modelos personalizados são salvos somente neste caixa.</p></div></div>
      <div class="template-list"><?php foreach($templates as $t):?><details class="template-item"><summary><span><strong><?= e($t['name']) ?></strong><small><?= e($t['category']) ?><?= $t['cycle_id']?' · personalizado':' · padrão' ?></small></span><span class="status <?= $t['active']?'status-sent':'status-cancelled' ?>"><?= $t['active']?'Ativo':'Inativo' ?></span></summary>
        <form method="post" action="<?= url('message_template_save') ?>" class="form-grid message-template-form" data-template-form>
          <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="code" value="<?= e($t['code']) ?>">
          <label>Nome<input name="name" value="<?= e($t['name']) ?>" required></label>
          <label>Categoria<select name="category"><?php foreach(['Cobrança','Financeiro','Aviso','Relacionamento','Marketing','Outro'] as $cat):?><option <?= $t['category']===$cat?'selected':'' ?>><?= e($cat) ?></option><?php endforeach;?></select></label>
          <label>Título opcional<input name="title" value="<?= e($t['title']??'') ?>"></label>
          <label>Mensagem<textarea name="body" rows="7" required data-template-body><?= e($t['body']) ?></textarea></label>
          <div class="variable-chips"><?php foreach($variables as $v):?><button type="button" class="variable-chip" data-insert-variable="<?= e($v) ?>"><?= e($v) ?></button><?php endforeach;?></div>
          <div class="template-preview"><strong>Pré-visualização</strong><div data-template-preview></div></div>
          <div class="template-actions"><button class="btn" type="submit">Salvar alterações</button></div>
        </form>
        <div class="template-secondary-actions"><form method="post" action="<?= url('message_template_toggle') ?>"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="code" value="<?= e($t['code']) ?>"><button class="btn" type="submit"><?= $t['active']?'Desativar':'Ativar' ?></button></form><?php if($t['cycle_id']):?><form method="post" action="<?= url('message_template_delete') ?>" onsubmit="return confirm('Excluir este modelo?')"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="code" value="<?= e($t['code']) ?>"><button class="btn danger" type="submit">Excluir</button></form><?php endif;?></div>
      </details><?php endforeach;?></div>
    </section>
    <section class="panel"><h2>Fila e histórico</h2><?php if(!$queue):?><p>Nenhuma mensagem adicionada ainda.</p><?php else:?><div class="table-wrap"><table><thead><tr><th>Participante</th><th>Mensagem</th><th>Status</th><th>Criada em</th><th>Ações</th></tr></thead><tbody><?php foreach($queue as $q):$wa=participant_whatsapp_url(['phone'=>$q['phone']],$q['message_text']);?><tr><td><strong><?= e($q['participant_name']) ?></strong><small><?= e($q['phone']?:'Sem telefone') ?></small></td><td class="message-cell"><?= nl2br(e($q['message_text'])) ?></td><td><span class="status status-<?= e($q['status']) ?>"><?= ['waiting'=>'Aguardando','sent'=>'Enviada','failed'=>'Falhou','cancelled'=>'Cancelada'][$q['status']] ?></span></td><td><?= date('d/m/Y H:i',strtotime($q['created_at'])) ?></td><td class="actions"><?php if($q['status']!=='cancelled'):?><?php if($wa):?><a class="btn primary" target="_blank" href="<?= e($wa) ?>">Abrir WhatsApp</a><?php endif;?><form method="post" action="<?= url('message_mark') ?>"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= $q['id'] ?>"><button class="btn" name="status" value="sent">Marcar enviada</button><button class="btn" name="status" value="failed">Marcar falha</button><button class="btn danger" name="status" value="cancelled">Cancelar</button></form><?php endif;?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></section>
    <?php render_footer();
}
function message_queue_add(): void {
    verify_csrf();$cycle=current_cycle();if(!$cycle)redirect('messages');
    $pid=(int)($_POST['participant_id']??0);$code=trim($_POST['template_code']??'');
    $st=db()->prepare('SELECT p.* FROM participants p JOIN cycle_participants cp ON cp.participant_id=p.id WHERE p.id=? AND cp.cycle_id=? AND p.active=1');$st->execute([$pid,$cycle['id']]);$p=$st->fetch();
    if(!$p||$code===''){flash('error','Selecione um participante e um modelo válidos.');redirect('messages');}
    $text=build_participant_message($cycle,$p,$code);if($text===''){flash('error','Modelo de mensagem não encontrado ou inativo.');redirect('messages');}
    db()->prepare('INSERT INTO message_queue(cycle_id,participant_id,template_code,message_text,scheduled_at) VALUES(?,?,?,?,NOW())')->execute([$cycle['id'],$pid,$code,$text]);
    flash('success','Mensagem adicionada à fila.');redirect('messages');
}
function message_template_save(): void {
    verify_csrf();$cycle=current_cycle();if(!$cycle)redirect('messages');
    $code=trim($_POST['code']??'');$name=trim($_POST['name']??'');$category=trim($_POST['category']??'Aviso');$title=trim($_POST['title']??'');$body=trim($_POST['body']??'');
    if($name===''||$body===''){flash('error','Informe o nome e o texto do modelo.');redirect('messages');}
    if($code==='')$code='custom_'.bin2hex(random_bytes(6));
    db()->prepare('INSERT INTO message_templates(cycle_id,code,name,category,title,body,active) VALUES(?,?,?,?,?,?,1) ON DUPLICATE KEY UPDATE name=VALUES(name),category=VALUES(category),title=VALUES(title),body=VALUES(body)')->execute([$cycle['id'],$code,$name,$category,$title?:null,$body]);
    flash('success','Modelo salvo com sucesso.');redirect('messages');
}
function message_template_toggle(): void {
    verify_csrf();$cycle=current_cycle();if(!$cycle)redirect('messages');$code=trim($_POST['code']??'');
    $current=message_template_for_cycle_any((int)$cycle['id'],$code);if(!$current){flash('error','Modelo não encontrado.');redirect('messages');}
    if(empty($current['cycle_id'])) db()->prepare('INSERT INTO message_templates(cycle_id,code,name,category,title,body,active) VALUES(?,?,?,?,?,?,?)')->execute([$cycle['id'],$code,$current['name'],$current['category'],$current['title'],$current['body'],$current['active']?0:1]);
    else db()->prepare('UPDATE message_templates SET active=1-active WHERE cycle_id=? AND code=?')->execute([$cycle['id'],$code]);
    flash('success','Status do modelo atualizado.');redirect('messages');
}
function message_template_delete(): void {
    verify_csrf();$cycle=current_cycle();if(!$cycle)redirect('messages');$code=trim($_POST['code']??'');
    db()->prepare('DELETE FROM message_templates WHERE cycle_id=? AND code=?')->execute([$cycle['id'],$code]);
    flash('success','Modelo excluído.');redirect('messages');
}
function message_template_for_cycle_any(int $cycleId,string $code): ?array {
    $st=db()->prepare('SELECT * FROM message_templates WHERE code=? AND (cycle_id=? OR cycle_id IS NULL) ORDER BY cycle_id DESC LIMIT 1');$st->execute([$code,$cycleId]);return $st->fetch()?:null;
}
function message_mark(): void {
    verify_csrf();$cycle=current_cycle();$id=(int)($_POST['id']??0);$status=$_POST['status']??'';
    if(!$cycle||!in_array($status,['sent','failed','cancelled'],true)){flash('error','Ação inválida.');redirect('messages');}
    $sent=$status==='sent'?date('Y-m-d H:i:s'):null;db()->prepare('UPDATE message_queue SET status=?,sent_at=? WHERE id=? AND cycle_id=?')->execute([$status,$sent,$id,$cycle['id']]);
    flash('success','Status da mensagem atualizado.');redirect('messages');
}

function page_reports(): void {
    $cycle=current_cycle();
    if(!$cycle){flash('error','Nenhum caixa ativo.');redirect('dashboard');}
    $stmt=db()->prepare("SELECT p.id,p.name,p.phone,COALESCE(SUM(c.amount),0) total_paid,COUNT(c.id) contribution_count FROM participants p JOIN cycle_participants cp ON cp.participant_id=p.id LEFT JOIN contributions c ON c.participant_id=p.id AND c.cycle_id=cp.cycle_id WHERE cp.cycle_id=? GROUP BY p.id,p.name,p.phone ORDER BY p.name");
    $stmt->execute([$cycle['id']]);$participants=$stmt->fetchAll();
    $stmt=db()->prepare("SELECT DATE(paid_at) day,COALESCE(SUM(amount),0) total FROM contributions WHERE cycle_id=? GROUP BY DATE(paid_at) ORDER BY day");$stmt->execute([$cycle['id']]);$daily=$stmt->fetchAll();
    $stmt=db()->prepare("SELECT po.point_day,p.name,po.amount,po.paid_at FROM payouts po JOIN participants p ON p.id=po.participant_id WHERE po.cycle_id=? AND po.status='paid' ORDER BY po.point_day");$stmt->execute([$cycle['id']]);$payoutRows=$stmt->fetchAll();
    $gross=cycle_gross_payout($cycle);$expected=cycle_net_payout($cycle);$cash=cycle_cash((int)$cycle['id']);$payoutTotal=array_sum(array_map(fn($r)=>(float)$r['amount'],$payoutRows));
    render_header('Relatórios'); ?>
    <div class="page-head"><div><h1>Relatórios</h1><p>Resumo financeiro e individual do caixa selecionado.</p></div><button class="btn" onclick="window.print()">Imprimir / Salvar em PDF</button></div>
    <section class="stats"><article><span>Arrecadação bruta prevista</span><strong><?= money($gross) ?></strong></article><article><span>Saldo atual</span><strong><?= money($cash) ?></strong></article><article><span>Pagamentos efetivos</span><strong><?= money($payoutTotal) ?></strong></article><article><span>Aportes registrados</span><strong><?= array_sum(array_map(fn($r)=>(int)$r['contribution_count'],$participants)) ?></strong></article></section>
    <section class="panel"><div class="panel-title"><h2>Resumo por participante</h2><input class="table-search" data-table-search="#report-participants" placeholder="Pesquisar participante"></div><div class="table-wrap"><table id="report-participants"><thead><tr><th>Participante</th><th>Telefone</th><th>Pontos</th><th>Total pago</th><th>Aportes</th></tr></thead><tbody><?php foreach($participants as $p):?><tr><td><?= e($p['name']) ?></td><td><?= e($p['phone']?:'—') ?></td><td><?= participant_point_count((int)$cycle['id'],(int)$p['id']) ?></td><td><?= money($p['total_paid']) ?></td><td><?= (int)$p['contribution_count'] ?></td></tr><?php endforeach;?></tbody></table></div></section>
    <section class="panel"><h2>Arrecadação por dia</h2><div class="bar-chart"><?php $max=max(array_map(fn($r)=>(float)$r['total'],$daily)?:[1]);foreach($daily as $r):$pct=$max>0?($r['total']/$max)*100:0;?><div class="bar-row"><span><?= date('d/m',strtotime($r['day'])) ?></span><div><i style="width:<?= round($pct) ?>%"></i></div><strong><?= money($r['total']) ?></strong></div><?php endforeach;?></div></section>
    <section class="panel"><h2>Pagamentos dos pontos</h2><div class="table-wrap"><table><thead><tr><th>Dia</th><th>Participante</th><th>Previsto</th><th>Pago</th><th>Restante</th><th>Data</th></tr></thead><tbody><?php foreach($payoutRows as $row):$paidAmount=(float)$row['amount'];?><tr><td><?= (int)$row['point_day'] ?></td><td><?= e($row['name']) ?></td><td><?= money($expected) ?></td><td><?= money($paidAmount) ?></td><td><?= money(max(0,$expected-$paidAmount)) ?></td><td><?= date('d/m/Y H:i',strtotime($row['paid_at'])) ?></td></tr><?php endforeach;?></tbody></table></div></section>
    <?php render_footer();
}

function page_receipt(): void {
    $id=(int)($_GET['id']??0);$stmt=db()->prepare("SELECT c.*,p.name,p.phone,p.pix_key,cy.name cycle_name,cy.month,cy.year FROM contributions c JOIN participants p ON p.id=c.participant_id JOIN cycles cy ON cy.id=c.cycle_id WHERE c.id=? AND cy.company_id=? LIMIT 1");$stmt->execute([$id,company_id()]);$r=$stmt->fetch();
    if(!$r){http_response_code(404);exit('Recibo não encontrado.');}
    render_header('Recibo'); ?>
    <section class="receipt"><div class="receipt-brand">GDA Plataforma</div><h1>Recibo de contribuição</h1><p>Recebemos de <strong><?= e($r['name']) ?></strong> o valor de <strong><?= money($r['amount']) ?></strong>, referente ao caixa <strong><?= e($r['cycle_name']) ?></strong>, competência <?= e(month_name((int)$r['month'])) ?>/<?= e($r['year']) ?>.</p><dl><dt>Data</dt><dd><?= date('d/m/Y H:i',strtotime($r['paid_at'])) ?></dd><dt>Identificação</dt><dd>#<?= (int)$r['id'] ?></dd><dt>Observação</dt><dd><?= e($r['notes']?:'Sem observação') ?></dd></dl><div class="receipt-actions"><button class="btn primary" onclick="window.print()">Imprimir / Salvar em PDF</button><button class="btn" onclick="history.back()">Voltar</button></div></section>
    <?php render_footer();
}


function page_connect_status(): void {
    header('Content-Type: application/json; charset=utf-8');
    $last=latest_mobile_access();
    echo json_encode([
      'connected'=>(bool)$last,
      'last_seen'=>$last ? date('d/m/Y H:i:s',strtotime($last['last_seen'])) : null,
      'ip'=>$last['ip_address']??null,
      'device'=>$last ? detect_device_label((string)$last['user_agent']) : null,
      'connect_url'=>gda_connect_url()
    ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
}
function detect_device_label(string $ua): string {
    $u=strtolower($ua);
    if(str_contains($u,'iphone')) return 'iPhone';
    if(str_contains($u,'ipad')) return 'iPad';
    if(str_contains($u,'android')) return 'Android';
    return 'Dispositivo móvel';
}
function connect_ip_save(): void {
    verify_csrf();
    $ip=trim((string)($_POST['lan_ip']??''));
    if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)){flash('error','Informe um IPv4 válido.');redirect('dashboard');}
    set_app_setting('connect_lan_ip',$ip);
    flash('success','Endereço de conexão atualizado.');
    redirect('dashboard');
}


function receipt_participant_from_request(): array {
    $token=trim((string)($_POST['access_token']??$_GET['access_token']??''));$link=participant_access_service()->resolve($token,false);
    if(!$link)throw new RuntimeException('Link inválido ou indisponível.');
    return[$link,(int)$link['cycle_id'],$token];
}

function receipt_upload(): void {
    verify_csrf();
    try { [$p,$cycleId,$token]=receipt_participant_from_request(); }
    catch(Throwable $e){http_response_code(403);exit('Não foi possível validar este link.');}
    if(($p['cycle_status']??'')!=='active'){flash('error','Este caixa está disponível somente para consulta.');header('Location: '.participant_portal_url($token));exit;}
    $amount=parse_money($_POST['amount']??0);$paidAt=str_replace('T',' ',trim((string)($_POST['paid_at']??'')));$notes=trim((string)($_POST['notes']??''));
    if($amount<=0||$paidAt===''){flash('error','Informe o valor e a data do pagamento.');header('Location: '.participant_portal_url($token));exit;}
    $file=$_FILES['receipt_file']??null;
    if(!$file||($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK){flash('error','Não foi possível receber o arquivo. Tente novamente.');header('Location: '.participant_portal_url($token));exit;}
    $max=(int)config('upload_max_bytes',5242880);if((int)$file['size']>$max){flash('error','O arquivo ultrapassa o limite de 5 MB.');header('Location: '.participant_portal_url($token));exit;}
    $finfo=new finfo(FILEINFO_MIME_TYPE);$mime=$finfo->file($file['tmp_name']);$allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','application/pdf'=>'pdf'];
    if(!isset($allowed[$mime])){flash('error','Formato inválido. Envie JPG, PNG, WEBP ou PDF.');header('Location: '.participant_portal_url($token));exit;}
    $stored=bin2hex(random_bytes(24)).'.'.$allowed[$mime];$dest=receipt_storage_directory().DIRECTORY_SEPARATOR.$stored;
    if(!move_uploaded_file($file['tmp_name'],$dest)){flash('error','Não foi possível salvar o comprovante.');header('Location: '.participant_portal_url($token));exit;}
    $original=mb_substr(basename((string)$file['name']),0,255);
    db()->prepare('INSERT INTO payment_receipts(cycle_id,participant_id,amount,paid_at,original_name,stored_name,mime_type,file_size,notes) VALUES(?,?,?,?,?,?,?,?,?)')->execute([$cycleId,$p['participant_id'],$amount,$paidAt.':'.(strlen($paidAt)===16?'00':'00'),$original,$stored,$mime,(int)$file['size'],$notes?:null]);
    flash('success','Comprovante enviado. A administração fará a conferência.');header('Location: '.participant_portal_url($token));exit;
}

function page_receipts(): void {
    $cycle=current_cycle();if(!$cycle){flash('error','Nenhum caixa selecionado.');redirect('dashboard');}
    $status=trim((string)($_GET['status']??''));$where='r.cycle_id=?';$args=[$cycle['id']];
    if(in_array($status,['pending','approved','rejected','resubmit'],true)){$where.=' AND r.status=?';$args[]=$status;}
    $stmt=db()->prepare("SELECT r.*,p.name participant_name,p.phone FROM payment_receipts r JOIN participants p ON p.id=r.participant_id WHERE $where ORDER BY FIELD(r.status,'pending','resubmit','rejected','approved'),r.created_at DESC");$stmt->execute($args);$rows=$stmt->fetchAll();
    $counts=['pending'=>0,'approved'=>0,'rejected'=>0,'resubmit'=>0];$cs=db()->prepare('SELECT status,COUNT(*) total FROM payment_receipts WHERE cycle_id=? GROUP BY status');$cs->execute([$cycle['id']]);foreach($cs->fetchAll() as $c)$counts[$c['status']]=(int)$c['total'];
    render_header('Comprovantes'); ?>
    <div class="page-head"><div><h1>Central de comprovantes</h1><p>Confira os arquivos enviados antes de registrar o aporte.</p></div></div>
    <section class="stats receipt-stats"><article><span>Em análise</span><strong><?= $counts['pending'] ?></strong></article><article><span>Aprovados</span><strong><?= $counts['approved'] ?></strong></article><article><span>Recusados</span><strong><?= $counts['rejected'] ?></strong></article><article><span>Reenvio solicitado</span><strong><?= $counts['resubmit'] ?></strong></article></section>
    <section class="panel"><div class="receipt-filters"><a class="btn small" href="<?= url('receipts') ?>">Todos</a><a class="btn small" href="<?= url('receipts',['status'=>'pending']) ?>">Em análise</a><a class="btn small" href="<?= url('receipts',['status'=>'approved']) ?>">Aprovados</a></div>
    <?php if(!$rows):?><div class="empty">Nenhum comprovante encontrado.</div><?php else:?><div class="receipt-admin-grid"><?php foreach($rows as $r):?><article class="receipt-admin-card"><div class="receipt-admin-head"><div><strong><?= e($r['participant_name']) ?></strong><small><?= date('d/m/Y H:i',strtotime($r['created_at'])) ?></small></div><span class="status status-<?= $r['status']==='approved'?'sent':($r['status']==='pending'?'waiting':'failed') ?>"><?= e(receipt_status_label($r['status'])) ?></span></div><dl><dt>Valor</dt><dd><?= money($r['amount']) ?></dd><dt>Pago em</dt><dd><?= date('d/m/Y H:i',strtotime($r['paid_at'])) ?></dd><dt>Arquivo</dt><dd><?= e($r['original_name']) ?></dd></dl><?php if($r['notes']):?><p><?= e($r['notes']) ?></p><?php endif;?><a class="btn" target="_blank" href="<?= url('receipt_file',['id'=>$r['id']]) ?>">Visualizar comprovante</a><?php if($r['status']==='pending'||$r['status']==='resubmit'):?><form method="post" action="<?= url('receipt_review') ?>" class="receipt-review-form"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><label>Observação da conferência<textarea name="review_notes" rows="2" maxlength="500"></textarea></label><div><button class="btn primary" name="decision" value="approved">Aprovar e registrar aporte</button><button class="btn danger" name="decision" value="rejected">Recusar</button><button class="btn" name="decision" value="resubmit">Solicitar novo envio</button></div></form><?php elseif($r['review_notes']):?><p><strong>Conferência:</strong> <?= e($r['review_notes']) ?></p><?php endif;?></article><?php endforeach;?></div><?php endif;?></section>
    <?php render_footer();
}

function receipt_review(): void {
    verify_csrf();$cycle=current_cycle();$id=(int)($_POST['id']??0);$decision=(string)($_POST['decision']??'');$notes=trim((string)($_POST['review_notes']??''));
    if(!$cycle||!in_array($decision,['approved','rejected','resubmit'],true)){flash('error','Ação inválida.');redirect('receipts');}
    $pdo=db();$pdo->beginTransaction();
    try{$st=$pdo->prepare('SELECT * FROM payment_receipts WHERE id=? AND cycle_id=? FOR UPDATE');$st->execute([$id,$cycle['id']]);$r=$st->fetch();if(!$r||!in_array($r['status'],['pending','resubmit'],true))throw new RuntimeException('Este comprovante já foi conferido.');$contributionId=null;
      if($decision==='approved'){$ins=$pdo->prepare('INSERT INTO contributions(cycle_id,participant_id,amount,paid_at,notes,created_by) VALUES(?,?,?,?,?,?)');$ins->execute([$r['cycle_id'],$r['participant_id'],$r['amount'],$r['paid_at'],'Comprovante #'.$r['id'].($r['notes']?' — '.$r['notes']:''),$_SESSION['user']['id']]);$contributionId=(int)$pdo->lastInsertId();}
      $up=$pdo->prepare('UPDATE payment_receipts SET status=?,review_notes=?,contribution_id=?,reviewed_by=?,reviewed_at=NOW() WHERE id=?');$up->execute([$decision,$notes?:null,$contributionId,$_SESSION['user']['id'],$id]);$pdo->commit();flash('success',$decision==='approved'?'Comprovante aprovado e aporte registrado.':'Status do comprovante atualizado.');
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();flash('error',$e->getMessage());}
    redirect('receipts');
}

function receipt_file(): void {
    $id=(int)($_GET['id']??0);$token=trim((string)($_GET['access_token']??''));$receiptToken=basename(trim((string)($_GET['receipt_token']??'')));
    if(!empty($_SESSION['user'])){$st=db()->prepare('SELECT r.* FROM payment_receipts r INNER JOIN cycles c ON c.id=r.cycle_id WHERE r.id=? AND c.company_id=? LIMIT 1');$st->execute([$id,company_id()]);}
    else{$link=participant_access_service()->resolve($token,false);if(!$link){http_response_code(404);exit('Arquivo não encontrado.');}$st=db()->prepare('SELECT * FROM payment_receipts WHERE stored_name=? AND cycle_id=? AND participant_id=? LIMIT 1');$st->execute([$receiptToken,$link['cycle_id'],$link['participant_id']]);}
    $r=$st->fetch();if(!$r){http_response_code(404);exit('Arquivo não encontrado.');}$file=receipt_storage_directory().DIRECTORY_SEPARATOR.basename($r['stored_name']);if(!is_file($file)){http_response_code(404);exit('Arquivo indisponível.');}
    header('Content-Type: '.$r['mime_type']);header('Content-Length: '.filesize($file));header('Content-Disposition: inline; filename="'.rawurlencode($r['original_name']).'"');header('X-Content-Type-Options: nosniff');readfile($file);exit;
}

function page_updates(): void {
    update_table_ensure();
    $history = recent_app_updates();
    $zipAvailable = class_exists('ZipArchive');
    $uploadLimit = min(
        update_php_size_to_bytes((string)ini_get('upload_max_filesize')),
        update_php_size_to_bytes((string)ini_get('post_max_size'))
    );
    render_header('Atualizações'); ?>
    <div class="page-head"><div><span class="eyebrow">Super Administração</span><h1>Atualizações GDA</h1><p>Instale novas versões com backup automático dos arquivos e do banco de dados.</p></div></div>

    <section class="stats-grid">
      <article class="stat"><span>Versão instalada</span><strong>v<?= e(APP_VERSION) ?></strong></article>
      <article class="stat"><span>Backup automático</span><strong>Ativo</strong></article>
      <article class="stat"><span>Configuração local</span><strong>Protegida</strong></article>
      <article class="stat"><span>Extensão ZIP</span><strong><?= $zipAvailable?'Disponível':'Indisponível' ?></strong></article>
    </section>

    <section class="card update-card">
      <div class="card-head"><div><h2>Instalar pacote de atualização</h2><p>Selecione um ZIP oficial do GDA Caixas. O arquivo <code>config/local.php</code> nunca será substituído.</p></div></div>
      <?php if(!$zipAvailable):?><div class="alert error">A extensão PHP ZipArchive não está ativa na hospedagem. Ative a extensão <strong>zip</strong> no seletor de PHP antes de atualizar.</div><?php endif;?>
      <form method="post" action="<?= url('update_install') ?>" enctype="multipart/form-data" class="form-grid">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <label class="full">Pacote ZIP
          <input type="file" name="update_package" accept=".zip,application/zip" required <?= $zipAvailable?'':'disabled' ?>>
          <small>Limite detectado: <?= e(update_format_bytes($uploadLimit)) ?>. O pacote precisa conter <code>update.json</code> na raiz.</small>
        </label>
        <label class="checkbox full"><input type="checkbox" name="confirmation" value="1" required> Confirmo que desejo criar o backup e instalar a nova versão.</label>
        <div class="full"><button class="btn primary" type="submit" <?= $zipAvailable?'':'disabled' ?>>Fazer backup e atualizar</button></div>
      </form>
    </section>

    <section class="card">
      <div class="card-head"><div><h2>O que o atualizador faz</h2></div></div>
      <div class="check-list">
        <p>✓ Faz backup completo do banco em SQL.</p>
        <p>✓ Compacta os arquivos atuais antes de substituir.</p>
        <p>✓ Executa migrações pendentes.</p>
        <p>✓ Preserva <code>config/local.php</code> e os backups anteriores.</p>
        <p>✓ Registra o resultado e os erros no histórico.</p>
      </div>
    </section>

    <section class="card">
      <div class="card-head"><div><h2>Histórico</h2><p>Últimas tentativas de atualização.</p></div></div>
      <?php if(!$history):?><p class="muted">Nenhuma atualização foi executada pelo painel ainda.</p><?php else:?><div class="table-wrap"><table><thead><tr><th>Data</th><th>Versão</th><th>Pacote</th><th>Status</th><th>Backup</th></tr></thead><tbody>
      <?php foreach($history as $row):?><tr><td><?= e(date('d/m/Y H:i',strtotime($row['started_at']))) ?></td><td>v<?= e($row['version_from']) ?> → v<?= e($row['version_to']) ?></td><td><?= e($row['package_name']?:'—') ?></td><td><span class="badge <?= e($row['status']) ?>"><?= e(update_status_label($row['status'])) ?></span></td><td><?php if($row['backup_file']):?><a class="btn small" href="<?= url('update_backup_download',['backup'=>$row['backup_file']]) ?>">Baixar</a><?php else:?>—<?php endif;?></td></tr><?php endforeach;?>
      </tbody></table></div><?php endif;?>
    </section>
    <?php render_footer();
}

function update_php_size_to_bytes(string $value): int {
    $value=trim($value); if($value==='') return 0; $last=strtolower($value[strlen($value)-1]); $n=(float)$value;
    return match($last){'g'=>(int)($n*1024*1024*1024),'m'=>(int)($n*1024*1024),'k'=>(int)($n*1024),default=>(int)$n};
}
function update_format_bytes(int $bytes): string { if($bytes<=0)return 'não informado'; $u=['B','KB','MB','GB'];$i=0;$n=$bytes;while($n>=1024&&$i<count($u)-1){$n/=1024;$i++;}return number_format($n,$i?1:0,',','.').' '.$u[$i]; }
function update_status_label(string $status): string { return ['started'=>'Em andamento','completed'=>'Concluída','failed'=>'Falhou','rolled_back'=>'Revertida'][$status]??$status; }

function update_install(): void {
    verify_csrf();
    if (empty($_POST['confirmation'])) { flash('error','Confirme a instalação da atualização.'); redirect('updates'); }
    if (!class_exists('ZipArchive')) { flash('error','A extensão ZIP do PHP não está disponível.'); redirect('updates'); }
    $file=$_FILES['update_package']??null;
    if(!$file || ($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK){ flash('error','Não foi possível receber o pacote de atualização.'); redirect('updates'); }
    if(($file['size']??0)<1 || ($file['size']??0)>100*1024*1024){ flash('error','O pacote deve ter no máximo 100 MB.'); redirect('updates'); }
    $name=basename((string)$file['name']);
    if(strtolower(pathinfo($name,PATHINFO_EXTENSION))!=='zip'){ flash('error','Envie um arquivo ZIP válido.'); redirect('updates'); }
    $tmp=update_storage_root().'/upload-'.bin2hex(random_bytes(8)).'.zip';
    if(!move_uploaded_file($file['tmp_name'],$tmp)){ flash('error','Falha ao salvar temporariamente o pacote.'); redirect('updates'); }
    try{
        $result=install_update_package($tmp,$name);
        $version=(string)$result['manifest']['version'];
        $migrations=count($result['migrations']);
        flash('success','Atualização para v'.$version.' concluída. Backup criado e '.$migrations.' migração(ões) executada(s). Recarregue a página.');
    }catch(Throwable $e){
        error_log('Falha na atualização GDA: '.$e->getMessage());
        flash('error','Atualização não concluída: '.$e->getMessage().' O backup foi preservado para recuperação.');
    }finally{ @unlink($tmp); }
    redirect('updates');
}

function update_backup_download(): void {
    auth_required();
    $backup=basename((string)($_GET['backup']??''));
    if(!preg_match('/^\d{8}-\d{6}-v[0-9A-Za-z._-]+$/',$backup)){http_response_code(400);exit('Backup inválido.');}
    $dir=update_backup_root().'/'.$backup;
    if(!is_dir($dir)){http_response_code(404);exit('Backup não encontrado.');}
    if(!class_exists('ZipArchive')){http_response_code(500);exit('Extensão ZIP indisponível.');}
    $download=update_storage_root().'/'.$backup.'.zip';
    $zip=new ZipArchive();
    if($zip->open($download,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true){http_response_code(500);exit('Falha ao preparar download.');}
    foreach(['database.sql','files.zip','backup.json'] as $f){if(is_file($dir.'/'.$f))$zip->addFile($dir.'/'.$f,$f);}
    $zip->close();
    header('Content-Type: application/zip');header('Content-Disposition: attachment; filename="'.$backup.'.zip"');header('Content-Length: '.filesize($download));
    readfile($download);@unlink($download);exit;
}
