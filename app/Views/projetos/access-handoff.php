<?php $title='Acesso ao módulo'; ?>
<section class="panel access-handoff"><h1>Abrindo administração do módulo…</h1><p>A autorização é temporária e será enviada diretamente por POST.</p><form id="module-access" method="post" action="<?=e($handoff['action'])?>"><input type="hidden" name="access_token" value="<?=e($handoff['access_token'])?>"><noscript><button class="btn primary" type="submit">Continuar</button></noscript></form></section>
<script>document.getElementById('module-access').submit();</script>
