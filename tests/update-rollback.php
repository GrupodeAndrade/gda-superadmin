<?php
declare(strict_types=1);

$testRoot = sys_get_temp_dir() . '/gda-update-test-' . bin2hex(random_bytes(5));
function base_path(string $path = ''): string
{
    global $testRoot;
    return $testRoot . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

require dirname(__DIR__) . '/bootstrap/autoload.php';

use App\Services\UpdateFailureException;
use App\Services\UpdateService;

function makePackage(string $path, array $files): void
{
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Falha ao criar ZIP.');
    $zip->addFromString('update.json', json_encode([
        'app' => 'gda-plataforma', 'name' => 'GDA Plataforma', 'version' => '0.2.0',
        'minimum_version' => '0.1.0', 'release_date' => '2026-08-01', 'description' => 'Teste',
        'migrations' => [], 'preserve' => ['config/local.php'],
    ]));
    $zip->addEmptyDir('payload/');
    foreach ($files as $name => $contents) $zip->addFromString('payload/' . $name, $contents);
    $zip->close();
}

function removeTestTree(string $dir): void
{
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    rmdir($dir);
}

try {
    foreach (['storage/updates','storage/temp','storage/cache','storage/logs','storage/backups','config'] as $dir) mkdir(base_path($dir), 0777, true);
    file_put_contents(base_path('version.json'), '{"app":"gda-plataforma","name":"GDA Plataforma","version":"0.1.0","release_date":"2026-07-30"}');
    file_put_contents(base_path('existing.txt'), 'original');
    file_put_contents(base_path('config/local.php'), 'protegido');
    $package = base_path('package.zip');
    makePackage($package, ['existing.txt' => 'alterado', 'new/created.txt' => 'novo']);
    $service = new UpdateService();
    $manifest = $service->analyze($package);

    $service->install($package, $manifest, false);
    if (file_get_contents(base_path('existing.txt')) !== 'alterado' || file_get_contents(base_path('new/created.txt')) !== 'novo') {
        throw new RuntimeException('Atualização válida não substituiu/criou arquivos.');
    }
    unlink(base_path('new/created.txt')); rmdir(base_path('new'));
    file_put_contents(base_path('existing.txt'), 'original');
    file_put_contents(base_path('version.json'), '{"app":"gda-plataforma","name":"GDA Plataforma","version":"0.1.0","release_date":"2026-07-30"}');

    putenv('GDA_TEST_UPDATE_FAIL_AFTER_FILES=2');
    try { $service->install($package, $manifest, false); throw new RuntimeException('A falha simulada não ocorreu.'); }
    catch (UpdateFailureException $e) { if (!$e->rollbackCompleted()) throw $e; }
    putenv('GDA_TEST_UPDATE_FAIL_AFTER_FILES');
    if (file_get_contents(base_path('existing.txt')) !== 'original') throw new RuntimeException('Arquivo substituído não foi restaurado.');
    if (is_file(base_path('new/created.txt'))) throw new RuntimeException('Arquivo novo não foi removido.');
    if (is_dir(base_path('new'))) throw new RuntimeException('Diretório novo vazio não foi removido.');
    if (str_contains(file_get_contents(base_path('version.json')), '0.2.0')) throw new RuntimeException('version.json não foi restaurado.');
    if (file_get_contents(base_path('config/local.php')) !== 'protegido') throw new RuntimeException('config/local.php foi alterado.');

    $badPackage=base_path('bad-slash.zip');$bad=new ZipArchive();$bad->open($badPackage,ZipArchive::CREATE|ZipArchive::OVERWRITE);$bad->addFromString('update.json','{}');$bad->addFromString('payload\\evil.php','x');$bad->close();
    try{$service->analyze($badPackage);throw new RuntimeException('ZIP com barra invertida foi aceito.');}catch(RuntimeException $e){if(!str_contains($e->getMessage(),'barras invertidas'))throw $e;}

    $originalHash=hash_file('sha256',$package);file_put_contents($package,'troca',FILE_APPEND);
    $changedHash=hash_file('sha256',$package);if(hash_equals($originalHash,$changedHash))throw new RuntimeException('Troca do ZIP não foi detectável.');
    makePackage($package, ['existing.txt' => 'alterado', 'new/created.txt' => 'novo']);

    putenv('GDA_TEST_UPDATE_FAIL_AFTER_FILES=1');
    putenv('GDA_TEST_ROLLBACK_FAILURE=1');
    try { $service->install($package, $manifest, false); throw new RuntimeException('A falha dupla não ocorreu.'); }
    catch (UpdateFailureException $e) { if ($e->rollbackCompleted()) throw new RuntimeException('Rollback foi marcado incorretamente como concluído.'); }
    if (!is_file(base_path('storage/maintenance.lock')) || !is_dir(base_path('storage/updates/update.lock'))) throw new RuntimeException('Manutenção ou trava não foram mantidas.');
    echo "Atualizador aprovado: substituição, rollback, arquivo novo, preservação, barra invertida, troca de ZIP e falha dupla.\n";
} finally {
    putenv('GDA_TEST_UPDATE_FAIL_AFTER_FILES');
    putenv('GDA_TEST_ROLLBACK_FAILURE');
    removeTestTree($testRoot);
}
