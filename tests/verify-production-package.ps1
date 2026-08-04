param([Parameter(Mandatory=$true)][string]$ZipPath)
$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$archive = [System.IO.Compression.ZipFile]::OpenRead((Resolve-Path -LiteralPath $ZipPath))
try {
  $names = @($archive.Entries | ForEach-Object { $_.FullName })
  if (-not ($names -contains 'index.php')) { throw 'index.php não está na raiz.' }
  if (-not ($names -contains '.htaccess')) { throw '.htaccess não está na raiz.' }
  if ($names -contains 'config/local.php') { throw 'config/local.php foi incluído.' }
  if (@($names | Where-Object { $_.Contains('\') }).Count) { throw 'O ZIP contém barra invertida.' }
  if (@($names | Where-Object { $_.StartsWith('/') -or $_ -match '^[A-Za-z]:/' }).Count) { throw 'O ZIP contém caminho absoluto.' }
  if (@($names | Where-Object { @($_.Split('/')) -contains '..' }).Count) { throw 'O ZIP contém ../.' }
  if (@($names | Group-Object | Where-Object Count -gt 1).Count) { throw 'O ZIP contém entradas duplicadas.' }
  if (@($names | Where-Object { $_ -like 'gda-plataforma/*' }).Count) { throw 'O ZIP contém pasta externa.' }
  Write-Output "Pacote POSIX aprovado: $($names.Count) entradas, todas usando /."
} finally {
  $archive.Dispose()
}

