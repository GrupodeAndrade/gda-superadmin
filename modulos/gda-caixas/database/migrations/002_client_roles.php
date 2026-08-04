<?php
declare(strict_types=1);

return static function (PDO $pdo): void {
    $columns = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('role', $columns, true)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN role VARCHAR(40) NOT NULL DEFAULT 'client_admin' AFTER password");
    }
    $pdo->exec("UPDATE users SET role='client_admin' WHERE role='' OR role IS NULL");
};
