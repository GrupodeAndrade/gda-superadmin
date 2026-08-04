<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use Throwable;

final class UpdateFailureException extends RuntimeException
{
    public function __construct(
        string $message,
        private bool $rollbackCompleted,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function rollbackCompleted(): bool
    {
        return $this->rollbackCompleted;
    }
}
