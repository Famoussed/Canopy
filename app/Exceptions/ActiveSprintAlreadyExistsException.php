<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class ActiveSprintAlreadyExistsException extends Exception
{
    public function __construct(
        public readonly string $projectId,
    ) {
        parent::__construct(
            'Bu projede zaten aktif bir Sprint bulunuyor. Aynı anda yalnızca 1 aktif Sprint olabilir.',
            422,
        );
    }

    public function render(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'error' => 'active_sprint_exists',
            'message' => $this->getMessage(),
            'project_id' => $this->projectId,
        ], 422);
    }
}
