<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;

class AuthenticateUserAction
{
    /**
     * @throws AuthenticationException
     */
    public function execute(array $credentials): User
    {
        if (! Auth::attempt($credentials)) {
            throw new AuthenticationException('The provided credentials are incorrect.');
        }

        /** @var User */
        return Auth::user();
    }
}
