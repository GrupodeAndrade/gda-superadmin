<?php
declare(strict_types=1);
namespace App\Services;

use RuntimeException;
use ZipArchive;

final class UpdateService
{
    private const PROTECTED=['config/local.php','storage/backups','storage/logs','storage/updates'];
    public function analyze(string $zipPath):array
    {
        if(!extension_loaded('zip'))throw new RuntimeException('A extensão ZIP não está disponível.');
        $zip=new ZipArchive();if($zip->open($zipPath)!==true)throw new RuntimeException('O arquivo ZIP não pôde ser aberto.');
        $names=[];$hasPayload=false;
        for($i=0;$i<$zip->numFiles;$i++){
            $stat=$zip->statIndex($i);$name=$stat['name'];if(str_contains($name,'\\')){$zip->close();throw new RuntimeException('O pacote contém barras invertidas em caminhos.');}$names[]=$name;
            if($this->unsafePath($name)){$zip->close();throw new RuntimeException('O pacote contém caminho inseguro.');}
            $mode=($stat['external_attributes']??0)>>16;if(($mode&0120000)===0120000){$zip->close();throw new RuntimeException('Links simbólicos não são permitidos.');}
            if(str_starts_with($name,'payload/'))$hasPayload=true;
            foreach(self::PROTECTED as $protected)if($name==="payload/$protected"||str_starts_with($name,"payload/$protected/")){$zip->close();throw new RuntimeException('O pacote tenta alterar arquivos protegidos.');}
        }
        if(!in_array('update.json',$names,true)||!$hasPayload){$zip->close();throw new RuntimeException('O pacote precisa conter update.json e payload/.');}
        $raw=$zip->getFromName('update.json');$zip->close();$manifest=json_decode((string)$raw,true);
        if(!is_array($manifest)||json_last_error()!==JSON_ERROR_NONE)throw new RuntimeException('Manifesto JSON inválido.');
        foreach(['app','name','version','minimum_version','release_date','description'] as $key)if(empty($manifest[$key]))throw new RuntimeException("Campo obrigatório ausente: $key.");
        $current=json_decode((string)file_get_contents(base_path('version.json')),true)['version'];
        if($manifest['app']!=='gda-plataforma'||!version_compare($manifest['version'],$current,'>')||!version_compare($current,$manifest['minimum_version'],'>='))throw new RuntimeException('Pacote incompatível com esta instalação.');
        $manifest['_current']=$current;return $manifest;
    }
    public function install(string $zipPath,array $manifest,bool $backup):string
    {
        $lock=base_path('storage/updates/update.lock');if(!@mkdir($lock,0750))throw new RuntimeException('Já existe uma atualização em andamento.');
        $maintenance=base_path('storage/maintenance.lock');file_put_contents($maintenance,$manifest['description']);
        $rollback=base_path('storage/temp/rollback-'.bin2hex(random_bytes(5)));mkdir($rollback,0750,true);$backupPath='';$createdFiles=[];$createdDirs=[];$pendingTempFiles=[];$temp=null;
        try{
            $this->makeDirectory($rollback.'/files', $createdDirs, false);
            if(!copy(base_path('version.json'),$rollback.'/files/version.json'))throw new RuntimeException('Não foi possível preservar version.json.');
            if($backup)$backupPath=$this->backup($manifest['_current']);
            $zip=new ZipArchive();$zip->open($zipPath);$temp=base_path('storage/temp/update-'.bin2hex(random_bytes(5)));mkdir($temp,0750,true);
            $copiedFiles=0;
            for($i=0;$i<$zip->numFiles;$i++){
                $name=$zip->getNameIndex($i);if(str_contains($name,'\\'))throw new RuntimeException('O pacote contém barras invertidas em caminhos.');if(!str_starts_with($name,'payload/')||str_ends_with($name,'/'))continue;
                $relative=substr($name,8);if($this->unsafePath($relative))throw new RuntimeException('Caminho inseguro detectado.');
                $target=base_path($relative);$source=$zip->getStream($name);if(!$source)throw new RuntimeException('Falha ao ler o pacote.');
                if(is_file($target)){
                    $old=$rollback.'/files/'.$relative;$this->makeDirectory(dirname($old),$createdDirs,false);
                    if(!copy($target,$old)){fclose($source);throw new RuntimeException('Falha ao preparar o rollback.');}
                } else {
                    $createdFiles[]=$target;
                }
                $this->makeDirectory(dirname($target),$createdDirs,true);
                $pendingTempFiles[]=$target.'.new';$out=fopen($target.'.new','wb');if(!$out){fclose($source);throw new RuntimeException('Falha ao criar arquivo temporário.');}
                if(stream_copy_to_stream($source,$out)===false){fclose($source);fclose($out);throw new RuntimeException('Falha ao copiar arquivo.');}
                fclose($source);fclose($out);if(!rename($target.'.new',$target))throw new RuntimeException('Falha ao substituir arquivo.');
                $copiedFiles++;
                $failAfter=(int)(getenv('GDA_TEST_UPDATE_FAIL_AFTER_FILES')?:0);
                if(PHP_SAPI==='cli'&&$failAfter>0&&$copiedFiles>=$failAfter)throw new RuntimeException('Falha simulada após cópia.');
            }
            $zip->close();$version=['app'=>'gda-plataforma','name'=>'GDA Plataforma','version'=>$manifest['version'],'release_date'=>$manifest['release_date']];file_put_contents(base_path('version.json'),json_encode($version,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)."\n");
            $this->removeTree(base_path('storage/cache'),false);@unlink($maintenance);@rmdir($lock);$this->removeTree($temp);$this->removeTree($rollback);return $backupPath;
        }catch(\Throwable $e){
            if(isset($zip)&&$zip instanceof ZipArchive)$zip->close();
            $rollbackError=null;
            try{
                if(PHP_SAPI==='cli'&&getenv('GDA_TEST_ROLLBACK_FAILURE')==='1')throw new RuntimeException('Falha simulada no rollback.');
                $this->restoreFiles($rollback.'/files');
                foreach($pendingTempFiles as $file){if(is_file($file)&&!unlink($file))throw new RuntimeException('Não foi possível remover arquivo temporário: '.$file);}
                foreach(array_reverse($createdFiles) as $file){if(is_file($file)&&!unlink($file))throw new RuntimeException('Não foi possível remover arquivo novo: '.$file);}
                usort($createdDirs,fn($a,$b)=>strlen($b)<=>strlen($a));
                foreach(array_unique($createdDirs) as $dir){if(is_dir($dir)&&$this->isDirectoryEmpty($dir)&&!rmdir($dir))throw new RuntimeException('Não foi possível remover diretório novo: '.$dir);}
                $this->removeTree($rollback);if($temp)$this->removeTree($temp);@unlink($maintenance);@rmdir($lock);
            }catch(\Throwable $rollbackException){$rollbackError=$rollbackException;@file_put_contents(base_path('storage/logs/error.log'),'['.date('c').'] Rollback falhou: '.$rollbackException->getMessage()."\n",FILE_APPEND|LOCK_EX);}
            if($rollbackError)throw new UpdateFailureException('Atualização falhou: '.$e->getMessage().'; rollback falhou: '.$rollbackError->getMessage(),false,$e);
            @file_put_contents(base_path('storage/logs/error.log'),'['.date('c').'] Atualização falhou; rollback concluído: '.$e->getMessage()."\n",FILE_APPEND|LOCK_EX);
            throw new UpdateFailureException('Atualização falhou e o rollback foi concluído: '.$e->getMessage(),true,$e);
        }
    }
    public function backup(string $version):string
    {
        if(!extension_loaded('zip'))throw new RuntimeException('Extensão ZIP indisponível para backup.');
        $name='gda-backup-v'.$version.'-'.date('Y-m-d-His').'.zip';$path=base_path('storage/backups/'.$name);$zip=new ZipArchive();if($zip->open($path,ZipArchive::CREATE|ZipArchive::EXCL)!==true)throw new RuntimeException('Não foi possível criar o backup.');
        $root=base_path();$it=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root,\FilesystemIterator::SKIP_DOTS));
        foreach($it as $file){$full=$file->getPathname();$rel=str_replace('\\','/',substr($full,strlen($root)+1));if(str_starts_with($rel,'.git/')||str_starts_with($rel,'storage/backups/')||str_starts_with($rel,'storage/temp/')||str_starts_with($rel,'dist/'))continue;$zip->addFile($full,'files/'.$rel);}
        $this->dumpDatabase($zip);$zip->addFromString('manifest.json',json_encode(['date'=>date('c'),'version'=>$version,'user_id'=>$_SESSION['user_id']??null],JSON_PRETTY_PRINT));$zip->close();return $path;
    }
    private function dumpDatabase(ZipArchive $zip):void
    {
        $pdo=Database::connection();$tables=$pdo->query('SHOW TABLES')->fetchAll(\PDO::FETCH_COLUMN);$sql="SET FOREIGN_KEY_CHECKS=0;\n";
        foreach($tables as $table){$create=$pdo->query("SHOW CREATE TABLE `$table`")->fetch();$sql.="DROP TABLE IF EXISTS `$table`;\n".$create['Create Table'].";\n";$rows=$pdo->query("SELECT * FROM `$table`");while($row=$rows->fetch()){ $cols=array_map(fn($c)=>"`$c`",array_keys($row));$vals=array_map(fn($v)=>$v===null?'NULL':$pdo->quote((string)$v),array_values($row));$sql.='INSERT INTO `'.$table.'` ('.implode(',',$cols).') VALUES ('.implode(',',$vals).");\n"; }}$sql.="SET FOREIGN_KEY_CHECKS=1;\n";if(!$zip->addFromString('database.sql',$sql))throw new RuntimeException('Não foi possível incluir o banco no backup.');
    }
    private function unsafePath(string $p):bool{return $p===''||str_contains($p,"\0")||str_contains($p,'\\')||str_starts_with($p,'/')||preg_match('/^[A-Za-z]:\//',$p)||in_array('..',explode('/',$p),true);}
    private function restoreFiles(string $dir):void{if(!is_dir($dir))return;$it=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir,\FilesystemIterator::SKIP_DOTS));foreach($it as $f){$rel=str_replace('\\','/',substr($f->getPathname(),strlen($dir)+1));$target=base_path($rel);if(!is_dir(dirname($target))&&!mkdir(dirname($target),0750,true)&&!is_dir(dirname($target)))throw new RuntimeException('Falha ao recriar diretório no rollback.');if(!copy($f->getPathname(),$target))throw new RuntimeException('Falha ao restaurar arquivo: '.$rel);}}
    private function makeDirectory(string $dir,array &$createdDirs,bool $track):void{if(is_dir($dir))return;$missing=[];$cursor=$dir;while(!is_dir($cursor)){$missing[]=$cursor;$parent=dirname($cursor);if($parent===$cursor)break;$cursor=$parent;}if(!mkdir($dir,0750,true)&&!is_dir($dir))throw new RuntimeException('Falha ao criar diretório.');if($track)foreach(array_reverse($missing) as $created)$createdDirs[]=$created;}
    private function isDirectoryEmpty(string $dir):bool{$items=scandir($dir);return $items!==false&&count($items)===2;}
    private function removeTree(string $dir,bool $removeRoot=true):void{if(!is_dir($dir))return;$it=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $f)$f->isDir()?@rmdir($f->getPathname()):@unlink($f->getPathname());if($removeRoot)@rmdir($dir);}
}
