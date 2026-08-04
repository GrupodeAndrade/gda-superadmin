<?php
declare(strict_types=1);
namespace Gda\Modules\Caixas\Services;

final class MessageService
{
    public function interpolate(string $template, array $variables): string
    {
        return strtr($template, $variables);
    }
    public function queueForCompany(\PDO $db, \Gda\ClientArea\ClientContext $context): array
    {
        $stmt=$db->prepare('SELECT q.* FROM message_queue q INNER JOIN cycles c ON c.id=q.cycle_id WHERE c.company_id=? ORDER BY q.id DESC');
        $stmt->execute([$context->companyId()]);
        return $stmt->fetchAll();
    }
}
