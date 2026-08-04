<?php
declare(strict_types=1);
namespace Gda\Modules\Caixas\Services;

final class UpdateService
{
    public function assertGlobalAdministrator(): void
    {
        if (($_SESSION['user']['role'] ?? '') !== 'global_admin' || !empty($_SESSION['user']['company_id'])) throw new \RuntimeException('Atualizações estruturais exigem administração global.');
    }
    public function packagePathIsSafe(string $name): bool
    {
        $name = str_replace('\\', '/', $name);
        return $name !== '' && !str_starts_with($name, '/') && !preg_match('#(^|/)\.\.(/|$)#', $name) && !preg_match('#^[A-Za-z]:/#', $name);
    }
}
