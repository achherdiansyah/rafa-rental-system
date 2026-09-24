<?php

namespace App\Actions\Auth;

use Illuminate\Support\Facades\Password;

class ForgotPasswordAction
{
    /**
     * Send password reset link to user.
     *
     * @param  array{email: string}  $credentials
     */
    public function execute(array $credentials): string
    {
        // Password broker handles sending notification via configured mailer
        Password::sendResetLink($credentials);

        // Always return generic success to prevent email enumeration attacks
        return 'Tautan reset kata sandi telah dikirimkan ke email Anda jika terdaftar di sistem.';
    }
}
