<?php $maintenance=update_maintenance_state(); ?>
<h1>Atualizações estruturais</h1>
<p>Versão instalada: <?= htmlspecialchars(APP_VERSION) ?></p>
<?php if($maintenance):?><section class="global-panel"><h2>Manutenção ativa</h2><p>Etapa: <strong><?= htmlspecialchars((string)($maintenance['stage']??'desconhecida')) ?></strong></p><p><?= htmlspecialchars((string)($maintenance['message']??'Restauração administrativa necessária.')) ?></p></section><?php endif; ?>
<section class="global-panel"><h2>Nova atualização</h2><p>O pacote será validado, terá seu SHA-256 registrado, receberá backup obrigatório e somente então substituirá arquivos e executará as migrações declaradas.</p><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?= global_csrf() ?>"><input type="file" name="update_package" accept=".zip" required <?= $maintenance?'disabled':'' ?>><button <?= $maintenance?'disabled':'' ?>>Validar, criar backup e instalar</button></form></section>
<section class="global-panel"><h2>Histórico e relatório</h2><pre><?= htmlspecialchars(json_encode($history,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre></section>
