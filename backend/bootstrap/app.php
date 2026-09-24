<?php

use App\Enums\ErrorCode;
use App\Exceptions\DomainException;
use App\Http\Middleware\RequireRole;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => RequireRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                if ($e instanceof ValidationException) {
                    return ApiResponse::validationError(
                        $e->errors(),
                        $e->getMessage()
                    );
                }

                if ($e instanceof AuthenticationException) {
                    return ApiResponse::unauthenticated(
                        $e->getMessage() ?: 'Unauthenticated.'
                    );
                }

                if ($e instanceof AccessDeniedHttpException || $e instanceof AuthorizationException) {
                    return ApiResponse::forbidden(
                        $e->getMessage() ?: 'Forbidden action.'
                    );
                }

                if ($e instanceof NotFoundHttpException || $e instanceof ModelNotFoundException) {
                    return ApiResponse::notFound(
                        'Resource not found.'
                    );
                }

                if ($e instanceof DomainException) {
                    return ApiResponse::error(
                        $e->getMessage(),
                        $e->getErrors(),
                        $e->getHttpStatus(),
                        $e->getErrorCode()
                    );
                }

                if ($e instanceof TooManyRequestsHttpException) {
                    return ApiResponse::error(
                        'Too many requests. Please slow down.',
                        null,
                        429,
                        ErrorCode::RATE_LIMIT_EXCEEDED->value
                    );
                }

                if ($e instanceof HttpException) {
                    return ApiResponse::error(
                        $e->getMessage() ?: 'HTTP error occurred.',
                        null,
                        $e->getStatusCode(),
                        'HTTP_ERROR'
                    );
                }

                // 500 Internal Server Error & Production Safety Check
                $isDebug = config('app.debug', false);
                $message = $isDebug ? $e->getMessage() : 'An internal server error occurred.';
                $errors = $isDebug ? [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ] : [];

                return ApiResponse::error(
                    $message,
                    $errors,
                    500,
                    ErrorCode::INTERNAL_SERVER_ERROR->value
                );
            }
        });
    })->create();
