<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class OwnerCannotBeRemovedException extends Exception
{
    public function __construct()
    {
        parent::__construct(
            "Proje sahibi projeden çıkarılamaz. Önce sahipliği başka bir kullanıcıya devredin.",
            403,
        );
    }

    public function render(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'error' => 'owner_cannot_be_removed',
            'message' => $this->getMessage(),
        ], 403);
    }
}
