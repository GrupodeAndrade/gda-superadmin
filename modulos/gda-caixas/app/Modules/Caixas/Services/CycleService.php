<?php
declare(strict_types=1);
namespace Gda\Modules\Caixas\Services;

use Gda\Modules\Caixas\Repositories\CycleRepository;

final class CycleService
{
    public function __construct(private CycleRepository $cycles) {}
    public function current(): ?array
    {
        $cycle = $this->cycles->active((int)($_SESSION['current_cycle_id'] ?? 0) ?: null);
        if ($cycle) $_SESSION['current_cycle_id'] = (int)$cycle['id'];
        return $cycle;
    }
    public function days(array $cycle): int { return cal_days_in_month(CAL_GREGORIAN, (int)$cycle['month'], (int)$cycle['year']); }
    public function create(\PDO $db, \Gda\ClientArea\ClientContext $context, string $name, int $year, int $month, float $dailyAmount, bool $adminPays, ?string $startDate = null, ?string $notes = null): int
    {
        $startDate ??= sprintf('%04d-%02d-01',$year,$month);
        try {
            $stmt=$db->prepare("INSERT INTO cycles(company_id,name,modality,start_date,year,month,daily_amount,admin_pays,status,notes) VALUES(?,?,'DIARIO',?,?,?,?,?,'active',?)");
            $stmt->execute([$context->companyId(),$name,$startDate,$year,$month,$dailyAmount,$adminPays ? 1 : 0,$notes]);
        } catch (\PDOException $e) {
            if (!str_contains(strtolower($e->getMessage()), 'modality')) throw $e;
            $stmt=$db->prepare("INSERT INTO cycles(company_id,name,year,month,daily_amount,admin_pays,status) VALUES(?,?,?,?,?,?,'active')");
            $stmt->execute([$context->companyId(),$name,$year,$month,$dailyAmount,$adminPays ? 1 : 0]);
        }
        return (int)$db->lastInsertId();
    }
}
