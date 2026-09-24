<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

abstract class ApiController extends Controller
{
    protected function success(mixed $data = null, string $message = 'Success', int $status = 200, array $meta = []): JsonResponse
    {
        return ApiResponse::success($data, $message, $status, $meta);
    }

    protected function created(mixed $data = null, string $message = 'Created'): JsonResponse
    {
        return ApiResponse::success($data, $message, 201);
    }

    protected function error(string $message, mixed $errors = null, int $status = 400, string $code = 'INTERNAL_SERVER_ERROR'): JsonResponse
    {
        return ApiResponse::error($message, $errors, $status, $code);
    }

    protected function notFound(string $message = 'Resource not found.'): JsonResponse
    {
        return ApiResponse::notFound($message);
    }

    protected function forbidden(string $message = 'Forbidden action.'): JsonResponse
    {
        return ApiResponse::forbidden($message);
    }
}
