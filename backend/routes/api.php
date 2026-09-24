<?php

use App\Http\Controllers\Api\V1\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HealthCheckController;
use App\Http\Controllers\Api\V1\Owner\OwnerUserController;
use App\Http\Controllers\Api\V1\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - V1
|--------------------------------------------------------------------------
|
| Versioned RESTful API endpoints for RAFA Rental System.
| Base Prefix: /api/v1
|
*/

Route::prefix('v1')->name('api.v1.')->group(function () {

    // 1. Infrastructure / Health Check (Public)
    Route::get('/health', HealthCheckController::class)->name('health');

    // 2. Public Authentication Endpoints
    Route::prefix('auth')->name('auth.')->middleware('throttle:auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])->name('register');
        Route::post('/login', [AuthController::class, 'login'])->name('login');
        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('forgot-password');
        Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('reset-password');
    });

    // 3. Authenticated Routes (Sanctum)
    Route::middleware('auth:sanctum')->group(function () {
        // Auth Session management
        Route::prefix('auth')->name('auth.')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
            Route::get('/me', [AuthController::class, 'me'])->name('me');
        });

        // Profile Management
        Route::prefix('profile')->name('profile.')->group(function () {
            Route::get('/', [ProfileController::class, 'show'])->name('show');
            Route::put('/', [ProfileController::class, 'update'])->name('update');
            Route::patch('/', [ProfileController::class, 'update'])->name('update.patch');

            // Profile Verification (Admin/Owner only via Gate in Request)
            Route::post('/verify', [ProfileController::class, 'verify'])->name('verify');
        });

        /*
        |--------------------------------------------------------------------------
        | Admin-Only Routes
        |--------------------------------------------------------------------------
        | Endpoints restricted to ADMIN and OWNER roles.
        */
        Route::middleware('role:ADMIN,OWNER')->prefix('admin')->name('admin.')->group(function () {
            // User Management
            Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
            Route::get('/users/{user}', [AdminUserController::class, 'show'])->name('users.show');
        });

        /*
        |--------------------------------------------------------------------------
        | Owner-Only Routes
        |--------------------------------------------------------------------------
        | Endpoints restricted to OWNER role exclusively.
        */
        Route::middleware('role:OWNER')->prefix('owner')->name('owner.')->group(function () {
            // User Account Management
            Route::post('/users/{user}/deactivate', [OwnerUserController::class, 'deactivate'])->name('users.deactivate');
        });
    });
});
