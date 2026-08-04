<?php
declare(strict_types=1);
namespace Gda\Modules\Caixas\Models;

final class Cycle
{
    public function __construct(
        public int $id,
        public string $name,
        public string $modality,
        public ?string $startDate,
        public int $year,
        public int $month,
        public float $dailyAmount,
        public bool $administrationPays,
        public string $status,
        public ?string $notes,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self((int)$row['id'], (string)$row['name'], (string)($row['modality']??'DIARIO'), $row['start_date']??null, (int)$row['year'], (int)$row['month'], (float)$row['daily_amount'], (bool)($row['admin_pays'] ?? false), (string)$row['status'], $row['notes']??null);
    }
}
