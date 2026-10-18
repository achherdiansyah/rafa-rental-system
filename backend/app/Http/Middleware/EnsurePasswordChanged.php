<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->must_change_password) {
            return $next($request);
        }

        $allowed = $request->is('api/v1/auth/me')
            || $request->is('api/v1/auth/logout')
            || $request->is('api/v1/profile/password')
            || ($request->isMethod('get') && $request->is('api/v1/profile'));

        if ($allowed) {
            return $next($request);
        }

        return ApiResponse::error(
            'Anda wajib mengganti password sementara sebelum melanjutkan.',
            null,
            423,
            'PASSWORD_CHANGE_REQUIRED'
        );
    }
}
