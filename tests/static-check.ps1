$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$required = @(
  'index.php','.htaccess','version.json','database/schema.sql','config/local.example.php',
  'public/index.php','routes/web.php','install/index.php','app/Services/UpdateService.php',
  'app/Services/RuntimeConfig.php','app/Services/InstallationState.php','tests/runtime-configuration.php',
  'app/Services/ProjectAttachmentService.php','app/Services/ProjectConnectionMigrationService.php','tests/module-attachment.php'
)
foreach ($item in $required) {
  if (-not (Test-Path -LiteralPath (Join-Path $root $item))) { throw "Arquivo obrigatório ausente: $item" }
}
$version = Get-Content -Raw -LiteralPath (Join-Path $root 'version.json') | ConvertFrom-Json
if ($version.app -ne 'gda-plataforma' -or $version.version -ne '1.0.0') { throw 'version.json inválido' }
$schema = Get-Content -Raw -LiteralPath (Join-Path $root 'database/schema.sql')
foreach ($table in @('users','clients','projects','client_projects','project_connections','settings','activity_logs','system_updates','migrations','login_attempts')) {
  if ($schema -notmatch "CREATE TABLE IF NOT EXISTS $table") { throw "Tabela ausente: $table" }
}
$routes = Get-Content -Raw -LiteralPath (Join-Path $root 'routes/web.php')
foreach ($route in @('/login','/dashboard','/clientes','/projetos','/projetos/anexar','/atualizacoes','/logs','/configuracoes','/perfil')) {
  if (-not $routes.Contains($route)) { throw "Rota ausente: $route" }
}
$update = Get-Content -Raw -LiteralPath (Join-Path $root 'app/Services/UpdateService.php')
foreach ($guard in @('config/local.php','str_contains($p,"\0")','in_array(''..'',explode(')) {
  if (-not $update.Contains($guard)) { throw "Proteção ausente: $guard" }
}
$auth = Get-Content -Raw -LiteralPath (Join-Path $root 'app/Services/Auth.php')
$router = Get-Content -Raw -LiteralPath (Join-Path $root 'app/Services/Router.php')
$authController = Get-Content -Raw -LiteralPath (Join-Path $root 'app/Controllers/AuthController.php')
foreach ($rule in @(
  "role = `"super_admin`"",
  "function isSuperAdmin",
  "role'] === 'super_admin'"
)) {
  if (-not $auth.Contains($rule)) { throw "Regra de autorização ausente: $rule" }
}
if (-not $router.Contains('Auth::isSuperAdmin()') -or -not $router.Contains("View::render('errors/403'")) {
  throw 'Roteador não exige Super Administrador de forma centralizada.'
}
if (-not $authController.Contains('Auth::isSuperAdmin()')) { throw 'Login não confirma a função Super Administrador.' }

foreach ($rollbackRule in @(
  '$createdFiles=[]',
  '$createdDirs=[]',
  'restoreFiles($rollback',
  'array_reverse($createdFiles)',
  'isDirectoryEmpty',
  'rollback falhou',
  'version.json'
)) {
  if (-not $update.Contains($rollbackRule)) { throw "Regra de rollback ausente: $rollbackRule" }
}

$updateController = Get-Content -Raw -LiteralPath (Join-Path $root 'app/Controllers/UpdateController.php')
foreach ($integrityRule in @("hash_file('sha256'","hash_equals(","manifest!=", 'rollbackCompleted()')) {
  if (-not $updateController.Contains($integrityRule)) { throw "Verificação de integridade ausente: $integrityRule" }
}

$installer = Get-Content -Raw -LiteralPath (Join-Path $root 'install/index.php')
foreach ($installerRule in @('is_writable','information_schema.tables','config/.gda-install-','is_file($localFile)','@unlink($tmp)','GDA_TEST_INSTALL_WRITE_FAILURE')) {
  if (-not $installer.Contains($installerRule)) { throw "Proteção do instalador ausente: $installerRule" }
}
$runtime = Get-Content -Raw -LiteralPath (Join-Path $root 'app/Services/RuntimeConfig.php')
foreach ($runtimeRule in @('DB_HOST','DB_PORT','DB_DATABASE','DB_USERNAME','DB_PASSWORD','source'' => ''environment','source'' => ''local_file')) {
  if (-not $runtime.Contains($runtimeRule)) { throw "Configuração de runtime ausente: $runtimeRule" }
}
$installationState = Get-Content -Raw -LiteralPath (Join-Path $root 'app/Services/InstallationState.php')
foreach ($stateRule in @('ESSENTIAL_TABLES','information_schema.tables','role=''super_admin''','status=''active''','PARTIAL')) {
  if (-not $installationState.Contains($stateRule)) { throw "Detecção de instalação ausente: $stateRule" }
}
if ($installer.Contains('name="db_host"') -and -not $installer.Contains('if ($environmentMode)')) {
  throw 'Credenciais do ambiente permanecem editáveis no instalador.'
}
Write-Output 'Validação estrutural aprovada.'
Write-Output 'Autorização centralizada: aprovada por inspeção estática.'
Write-Output 'Rollback e integridade do ZIP: aprovados por inspeção estática.'
Write-Output 'Proteção contra instalação parcial: aprovada por inspeção estática.'
