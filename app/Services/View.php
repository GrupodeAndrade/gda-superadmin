<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class View
{
    public static function render(string $view, array $data = [], string $layout = 'admin'): void
    {
        $root = dirname(__DIR__, 2) . '/app/Views/';
        $file = $root . $view . '.php';
        if (!is_file($file)) {
            throw new RuntimeException('Visualização não encontrada.');
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        $content = (string) ob_get_clean();
        $layoutFile = $root . 'layouts/' . $layout . '.php';
        require $layoutFile;
        unset($_SESSION['_old']);
    }
}

