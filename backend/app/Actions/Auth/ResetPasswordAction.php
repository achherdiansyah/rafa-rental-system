<?php

namespace App\Actions\Auth;

use App\Enums\ErrorCode;
use App\Exceptions\BusinessRuleException;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ResetPasswordAction
{
    /**
     * Reset user password using token.
     *
     * @param  array{email: string, password: string, password_confirmation: string, token: string}  $credentials
     *
     * @throws BusinessRuleException
     */
    public function execute(array $credentials): string
    {
        $status = Password::reset($credentials, function (User $user, string $password) {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();

            // Revoke all existing tokens for enhanced security on password change
            $user->tokens()->delete();

            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw new BusinessRuleException(
                'Token reset kata sandi tidak valid atau telah kedaluwarsa.',
                ErrorCode::BUSINESS_RULE_VIOLATION,
                ['token' => ['Token tidak valid atau kedaluwarsa.']],
                400
            );
        }

        return 'Kata sandi berhasil diperbarui. Silakan masuk menggunakan kata sandi baru Anda.';
    }
}
