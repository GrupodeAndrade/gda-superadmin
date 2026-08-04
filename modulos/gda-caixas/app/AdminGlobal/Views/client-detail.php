<h1><?= htmlspecialchars($company['name']) ?></h1>
<section class="global-panel"><dl><dt>Identificador</dt><dd><?= htmlspecialchars($company['identifier']) ?></dd><dt>GDA client</dt><dd><?= htmlspecialchars($company['gda_client_id']??'—') ?></dd><dt>Status</dt><dd><?= htmlspecialchars($company['status']) ?></dd><dt>Criada em</dt><dd><?= htmlspecialchars($company['created_at']) ?></dd></dl></section>
<section class="global-panel"><h2>Status do ambiente</h2><p><?= htmlspecialchars($company['environment_status']) ?></p></section>
<?php foreach(['users'=>'Usuários','cycles'=>'Caixas','participants'=>'Participantes','settings'=>'Configurações operacionais','activity'=>'Última atividade','errors'=>'Últimos erros','migration_history'=>'Histórico de migração']as$key=>$label):?>
<section class="global-panel"><h2><?= $label ?></h2><pre><?= htmlspecialchars(json_encode($company[$key],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)) ?></pre></section>
<?php endforeach;?>
