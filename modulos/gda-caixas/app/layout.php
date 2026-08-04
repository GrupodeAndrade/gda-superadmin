<?php function render_header(string $title): void { $user = $_SESSION['user'] ?? null; $logo=gda_logo_url(); ?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?> &mdash; <?= e(config('app_name')) ?></title><meta name="theme-color" content="#10243f"><link rel="manifest" href="<?= e(app_base_path()) ?>/public/manifest.json"><link rel="stylesheet" href="<?= e(app_base_path()) ?>/public/assets/style.css?v=<?= e(APP_VERSION) ?>"></head><body class="<?= is_mobile_request()?'is-mobile-device':'' ?>">
<?php if ($user): $headerCycle=current_cycle(); ?><header class="topbar"><div class="brand-area"><?php if($logo):?><img class="gda-logo" src="<?= e($logo) ?>?v=<?= e(APP_VERSION) ?>" alt="Logo GDA"><?php else:?><span class="brand-monogram">GDA</span><?php endif;?><div class="brand-copy"><strong>GDA Plataforma</strong><span>Caixas<?= $headerCycle?' &mdash; '.e($headerCycle['name']):'' ?></span></div></div><button class="nav-toggle" type="button" aria-label="Abrir menu" aria-expanded="false">&#9776;</button><nav aria-label="Menu principal">
<a href="<?= url('dashboard') ?>">Dashboard</a>
<details class="nav-group"><summary>Caixa</summary><div class="nav-submenu"><a href="<?= url('contribution') ?>">Aportes</a><a href="<?= url('points') ?>">Pontos</a><a href="<?= url('payouts') ?>">Pagamentos</a><a href="<?= url('participants') ?>">Participantes</a><a href="<?= url('reports') ?>">Hist&oacute;rico</a></div></details>
<a href="<?= url('messages') ?>">Mensagens</a>
<a href="<?= url('settings') ?>">Configura&ccedil;&otilde;es</a>
<form method="post" action="<?= url('logout') ?>" class="logout-form"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><button type="submit" class="nav-link-button">Sair</button></form>
</nav></header>
<?php endif; ?>
<main class="container"><?php foreach (flashes() as $f): ?><div class="alert <?= e($f['type']) ?>"><?= e($f['message']) ?></div><?php endforeach; ?>
<?php }
function render_footer(): void { ?></main><script src="<?= e(app_base_path()) ?>/public/assets/app.js?v=<?= e(APP_VERSION) ?>"></script></body></html><?php }
