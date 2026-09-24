<?php

namespace App\Actions\Auth;

use App\Enums\ErrorCode;
use App\Exceptions\BusinessRuleException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginUserAction
{
    /**
     * Authenticate user and issue Sanctum token.
     *
     * @param  array{email: string, password: string}  $credentials
     * @return array{user: User, token: string}
     *
     * @throws ValidationException|BusinessRuleException
     */
    public function execute(array $credentials): array
    {
        /** @var User|null $user */
        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials do not match our records.'],
            ]);
        }

        if (! $user->is_active) {
            throw new BusinessRuleException(
                'Akun Anda dinonaktifkan. Silakan hubungi admin.',
                ErrorCode::FORBIDDEN_ACTION,
                [],
                403
            );
        }

        // Generate personal access token
        $token = $user->createToken('auth_token')->plainTextToken;

        $user->load('customerProfile');

        return [
            'user' => $user,
            'token' => $token,
        ];
    }
}
