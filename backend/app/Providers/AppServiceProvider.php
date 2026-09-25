<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerGates();
        $this->registerRateLimiters();

        // Customize the password reset URL for SPA frontend
        ResetPassword::createUrlUsing(function (User $user, string $token) {
            $frontendUrl = config('app.frontend_url', 'http://localhost:5173');

            return $frontendUrl.'/reset-password?token='.$token.'&email='.urlencode($user->email);
        });
    }

    /**
     * Register global Gates based on Phase 1 Permission Matrix.
     */
    private function registerGates(): void
    {
        // Admin & Owner capabilities
        Gate::define('view-admin-dashboard', fn (User $user) => $user->hasRole(UserRole::ADMIN, UserRole::OWNER));
        Gate::define('manage-equipment', fn (User $user) => $user->hasRole(UserRole::ADMIN, UserRole::OWNER));
        Gate::define('approve-booking', fn (User $user) => $user->hasRole(UserRole::ADMIN, UserRole::OWNER));
        Gate::define('verify-payment', fn (User $user) => $user->hasRole(UserRole::ADMIN, UserRole::OWNER));

        // Admin-exclusive capabilities (Field operations)
        Gate::define('assign-units', fn (User $user) => $user->isAdmin());
        Gate::define('dispatch-unit', fn (User $user) => $user->isAdmin());
        Gate::define('validate-bast', fn (User $user) => $user->isAdmin());
        Gate::define('validate-timesheet', fn (User $user) => $user->isAdmin());
        Gate::define('process-refund', fn (User $user) => $user->isAdmin());

        // Owner-exclusive capabilities (Financial & Governance)
        Gate::define('view-owner-dashboard', fn (User $user) => $user->isOwner());
        Gate::define('view-revenue-reports', fn (User $user) => $user->isOwner());
        Gate::define('view-audit-logs', fn (User $user) => $user->isOwner());
        Gate::define('manage-pricing-master', fn (User $user) => $user->isOwner());
        Gate::define('approve-refund', fn (User $user) => $user->isOwner());
        Gate::define('manage-bank-accounts', fn (User $user) => $user->hasRole(UserRole::ADMIN, UserRole::OWNER));
        Gate::define('decommission-equipment', fn (User $user) => $user->isOwner());
        Gate::define('deactivate-user', fn (User $user) => $user->isOwner());
    }

    /**
     * Register API Rate Limiters.
     */
    private function registerRateLimiters(): void
    {
        // General API limit: 60 requests per minute per IP or authenticated User ID
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Strict limit for Auth endpoints (e.g. login, register, password reset)
        RateLimiter::for('auth', function (Request $request) {
            // 5 attempts per minute per IP
            return Limit::perMinute(5)->by($request->ip());
        });

        // Limit for file uploads to prevent storage flood
        RateLimiter::for('file-upload', function (Request $request) {
            // 10 uploads per minute per user/IP
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });
    }
}
