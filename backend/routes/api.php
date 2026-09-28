<?php

use App\Http\Controllers\Api\V1\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BankAccountController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\CartController;
use App\Http\Controllers\Api\V1\EquipmentMediaController;
use App\Http\Controllers\Api\V1\EquipmentModelController;
use App\Http\Controllers\Api\V1\EquipmentPriceController;
use App\Http\Controllers\Api\V1\EquipmentTypeController;
use App\Http\Controllers\Api\V1\EquipmentUnitController;
use App\Http\Controllers\Api\V1\HealthCheckController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\OutstandingController;
use App\Http\Controllers\Api\V1\Owner\OwnerUserController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PricingCalculationController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ProjectLocationController;
use App\Http\Controllers\Api\V1\RecommendationController;
use App\Http\Controllers\Api\V1\RefundController;
use App\Http\Controllers\Api\V1\RentalController;
use App\Http\Controllers\Api\V1\ReportingController;
use App\Http\Controllers\Api\V1\TimesheetController;
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

    // 3. Public Equipment Catalog & Pricing Simulation Endpoints (Read-only / Calculation)
    Route::prefix('equipment')->name('equipment.')->group(function () {
        Route::get('/types', [EquipmentTypeController::class, 'index'])->name('types.index');
        Route::get('/types/{type}', [EquipmentTypeController::class, 'show'])->name('types.show');
        Route::get('/models', [EquipmentModelController::class, 'index'])->name('models.index');
        Route::get('/models/{model}', [EquipmentModelController::class, 'show'])->name('models.show');
    });

    Route::post('/pricing/calculate', PricingCalculationController::class)->name('pricing.calculate');

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

        // Bank Accounts (Publicly viewable by Users for payment instructions; manageable by Admin/Owner)
        Route::prefix('bank-accounts')->name('bank-accounts.')->group(function () {
            Route::get('/', [BankAccountController::class, 'index'])->name('index');
            Route::get('/{bankAccount}', [BankAccountController::class, 'show'])->name('show');
            Route::post('/', [BankAccountController::class, 'store'])->name('store');
            Route::put('/{bankAccount}', [BankAccountController::class, 'update'])->name('update');
        });

        // Project Locations (Customer project delivery destinations)
        Route::prefix('project-locations')->name('project-locations.')->group(function () {
            Route::get('/', [ProjectLocationController::class, 'index'])->name('index');
            Route::post('/', [ProjectLocationController::class, 'store'])->name('store');
            Route::get('/{projectLocation}', [ProjectLocationController::class, 'show'])->name('show');
            Route::put('/{projectLocation}', [ProjectLocationController::class, 'update'])->name('update');
            Route::patch('/{projectLocation}', [ProjectLocationController::class, 'update'])->name('update.patch');
            Route::delete('/{projectLocation}', [ProjectLocationController::class, 'destroy'])->name('destroy');
        });

        // Recommendation System (Decision support for equipment selection)
        Route::prefix('recommendations')->name('recommendations.')->group(function () {
            Route::get('/', [RecommendationController::class, 'index'])->name('index');
            Route::post('/request', [RecommendationController::class, 'requestRecommendation'])->name('request');
            Route::post('/', [RecommendationController::class, 'requestRecommendation'])->name('store');
            Route::get('/{recommendationRequest}', [RecommendationController::class, 'show'])->name('show');
        });

        // Shopping Cart for Rental Bookings
        Route::prefix('cart')->name('cart.')->group(function () {
            Route::get('/', [CartController::class, 'getCart'])->name('get');
            Route::delete('/', [CartController::class, 'clear'])->name('clear');
            Route::put('/location', [CartController::class, 'updateLocation'])->name('location.update');
            Route::post('/items', [CartController::class, 'addItem'])->name('items.add');
            Route::put('/items/{cartItem}', [CartController::class, 'updateItem'])->name('items.update');
            Route::patch('/items/{cartItem}', [CartController::class, 'updateItem'])->name('items.update.patch');
            Route::delete('/items/{cartItem}', [CartController::class, 'removeItem'])->name('items.remove');
        });

        // Booking Module (Draft -> Submission -> Approval)
        Route::prefix('bookings')->name('bookings.')->group(function () {
            Route::get('/', [BookingController::class, 'index'])->name('index');
            Route::post('/', [BookingController::class, 'store'])->name('store');
            Route::get('/{booking}', [BookingController::class, 'show'])->name('show');
            Route::post('/{booking}/submit', [BookingController::class, 'submit'])->name('submit');
            Route::post('/{booking}/approve', [BookingController::class, 'approve'])->name('approve');
            Route::post('/{booking}/reject', [BookingController::class, 'reject'])->name('reject');
            Route::post('/{booking}/assign-units', [BookingController::class, 'assignUnits'])->name('assign-units');
            Route::post('/{booking}/assignments/{assignment}/replace', [BookingController::class, 'replaceUnit'])->name('replace-unit');
            Route::post('/{booking}/extend-deadline', [BookingController::class, 'extendDeadline'])->name('extend-deadline');
            Route::post('/{booking}/cancel', [BookingController::class, 'cancel'])->name('cancel');
            Route::post('/{booking}/reschedule', [BookingController::class, 'reschedule'])->name('reschedule');
        });

        // Rental Lifecycle (from CONFIRMED booking with assigned units)
        Route::prefix('rentals')->name('rentals.')->group(function () {
            Route::get('/', [RentalController::class, 'index'])->name('index');
            Route::post('/', [RentalController::class, 'store'])->name('store');
            Route::get('/{rental}', [RentalController::class, 'show'])->name('show');
            Route::post('/{rental}/ready', [RentalController::class, 'ready'])->name('ready');
            Route::post('/{rental}/{target}', [RentalController::class, 'transition'])->name('transition');
        });

        // Daily Operational Timesheet
        Route::prefix('timesheets')->name('timesheets.')->group(function () {
            Route::get('/', [TimesheetController::class, 'index'])->name('index');
            Route::post('/', [TimesheetController::class, 'store'])->name('store');
            Route::get('/{timesheet}', [TimesheetController::class, 'show'])->name('show');
            Route::post('/{timesheet}/submit', [TimesheetController::class, 'submit'])->name('submit');
            Route::post('/{timesheet}/signature', [TimesheetController::class, 'sign'])->name('signature');
            Route::post('/{timesheet}/approve', [TimesheetController::class, 'approve'])->name('approve');
            Route::post('/{timesheet}/reject', [TimesheetController::class, 'reject'])->name('reject');
            Route::put('/{timesheet}/revise', [TimesheetController::class, 'revise'])->name('revise');
            Route::get('/{timesheet}/revisions', [TimesheetController::class, 'revisions'])->name('revisions');
        });

        // Invoice Module (creation & lifecycle; payment verification deferred to 10D)
        Route::prefix('invoices')->name('invoices.')->group(function () {
            Route::get('/', [InvoiceController::class, 'index'])->name('index');
            Route::post('/', [InvoiceController::class, 'store'])->name('store');
            Route::get('/{invoice}', [InvoiceController::class, 'show'])->name('show');
            Route::get('/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('pdf');
            Route::post('/{invoice}/issue', [InvoiceController::class, 'issue'])->name('issue');
            Route::post('/{invoice}/mark-unpaid', [InvoiceController::class, 'markUnpaid'])->name('mark-unpaid');
            Route::post('/{invoice}/void', [InvoiceController::class, 'void'])->name('void');
            Route::post('/{invoice}/extend-deadline', [InvoiceController::class, 'extendDeadline'])->name('extend-deadline');
            Route::get('/{invoice}/payments', [PaymentController::class, 'index'])->name('payments.index');
            Route::post('/{invoice}/payments', [PaymentController::class, 'store'])->name('payments.store');
        });

        // Payment detail & admin verification (single-param binding)
        Route::prefix('payments')->name('payments.')->group(function () {
            Route::get('/', [PaymentController::class, 'queue'])->name('queue');
            Route::get('/{payment}', [PaymentController::class, 'show'])->name('show');
            Route::get('/{payment}/proof', [PaymentController::class, 'proof'])->name('proof');
            Route::post('/{payment}/approve', [PaymentController::class, 'approve'])->name('approve');
            Route::post('/{payment}/reject', [PaymentController::class, 'reject'])->name('reject');
        });

        // Refund Module (manual bank transfer; no gateway; history immutable)
        Route::prefix('refunds')->name('refunds.')->group(function () {
            Route::get('/', [RefundController::class, 'index'])->name('index');
            Route::get('/{refund}', [RefundController::class, 'show'])->name('show');
            Route::post('/{refund}/approve', [RefundController::class, 'approve'])->name('approve');
            Route::post('/{refund}/process', [RefundController::class, 'process'])->name('process');
            Route::post('/{refund}/complete', [RefundController::class, 'complete'])->name('complete');
            Route::post('/{refund}/fail', [RefundController::class, 'fail'])->name('fail');
        });

        // Customer outstanding / credit control
        Route::prefix('finance')->name('finance.')->group(function () {
            Route::get('/outstanding/me', [OutstandingController::class, 'mine'])->name('outstanding.mine');
            Route::get('/outstanding', [OutstandingController::class, 'index'])->name('outstanding.index');
        });

        // In-app notifications (database channel; scoped to owner)
        Route::prefix('notifications')->name('notifications.')->group(function () {
            Route::get('/', [NotificationController::class, 'index'])->name('index');
            Route::get('/unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
            Route::get('/deliveries', [NotificationController::class, 'deliveries'])->name('deliveries');
            Route::post('/read-all', [NotificationController::class, 'markAllRead'])->name('read-all');
            Route::post('/{notification}/read', [NotificationController::class, 'markRead'])->name('read');
        });

        // Read-only Reporting (aggregates; business data never mutated)
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/dashboard', [ReportingController::class, 'dashboard'])->name('dashboard');
            Route::get('/bookings', [ReportingController::class, 'bookings'])->name('bookings');
            Route::get('/rentals', [ReportingController::class, 'rentals'])->name('rentals');
            Route::get('/timesheet', [ReportingController::class, 'timesheet'])->name('timesheet');
            Route::get('/financial', [ReportingController::class, 'financial'])->name('financial');
            Route::get('/equipment-utilization', [ReportingController::class, 'equipmentUtilization'])->name('equipment-utilization');
            Route::get('/operational/bookings', [ReportingController::class, 'operationalBookings'])->name('operational.bookings');
            Route::get('/operational/timesheets', [ReportingController::class, 'operationalTimesheets'])->name('operational.timesheets');
            Route::get('/operational/rentals', [ReportingController::class, 'operationalRentals'])->name('operational.rentals');
            Route::get('/operational/activity', [ReportingController::class, 'operationalActivity'])->name('operational.activity');
            Route::get('/operational/equipment', [ReportingController::class, 'operationalEquipment'])->name('operational.equipment');
            Route::get('/financial/invoices', [ReportingController::class, 'financialInvoices'])->name('financial.invoices');
            Route::get('/financial/payments', [ReportingController::class, 'financialPayments'])->name('financial.payments');
            Route::get('/financial/partials', [ReportingController::class, 'financialPartials'])->name('financial.partials');
            Route::get('/financial/outstanding', [ReportingController::class, 'financialOutstanding'])->name('financial.outstanding');
            Route::get('/financial/overpayments', [ReportingController::class, 'financialOverpayments'])->name('financial.overpayments');
            Route::get('/financial/refunds', [ReportingController::class, 'financialRefunds'])->name('financial.refunds');
            Route::get('/export/{type}', [ReportingController::class, 'export'])->name('export');
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
