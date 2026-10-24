<?php

namespace App\Providers;

use App\Models\BranchDailyClosure;
use App\Models\IntakeRequest;
use App\Models\NumberLimit;
use App\Models\Order;
use App\Models\User;
use App\Policies\BranchDailyClosurePolicy;
use App\Policies\IntakeRequestPolicy;
use App\Policies\NumberLimitPolicy;
use App\Policies\OrderPolicy;
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
        Gate::policy(IntakeRequest::class, IntakeRequestPolicy::class);
        Gate::policy(BranchDailyClosure::class, BranchDailyClosurePolicy::class);
        Gate::policy(NumberLimit::class, NumberLimitPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);

        Gate::define('manage-settings', fn (User $user): bool => $user->canManageSettings());

        // Los webhooks son públicos: tope por IP para que un abuso no sature la cola.
        RateLimiter::for('webhooks', fn (Request $request): Limit => Limit::perMinute(
            (int) config('messaging.webhook_rate_limit_per_minute', 300)
        )->by($request->ip()));
    }
}
