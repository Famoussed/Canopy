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

    public function render(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'error' => 'invalid_status_transition',
            'message' => $this->getMessage(),
            'current_status' => $this->currentStatus,
            'target_status' => $this->targetStatus,
        ], 422);
    }
}
