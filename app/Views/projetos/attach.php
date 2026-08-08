<?php $title='Anexar projeto'; ?>
<section class="page-head"><div><h1>Anexar projeto</h1><p>Valide a URL pública e a chave temporária fornecida pelo módulo GDA.</p></div></section>

<?php if (empty($preview)): ?>
<form class="panel attachment-form" method="post" action="<?= e(base_url('projetos/anexar/validar')) ?>" data-loading>
    <?= csrf_field() ?>
    <label>URL do módulo<input type="url" name="module_url" maxlength="1000" placeholder="https://caixas.gda.app.br" autocomplete="url" required></label>
    <label>Chave de anexação<input name="attachment_key" maxlength="19" pattern="[A-HJ-NP-Za-hj-np-z2-9]{4}(?:-[A-HJ-NP-Za-hj-np-z2-9]{4}){3}" placeholder="XXXX-XXXX-XXXX-XXXX" autocomplete="off" spellcheck="false" required></label>
    <div class="form-actions"><a class="btn" href="<?= e(base_url('projetos')) ?>">Cancelar</a><button class="btn primary" type="submit">Validar módulo</button></div>
</form>
<?php else: ?>
<section class="panel attachment-preview" aria-labelledby="module-found-title">
    <div class="panel-head"><div><h2 id="module-found-title">Módulo encontrado</h2><p>Confira os dados retornados pelo próprio módulo antes de confirmar.</p></div><span class="badge success">Validado</span></div>
    <dl>
        <dt>Nome</dt><dd><?= e($preview['name']) ?></dd>
        <dt>Versão</dt><dd>v<?= e($preview['version']) ?></dd>
        <dt>UUID</dt><dd><code><?= e($preview['uuid']) ?></code></dd>
        <dt>Código</dt><dd><code><?= e($preview['code']) ?></code></dd>
        <dt>Slug</dt><dd><code><?= e($preview['slug']) ?></code></dd>
        <dt>URL</dt><dd><a href="<?= e($preview['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($preview['url']) ?></a></dd>
        <dt>Status</dt><dd><?= e($preview['status']) ?></dd>
        <dt>Versão da API</dt><dd><?= e($preview['api_version']) ?></dd>
        <dt>Versão mínima GDA</dt><dd><?= e($preview['minimum_version']) ?></dd>
        <dt>Compatibilidade</dt><dd><code><?= e(json_encode($preview['compatibility'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) ?></code></dd>
        <dt>Capabilities</dt><dd><code><?= e(json_encode($preview['capabilities'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) ?></code></dd>
        <dt>Health</dt><dd><code><?= e(json_encode($preview['health'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) ?></code></dd>
        <dt>Última atualização</dt><dd><?= $preview['last_updated_at']?e(date('d/m/Y H:i',strtotime($preview['last_updated_at']))):'Não informada' ?></dd>
    </dl>
    <p class="attachment-confirmation">Deseja anexar este módulo?</p>
    <div class="form-actions">
        <form method="post" action="<?= e(base_url('projetos/anexar/cancelar')) ?>"><?= csrf_field() ?><button class="btn" type="submit">Cancelar</button></form>
        <form method="post" action="<?= e(base_url('projetos/anexar/confirmar')) ?>" data-loading><?= csrf_field() ?><button class="btn primary" type="submit">Confirmar anexação</button></form>
    </div>
</section>
<?php endif; ?>
