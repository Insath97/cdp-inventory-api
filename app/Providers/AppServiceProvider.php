<?php

namespace App\Providers;

use App\Console\Commands\CheckExpiryProducts;
use App\Console\Commands\CheckPendingPurchaseOrders;
use App\Console\Commands\CheckStaleStockTransfers;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    // public function register(): void
    // {
    //     $this->commands([
    //         CheckExpiryProducts::class,
    //         CheckPendingPurchaseOrders::class,
    //         CheckStaleStockTransfers::class,
    //     ]);
    // }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Implicitly grant "Super Admin" or "SUPER ADMIN" role all permissions
        \Illuminate\Support\Facades\Gate::before(function ($user, $ability) {
            return ($user->hasRole('Super Admin') || $user->hasRole('SUPER ADMIN')) ? true : null;
        });

        // Keyed by email+IP (not IP alone) so one person's retries — or several
        // people testing behind the same office/NAT IP — don't lock everyone
        // else out of /v1/login. 10/minute per account is generous for normal
        // mistyped-password retries while still limiting brute force.
        RateLimiter::for('login', function (\Illuminate\Http\Request $request) {
            $key = strtolower((string) $request->input('email')) . '|' . $request->ip();
            return Limit::perMinute(10)->by($key);
        });
    }
}
