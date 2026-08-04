$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$files = @{
  schema = Get-Content -Raw -LiteralPath (Join-Path $root 'database/schema.sql')
  installer = Get-Content -Raw -LiteralPath (Join-Path $root 'install/index.php')
  migration = Get-Content -Raw -LiteralPath (Join-Path $root 'app/Services/ModuleMigrationService.php')
  list = Get-Content -Raw -LiteralPath (Join-Path $root 'app/Views/projetos/index.php')
  details = Get-Content -Raw -LiteralPath (Join-Path $root 'app/Views/projetos/show.php')
  clients = Get-Content -Raw -LiteralPath (Join-Path $root 'app/Views/clientes/form.php')
  dashboard = Get-Content -Raw -LiteralPath (Join-Path $root 'app/Views/dashboard/index.php')
  router = Get-Content -Raw -LiteralPath (Join-Path $root 'app/Services/Router.php')
  css = Get-Content -Raw -LiteralPath (Join-Path $root 'public/assets/css/app.css')
}
foreach ($required in @('gda-caixas','GDA Caixas','https://caixas.gda.app.br','https://caixas.gda.app.br/admin-gda','deploying')) {
  if (-not $files.installer.Contains($required) -or -not $files.migration.Contains($required)) { throw "Cadastro ausente: $required" }
}
foreach ($legacy in @('caixadiario.gda.app.br',"'Caixa Diário','caixa-diario'")) {
  if ($files.installer.Contains($legacy) -or $files.schema.Contains($legacy)) { throw "Legado ativo encontrado: $legacy" }
}
foreach ($preservation in @('$projectId = (int) $legacy[''id'']','INSERT INTO client_projects','DELETE FROM client_projects','MIGRATION_ID','ON DUPLICATE KEY UPDATE')) {
  if (-not $files.migration.Contains($preservation)) { throw "Garantia de migração ausente: $preservation" }
}
if (-not $files.list.Contains('Administrar') -or -not $files.details.Contains('URL administrativa')) { throw 'Listagem ou detalhes incompletos.' }
if (-not $files.clients.Contains('project_status_label') -or -not $files.dashboard.Contains('Caixas')) { throw 'Clientes ou Dashboard não exibem Caixas.' }
if (-not $files.router.Contains('Auth::isSuperAdmin()')) { throw 'Bloqueio de papéis não super_admin ausente.' }
foreach ($breakpoint in @('@media(max-width:1000px)','@media(max-width:650px)')) { if (-not $files.css.Contains($breakpoint)) { throw "Responsividade ausente: $breakpoint" } }
Write-Output 'Módulo Caixas: 18 verificações estruturais aprovadas.'
