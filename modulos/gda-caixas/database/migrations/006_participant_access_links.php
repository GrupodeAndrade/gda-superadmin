<?php
declare(strict_types=1);

return static function(PDO $pdo): void {
    $columnExists=static function(string $column)use($pdo):bool{$stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?');$stmt->execute(['cycle_participants',$column]);return(int)$stmt->fetchColumn()>0;};
    $indexExists=static function(string $index)use($pdo):bool{$stmt=$pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?');$stmt->execute(['cycle_participants',$index]);return(int)$stmt->fetchColumn()>0;};
    if(!$columnExists('access_token_hash'))$pdo->exec('ALTER TABLE cycle_participants ADD COLUMN access_token_hash CHAR(64) NULL AFTER joined_at');
    if(!$columnExists('access_token_prefix'))$pdo->exec('ALTER TABLE cycle_participants ADD COLUMN access_token_prefix VARCHAR(12) NULL AFTER access_token_hash');
    if(!$columnExists('access_enabled'))$pdo->exec('ALTER TABLE cycle_participants ADD COLUMN access_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER access_token_prefix');
    if(!$columnExists('access_created_at'))$pdo->exec('ALTER TABLE cycle_participants ADD COLUMN access_created_at DATETIME NULL AFTER access_enabled');
    if(!$columnExists('access_rotated_at'))$pdo->exec('ALTER TABLE cycle_participants ADD COLUMN access_rotated_at DATETIME NULL AFTER access_created_at');
    if(!$columnExists('last_access_at'))$pdo->exec('ALTER TABLE cycle_participants ADD COLUMN last_access_at DATETIME NULL AFTER access_rotated_at');
    if(!$indexExists('uniq_cycle_participant_access_hash'))$pdo->exec('ALTER TABLE cycle_participants ADD UNIQUE KEY uniq_cycle_participant_access_hash(access_token_hash)');
    if(!$indexExists('idx_cycle_participant_access_enabled'))$pdo->exec('ALTER TABLE cycle_participants ADD INDEX idx_cycle_participant_access_enabled(access_enabled,last_access_at)');
};
