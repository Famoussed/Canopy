<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Auth\AuthenticateUserAction;
use App\Actions\Auth\CreateUserAction;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;

class AuthService
{
    public function __construct(
        private CreateUserAction $createUserAction,
        private AuthenticateUserAction $authenticateAction,
    ) {}

    public function register(array $data): User
    {
        return $this->createUserAction->execute($data);
    }

    /**
     * @throws AuthenticationException
     */
    public function login(array $credentials): User
    {
        return $this->authenticateAction->execute($credentials);
    }

    public function logout(): void
    {
        auth()->guard('web')->logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();
    }
}
