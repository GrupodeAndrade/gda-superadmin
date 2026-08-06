<?php $title = 'Dashboard'; ?>
<div class="dashboard-page">
    <section class="page-head dashboard-heading">
        <div>
            <h1>Visão geral</h1>
            <p>Acompanhe clientes, módulos e a operação da GDA Plataforma.</p>
        </div>
    </section>

    <section class="dashboard-summary-grid" aria-label="Resumo operacional">
        <a class="dashboard-summary-card" href="<?= e(base_url('clientes')) ?>">
            <span>Clientes</span><strong><?= (int) $stats['total_clients'] ?></strong><small>Ver clientes</small>
        </a>
        <a class="dashboard-summary-card" href="<?= e(base_url('clientes?status=active')) ?>">
            <span>Clientes ativos</span><strong><?= (int) $stats['active_clients'] ?></strong><small>Ver clientes ativos</small>
        </a>
        <a class="dashboard-summary-card" href="<?= e(base_url('projetos')) ?>">
            <span>Projetos</span><strong><?= (int) $stats['total_projects'] ?></strong><small>Ver projetos</small>
        </a>
        <a class="dashboard-summary-card" href="<?= e(base_url('projetos')) ?>">
            <span>Módulos em produção</span><strong><?= (int) $stats['production_projects'] ?></strong><small>Ver módulos</small>
        </a>
        <a class="dashboard-summary-card" href="<?= e(base_url('atualizacoes')) ?>">
            <span>Atualizações</span>
            <strong class="dashboard-summary-text"><?= $platform['last_update'] ? e('v' . $platform['last_update']['new_version']) : 'Nenhuma' ?></strong>
            <small><?= $platform['last_update'] ? e('Última em ' . date('d/m/Y', strtotime($platform['last_update']['updated_at']))) : 'Nenhuma registrada' ?></small>
        </a>
        <a class="dashboard-summary-card" href="<?= e(base_url('atualizacoes')) ?>">
            <span>Último backup</span>
            <strong class="dashboard-summary-text"><?= $platform['last_backup_at'] ? e(date('d/m/Y', (int) $platform['last_backup_at'])) : 'Nenhum' ?></strong>
            <small><?= $platform['last_backup_at'] ? e(date('H:i', (int) $platform['last_backup_at'])) : 'Nenhum backup' ?></small>
        </a>
    </section>

    <section class="dashboard-section" aria-labelledby="dashboard-modules-title">
        <div class="panel-head dashboard-section-head">
            <div><h2 id="dashboard-modules-title">Módulos GDA</h2><p>Projetos cadastrados e sua utilização pelos clientes.</p></div>
            <a href="<?= e(base_url('projetos')) ?>">Ver todos</a>
        </div>
        <?php if (!$modules): ?>
            <div class="panel empty"><b>Nenhum módulo cadastrado.</b></div>
        <?php else: ?>
            <div class="dashboard-modules-grid">
                <?php foreach ($modules as $module): ?>
                    <article class="module-card dashboard-module-card">
                        <div class="module-card-head"><div class="module-icon" aria-hidden="true"><?= $module['icon'] === 'wallet' ? '▣' : '◆' ?></div><span class="badge <?= e(dashboard_status_class($module['status'])) ?>"><?= e(dashboard_project_status_label($module['status'])) ?></span></div>
                        <div><h3><?= e($module['name']) ?></h3><p><?= e(dashboard_excerpt($module['description'])) ?></p></div>
                        <dl class="dashboard-module-meta"><div><dt>Versão</dt><dd>v<?= e($module['version']) ?></dd></div><div><dt>Clientes vinculados</dt><dd><?= (int) $module['client_count'] ?></dd></div></dl>
                        <a class="btn" href="<?= e(base_url('projetos/' . $module['id'])) ?>">Ver módulo</a>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <div class="dashboard-content-grid">
        <section class="panel dashboard-clients-panel">
            <div class="panel-head"><h2>Últimos clientes</h2><a href="<?= e(base_url('clientes')) ?>">Ver todos</a></div>
            <?php if (!$clients): ?>
                <div class="empty"><b>Nenhum cliente cadastrado.</b><p>Cadastre o primeiro cliente para iniciar a operação.</p><a class="btn primary" href="<?= e(base_url('clientes/novo')) ?>">Cadastrar primeiro cliente</a></div>
            <?php else: ?>
                <div class="dashboard-client-list">
                    <?php foreach ($clients as $client): ?>
                        <article class="dashboard-client-item">
                            <div class="dashboard-client-name"><strong><?= e($client['name']) ?></strong><small><?= e($client['identifier']) ?></small></div>
                            <div><span class="dashboard-field-label">Status</span><span class="badge <?= e($client['status']) ?>"><?= $client['status'] === 'active' ? 'Ativo' : 'Inativo' ?></span></div>
                            <div><span class="dashboard-field-label">Módulos</span><b><?= (int) $client['project_count'] ?></b></div>
                            <div><span class="dashboard-field-label">Cadastro</span><time><?= e(date('d/m/Y', strtotime($client['created_at']))) ?></time></div>
                            <a href="<?= e(base_url('clientes/' . $client['id'])) ?>">Ver cliente</a>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="panel dashboard-health-panel">
            <div class="panel-head"><h2>Situação do sistema</h2></div>
            <ul class="health dashboard-health-list">
                <?php foreach ($systemChecks as $check): ?><li><span><?= e($check['label']) ?></span><b class="health-state <?= e($check['state']) ?>"><?= e($check['text']) ?></b></li><?php endforeach; ?>
            </ul>
        </section>
    </div>

    <section class="panel dashboard-activity-panel">
        <div class="panel-head"><h2>Atividades recentes</h2><a href="<?= e(base_url('logs')) ?>">Ver logs</a></div>
        <?php if (!$activities): ?><div class="empty">Nenhuma atividade registrada.</div><?php else: ?>
            <div class="timeline dashboard-activity-list">
                <?php foreach ($activities as $activity): ?><div><span class="dot"></span><p><b><?= e($activity['user_name'] ?? 'Sistema') ?></b><span><?= e(dashboard_activity_label($activity['action'])) ?></span><?php if (trim((string) $activity['description']) !== dashboard_activity_label($activity['action'])): ?><small><?= e(dashboard_excerpt($activity['description'], 180)) ?></small><?php endif; ?><time><?= e(date('d/m/Y H:i', strtotime($activity['created_at']))) ?></time></p></div><?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
