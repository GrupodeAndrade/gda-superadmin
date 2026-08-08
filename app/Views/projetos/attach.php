<?php $title='Anexar módulo'; ?>
<section class="page-head"><div><h1>Anexar módulo</h1><p>Informe a URL pública oficial. A identidade será consultada diretamente no módulo.</p></div></section>
<?php if(empty($preview)):?>
<form class="panel attachment-form" method="post" action="<?=e(base_url('projetos/anexar'))?>" data-loading>
    <?=csrf_field()?>
    <label>URL do módulo<input type="url" name="module_url" maxlength="1000" placeholder="https://caixas.gda.app.br" autocomplete="url" required></label>
    <div class="form-actions"><a class="btn" href="<?=e(base_url('projetos'))?>">Cancelar</a><button class="btn primary" type="submit">Anexar módulo</button></div>
</form>
<?php else:?>
<section class="panel attachment-preview"><div class="panel-head"><div><h2>Validação técnica legada</h2><p>Fallback por chave preservado para recuperação de módulos antigos.</p></div><span class="badge success">Validado</span></div>
<dl><dt>Nome</dt><dd><?=e($preview['name'])?></dd><dt>UUID</dt><dd><code><?=e($preview['uuid'])?></code></dd><dt>URL</dt><dd><?=e($preview['url'])?></dd></dl>
<div class="form-actions"><form method="post" action="<?=e(base_url('projetos/anexar/cancelar'))?>"><?=csrf_field()?><button class="btn" type="submit">Cancelar</button></form><form method="post" action="<?=e(base_url('projetos/anexar/confirmar'))?>"><?=csrf_field()?><button class="btn primary" type="submit">Confirmar fallback</button></form></div></section>
<?php endif;?>
