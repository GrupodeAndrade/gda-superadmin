<?php
declare(strict_types=1);
namespace Gda\Core\Database;

use PDO;
use RuntimeException;

final class MigrationRunner
{
    public function __construct(private PDO $db) {}

    public static function declared(array $manifest): array
    {
        if(!array_key_exists('migrations',$manifest))return[];
        if(!is_array($manifest['migrations'])||array_keys($manifest['migrations'])!==($manifest['migrations']===[]?[]:range(0,count($manifest['migrations'])-1)))throw new RuntimeException('Manifesto inválido: migrations deve ser uma lista.');
        $result=[];$last=-1;
        foreach($manifest['migrations']as$name){
            if(!is_string($name)||!preg_match('/^(\d{3})_[A-Za-z0-9][A-Za-z0-9_-]*\.php$/',$name))throw new RuntimeException('Manifesto inválido: nome de migração inseguro.');
            if(isset($result[$name]))throw new RuntimeException('Manifesto inválido: migração duplicada.');
            $number=(int)substr($name,0,3);if($number<$last)throw new RuntimeException('Manifesto inválido: migrações fora de ordem.');
            $last=$number;$result[$name]=$name;
        }
        return array_values($result);
    }

    public static function validateFiles(array $migrations,string $directory): void
    {
        $base=realpath($directory);if($migrations&&$base===false)throw new RuntimeException('Diretório de migrações ausente no pacote.');
        foreach($migrations as$name){$path=$directory.'/'.$name;$real=realpath($path);if($real===false||!is_file($real)||!str_starts_with(str_replace('\\','/',$real),rtrim(str_replace('\\','/',(string)$base),'/').'/'))throw new RuntimeException('Migração declarada ausente no pacote: '.$name);if(!is_readable($real))throw new RuntimeException('Migração declarada não pode ser lida: '.$name);}
    }

    public function runDeclared(array $migrations,string $directory): array
    {
        self::validateFiles($migrations,$directory);$this->ensureTable();$executed=[];
        $check=$this->db->prepare('SELECT COUNT(*) FROM schema_migrations WHERE migration=?');
        $save=$this->db->prepare('INSERT INTO schema_migrations (migration, applied_at) VALUES (?, NOW())');
        foreach($migrations as$name){
            $check->execute([$name]);if((int)$check->fetchColumn()>0)continue;
            try{$callable=require $directory.'/'.$name;if(!is_callable($callable))throw new RuntimeException('callable inválido');$callable($this->db);$save->execute([$name]);$executed[]=$name;}
            catch(\Throwable $e){throw new RuntimeException('Falha ao executar a migração '.$name.'.');}
        }
        return$executed;
    }

    public function pending(array $migrations): array
    {
        $this->ensureTable();$check=$this->db->prepare('SELECT COUNT(*) FROM schema_migrations WHERE migration=?');$pending=[];foreach($migrations as$name){$check->execute([$name]);if((int)$check->fetchColumn()===0)$pending[]=$name;}return$pending;
    }

    private function ensureTable(): void
    {
        $this->db->exec('CREATE TABLE IF NOT EXISTS schema_migrations (migration VARCHAR(190) PRIMARY KEY, applied_at DATETIME NOT NULL) ENGINE=InnoDB');
    }
}
