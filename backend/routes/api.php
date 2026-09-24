<?php

use App\Http\Controllers\Api\V1\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\EquipmentMediaController;
use App\Http\Controllers\Api\V1\EquipmentModelController;
use App\Http\Controllers\Api\V1\EquipmentPriceController;
use App\Http\Controllers\Api\V1\EquipmentTypeController;
use App\Http\Controllers\Api\V1\EquipmentUnitController;
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

    // 3. Public Equipment Catalog Endpoints (Read-only)
    Route::prefix('equipment')->name('equipment.')->group(function () {
        Route::get('/types', [EquipmentTypeController::class, 'index'])->name('types.index');
        Route::get('/types/{type}', [EquipmentTypeController::class, 'show'])->name('types.show');
        Route::get('/models', [EquipmentModelController::class, 'index'])->name('models.index');
        Route::get('/models/{model}', [EquipmentModelController::class, 'show'])->name('models.show');
    });

    // 4. Authenticated Routes (Sanctum)
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

        // Equipment Master Management (Admin/Owner)
        Route::middleware('role:ADMIN,OWNER')->prefix('equipment')->name('equipment.admin.')->group(function () {
            Route::post('/types', [EquipmentTypeController::class, 'store'])->name('types.store');
            Route::put('/types/{type}', [EquipmentTypeController::class, 'update'])->name('types.update');
            Route::patch('/types/{type}', [EquipmentTypeController::class, 'update'])->name('types.update.patch');
            Route::delete('/types/{type}', [EquipmentTypeController::class, 'destroy'])->name('types.destroy');

            Route::post('/models', [EquipmentModelController::class, 'store'])->name('models.store');
            Route::put('/models/{model}', [EquipmentModelController::class, 'update'])->name('models.update');
            Route::patch('/models/{model}', [EquipmentModelController::class, 'update'])->name('models.update.patch');
            Route::delete('/models/{model}', [EquipmentModelController::class, 'destroy'])->name('models.destroy');

            // Equipment Media
            Route::post('/models/{model}/photos', [EquipmentMediaController::class, 'uploadPhoto'])->name('models.photos.upload');
            Route::delete('/models/{model}/photos/{attachment}', [EquipmentMediaController::class, 'deletePhoto'])->name('models.photos.delete');

            // Physical Equipment Units
            Route::get('/units', [EquipmentUnitController::class, 'index'])->name('units.index');
            Route::get('/units/{unit}', [EquipmentUnitController::class, 'show'])->name('units.show');
            Route::post('/units', [EquipmentUnitController::class, 'store'])->name('units.store');
            Route::put('/units/{unit}', [EquipmentUnitController::class, 'update'])->name('units.update');
            Route::patch('/units/{unit}', [EquipmentUnitController::class, 'update'])->name('units.update.patch');
            Route::post('/units/{unit}/status', [EquipmentUnitController::class, 'updateStatus'])->name('units.status');
            Route::delete('/units/{unit}', [EquipmentUnitController::class, 'destroy'])->name('units.destroy');

            // Equipment Pricing (Read for Admin/Owner, Mutate for Owner)
            Route::get('/prices', [EquipmentPriceController::class, 'index'])->name('prices.index');
            Route::get('/prices/{price}', [EquipmentPriceController::class, 'show'])->name('prices.show');
            Route::post('/prices', [EquipmentPriceController::class, 'store'])->name('prices.store');
            Route::put('/prices/{price}', [EquipmentPriceController::class, 'update'])->name('prices.update');
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
