<?php
$title = 'Dashboard';
$healthChecks = [];
foreach ([0 => 'Banco de dados', 2 => 'Armazenamento', 3 => 'Logs do sistema', 1 => 'Backup', 5 => 'Atualizações'] as $index => $label) {
    if (isset($systemChecks[$index])) $healthChecks[] = ['label' => $label] + $systemChecks[$index];
}
?>
<div class="dashboard-page">
    <section class="dashboard-hero">
        <div>
            <h1>Olá, Super Administrador!</h1>
            <p>Aqui está o resumo geral da GDA Plataforma.</p>
        </div>
    </section>

    <section class="dashboard-summary-grid" aria-label="Resumo operacional">
        <a class="dashboard-summary-card" href="<?= e(base_url('clientes')) ?>">
            <span class="dashboard-summary-icon tone-blue" aria-hidden="true">◉</span>
            <strong><?= (int) $stats['total_clients'] ?></strong><span class="dashboard-summary-label">Clientes</span><small>Ver clientes</small>
        </a>
        <a class="dashboard-summary-card" href="<?= e(base_url('clientes?status=active')) ?>">
            <span class="dashboard-summary-icon tone-green" aria-hidden="true">✓</span>
            <strong><?= (int) $stats['active_clients'] ?></strong><span class="dashboard-summary-label">Clientes ativos</span><small>Ver clientes ativos</small>
        </a>
        <a class="dashboard-summary-card" href="<?= e(base_url('projetos')) ?>">
            <span class="dashboard-summary-icon tone-purple" aria-hidden="true">◆</span>
            <strong><?= (int) $stats['total_projects'] ?></strong><span class="dashboard-summary-label">Projetos</span><small>Ver projetos</small>
        </a>
        <a class="dashboard-summary-card" href="<?= e(base_url('projetos')) ?>">
            <span class="dashboard-summary-icon tone-orange" aria-hidden="true">▦</span>
            <strong><?= (int) $stats['production_projects'] ?></strong><span class="dashboard-summary-label">Módulos em produção</span><small>Ver módulos</small>
        </a>
        <a class="dashboard-summary-card" href="<?= e(base_url('atualizacoes')) ?>">
            <span class="dashboard-summary-icon tone-coral" aria-hidden="true">↻</span>
            <strong class="dashboard-summary-text"><?= $platform['last_update'] ? e('v' . $platform['last_update']['new_version']) : 'Nenhuma' ?></strong>
            <span class="dashboard-summary-label">Atualizações</span><small><?= $platform['last_update'] ? e('Última em ' . date('d/m/Y', strtotime($platform['last_update']['updated_at']))) : 'Nenhuma registrada' ?></small>
        </a>
        <a class="dashboard-summary-card" href="<?= e(base_url('atualizacoes')) ?>">
            <span class="dashboard-summary-icon tone-teal" aria-hidden="true">◷</span>
            <strong class="dashboard-summary-text"><?= $platform['last_backup_at'] ? e(date('d/m/Y', (int) $platform['last_backup_at'])) : 'Nenhum' ?></strong>
            <span class="dashboard-summary-label">Último backup</span><small><?= $platform['last_backup_at'] ? e(date('H:i', (int) $platform['last_backup_at'])) : 'Nenhum backup' ?></small>
        </a>
    </section>

    <div class="dashboard-main-grid">
        <section class="panel dashboard-module-panel" aria-labelledby="dashboard-modules-title">
            <div class="panel-head dashboard-section-head">
                <div><h2 id="dashboard-modules-title">Módulos GDA</h2><p>Projetos cadastrados e sua utilização pelos clientes.</p></div>
                <a href="<?= e(base_url('projetos')) ?>">Ver todos</a>
            </div>
            <?php if (!$modules): ?>
                <div class="dashboard-empty-state"><span aria-hidden="true">◇</span><b>Nenhum módulo cadastrado.</b></div>
            <?php else: ?>
                <div class="dashboard-modules-grid">
                    <?php foreach ($modules as $module): ?>
                        <article class="dashboard-module-card">
                            <div class="dashboard-module-head"><span class="dashboard-module-icon" aria-hidden="true"><?= $module['icon'] === 'wallet' ? '▣' : '◆' ?></span><span class="badge <?= e(dashboard_status_class($module['status'])) ?>"><?= e(dashboard_project_status_label($module['status'])) ?></span></div>
                            <div><h3><?= e($module['name']) ?></h3><p><?= e(dashboard_excerpt($module['description'])) ?></p></div>
                            <dl class="dashboard-module-meta"><div><dt>Versão</dt><dd>v<?= e($module['version']) ?></dd></div><div><dt>Clientes vinculados</dt><dd><?= (int) $module['client_count'] ?></dd></div></dl>
                            <a class="btn" href="<?= e(base_url('projetos/' . $module['id'])) ?>">Ver detalhes</a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="panel dashboard-health-panel" aria-labelledby="dashboard-health-title">
            <div class="panel-head"><div><h2 id="dashboard-health-title">Situação do sistema</h2><p>Estado dos recursos internos.</p></div></div>
            <ul class="dashboard-health-list">
                <?php foreach ($healthChecks as $check): ?><li><span><?= e($check['label']) ?></span><b class="health-state <?= e($check['state']) ?>"><i aria-hidden="true"></i><?= e($check['text']) ?></b></li><?php endforeach; ?>
            </ul>
        </section>
    </div>

    <section class="panel dashboard-activity-panel">
        <div class="panel-head dashboard-section-head"><div><h2>Atividades recentes</h2><p>Últimas ações realizadas na plataforma.</p></div><a href="<?= e(base_url('logs')) ?>">Ver todas</a></div>
        <?php if (!$activities): ?>
            <div class="dashboard-empty-state"><span aria-hidden="true">◷</span><b>Nenhuma atividade registrada recentemente.</b></div>
        <?php else: ?>
            <div class="dashboard-activity-list">
                <?php foreach ($activities as $activity): ?><div><span class="dashboard-activity-dot" aria-hidden="true"></span><p><b><?= e($activity['user_name'] ?? 'Sistema') ?></b><span><?= e(dashboard_activity_label($activity['action'])) ?></span><?php if (trim((string) $activity['description']) !== dashboard_activity_label($activity['action'])): ?><small><?= e(dashboard_excerpt($activity['description'], 180)) ?></small><?php endif; ?><time><?= e(date('d/m/Y H:i', strtotime($activity['created_at']))) ?></time></p></div><?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
