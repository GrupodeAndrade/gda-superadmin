<?php
declare(strict_types=1);
namespace Gda\Modules\Caixas\Models;

final class Participant
{
    public function __construct(public int $id, public string $name, public ?string $phone, public bool $active) {}
    public static function fromRow(array $row): self
    {
        return new self((int)$row['id'], (string)$row['name'], $row['phone'] ?? null, (bool)$row['active']);
    }
}
