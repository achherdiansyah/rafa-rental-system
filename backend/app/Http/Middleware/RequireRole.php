<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return ApiResponse::unauthenticated('Unauthenticated.');
        }

        if (! $user->hasRole(...$roles)) {
            return ApiResponse::forbidden('You do not have the required role permissions.');
        }

        return $next($request);
    }
}
