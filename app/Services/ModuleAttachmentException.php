<?php
declare(strict_types=1);
namespace App\Services;
final class ModuleAttachmentException extends \RuntimeException { public function __construct(public readonly string $errorCode,string $safeMessage){parent::__construct($safeMessage);} }
