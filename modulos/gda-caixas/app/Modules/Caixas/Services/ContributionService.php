<?php
declare(strict_types=1);
namespace Gda\Modules\Caixas\Services;

final class ContributionService
{
    public function parseAmount(mixed $value): float
    {
        $text = preg_replace('/[^0-9,.-]/', '', trim((string)$value)) ?? '';
        if (str_contains($text, ',')) $text = str_replace(',', '.', str_replace('.', '', $text));
        return is_numeric($text) ? (float)$text : 0.0;
    }
}
