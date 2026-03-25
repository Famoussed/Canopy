<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class InvalidStatusTransitionException extends Exception
{
    public function __construct(
        public readonly string $currentStatus,
        public readonly string $targetStatus,
        public readonly string $entity,
    ) {
        $entityName = class_basename($entity);
        $message = "{$entityName} durumu '{$currentStatus}' → '{$targetStatus}' geçişi yapılamaz.";

        parent::__construct($message, 422);
    }
}
