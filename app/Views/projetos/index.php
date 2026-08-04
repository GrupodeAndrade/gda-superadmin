<?php $title='Projetos GDA'; ?>
<section class="page-head"><div><h1>Projetos GDA</h1><p>Produtos e módulos oferecidos pela GDA.</p></div></section>
<?php if(!$projects): ?><section class="panel"><div class="empty">Nenhum projeto cadastrado.</div></section><?php else: ?>
<div class="module-grid"><?php foreach($projects as $p): ?><article class="module-card">
<div class="module-icon" aria-hidden="true"><?= $p['icon']==='wallet'?'▣':'◆' ?></div>
<div class="module-card-head"><div><h2><?= e($p['name']) ?></h2><small><?= e($p['full_name']?:$p['code']) ?></small></div><span class="badge <?= e($p['status']) ?>"><?= e(project_status_label($p['status'])) ?></span></div>
<p><?= e($p['code']==='gda-caixas'?'Gerencie caixas de diferentes modalidades em um único módulo.':$p['description']) ?></p>
<div class="module-meta"><span>v<?= e($p['version']) ?></span><span><?= (int)$p['client_count'] ?> cliente(s)</span></div>
<div class="module-actions"><a class="btn" href="<?= e(base_url('projetos/'.$p['id'])) ?>">Ver detalhes</a><?php if($p['admin_url']): ?><a class="btn primary" href="<?= e($p['admin_url']) ?>" target="_blank" rel="noopener noreferrer">Administrar</a><?php endif; ?></div>
</article><?php endforeach; ?></div><?php endif; ?>
