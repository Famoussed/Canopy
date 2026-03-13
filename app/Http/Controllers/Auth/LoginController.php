<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;

class LoginController extends Controller
{
    public function __construct(private readonly AuthService $service) {}

    public function __invoke(LoginRequest $request): JsonResponse
    {
        $user = $this->service->login($request->validated());

        return (new UserResource($user))
            ->response()
            ->setStatusCode(200);
    }
}
