<?php
declare(strict_types=1);

return static function(PDO $pdo): void {
    $columnExists=static function(string $column)use($pdo):bool{$s=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');$s->execute(['cycles',$column]);return(int)$s->fetchColumn()>0;};
    if(!$columnExists('modality'))$pdo->exec("ALTER TABLE cycles ADD COLUMN modality ENUM('DIARIO','SEMANAL','QUINZENAL','MENSAL','PERSONALIZADO') NOT NULL DEFAULT 'DIARIO' AFTER name");
    if(!$columnExists('start_date'))$pdo->exec('ALTER TABLE cycles ADD COLUMN start_date DATE NULL AFTER modality');
    if(!$columnExists('notes'))$pdo->exec('ALTER TABLE cycles ADD COLUMN notes TEXT NULL AFTER status');
    $pdo->exec("ALTER TABLE cycles MODIFY status ENUM('active','archived','closed') NOT NULL DEFAULT 'active'");
    $pdo->exec("UPDATE cycles SET modality='DIARIO' WHERE modality IS NULL OR modality=''");
    $pdo->exec('UPDATE cycles SET start_date=MAKEDATE(year,1)+INTERVAL (month-1) MONTH WHERE start_date IS NULL');
};
