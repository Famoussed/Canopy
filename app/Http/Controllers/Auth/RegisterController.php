<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    public function __construct(private AuthService $service) {}

    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $user = $this->service->register($request->validated());

        return (new UserResource($user))
            ->response()
            ->setStatusCode(201);
    }
}
