<?php
declare(strict_types=1);
namespace Gda\Modules\Caixas\Services;

final class BackupService
{
    public function storageRoot(string $projectRoot): string { return $projectRoot . '/storage/backups'; }
    public function assertBelongsToCompany(array $payload, \Gda\ClientArea\ClientContext $context): void
    {
        if((int)($payload['company_id']??0)!==$context->companyId())throw new \RuntimeException('Backup pertence a outra empresa.');
    }
}
