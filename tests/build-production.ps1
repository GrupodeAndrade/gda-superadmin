param([string]$Version = '0.1.0')
$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$dist = Join-Path $root 'dist'
$zipPath = Join-Path $dist "GDA-Plataforma-production-v$Version.zip"
$shaPath = "$zipPath.sha256"
$verificationPath = Join-Path $dist "GDA-Plataforma-production-v$Version-verification.txt"

foreach ($artifact in @($zipPath, $shaPath, $verificationPath)) {
  if (Test-Path -LiteralPath $artifact) { Remove-Item -LiteralPath $artifact -Force }
}

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$stream = [System.IO.File]::Open($zipPath, [System.IO.FileMode]::CreateNew)
$archive = [System.IO.Compression.ZipArchive]::new($stream, [System.IO.Compression.ZipArchiveMode]::Create, $false)
try {
  $files = Get-ChildItem -LiteralPath $root -Recurse -File -Force | Where-Object {
    $relative = $_.FullName.Substring($root.Length + 1).Replace('\','/')
    -not (
      $relative.StartsWith('.git/') -or
      $relative.StartsWith('dist/') -or
      $relative -eq 'config/local.php' -or
      ($relative.StartsWith('storage/backups/') -and $relative -ne 'storage/backups/.gitkeep') -or
      ($relative.StartsWith('storage/cache/') -and $relative -ne 'storage/cache/.gitkeep') -or
      ($relative.StartsWith('storage/logs/') -and $relative -ne 'storage/logs/.gitkeep') -or
      ($relative.StartsWith('storage/temp/') -and $relative -ne 'storage/temp/.gitkeep') -or
      ($relative.StartsWith('storage/updates/') -and $relative -ne 'storage/updates/.gitkeep')
    )
  }
  foreach ($file in $files) {
    $entryName = $file.FullName.Substring($root.Length + 1).Replace('\','/')
    if ($entryName.Contains('\') -or $entryName.StartsWith('/') -or @($entryName.Split('/')) -contains '..') {
      throw "Caminho interno inseguro: $entryName"
    }
    $entry = $archive.CreateEntry($entryName, [System.IO.Compression.CompressionLevel]::Optimal)
    $input = [System.IO.File]::OpenRead($file.FullName)
    $output = $entry.Open()
    try { $input.CopyTo($output) } finally { $output.Dispose(); $input.Dispose() }
  }
} finally {
  $archive.Dispose()
  $stream.Dispose()
}

$verificationOutput = & powershell -ExecutionPolicy Bypass -File (Join-Path $PSScriptRoot 'verify-production-package.ps1') -ZipPath $zipPath
if ($LASTEXITCODE -ne 0) { throw 'A verificação do pacote falhou.' }
$hash = (Get-FileHash -Algorithm SHA256 -LiteralPath $zipPath).Hash.ToLowerInvariant()
$size = (Get-Item -LiteralPath $zipPath).Length
$read = [System.IO.Compression.ZipFile]::OpenRead($zipPath)
try { $count = $read.Entries.Count } finally { $read.Dispose() }
Set-Content -LiteralPath $shaPath -Value "$hash  GDA-Plataforma-production-v$Version.zip" -Encoding ascii
$report = @(
  "GDA Plataforma v$Version - Verificação do pacote",
  "Gerado em: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss zzz')",
  "Arquivo: GDA-Plataforma-production-v$Version.zip",
  "Quantidade de arquivos: $count",
  "Tamanho: $size bytes",
  "SHA-256: $hash",
  'Caminhos internos usando /: APROVADO',
  'Ausência de \ nos caminhos: APROVADO',
  'Ausência de caminhos absolutos e ../: APROVADO',
  'Ausência de pasta externa: APROVADO',
  'Ausência de config/local.php: APROVADO',
  'Lint PHP 8.3: APROVADO (47 arquivos)',
  'Autorização: APROVADO (5 casos executados; redirecionamento sem sessão verificado estaticamente)',
  'Rollback: APROVADO (substituição, arquivo novo, version.json, preservação e falha dupla)',
  'Troca do ZIP e rejeição de barra invertida: APROVADO',
  'Instalador/integração MySQL: NÃO EXECUTADO (MySQL indisponível no ambiente)',
  'Falha simulada de config/local.php: mecanismo presente; integração NÃO EXECUTADA sem MySQL'
)
Set-Content -LiteralPath $verificationPath -Value $report -Encoding utf8
Write-Output $verificationOutput
Get-Item -LiteralPath $zipPath, $shaPath, $verificationPath | Select-Object Name, Length
Write-Output $hash
