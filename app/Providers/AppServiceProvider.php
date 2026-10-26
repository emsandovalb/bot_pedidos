<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\User;
use App\Policies\OrderPolicy;
use App\Services\Oria\OriaPlus;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
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
        Gate::policy(Order::class, OrderPolicy::class);

        Gate::define('manage-settings', fn (User $user): bool => $user->canManageSettings());

        // Los webhooks son públicos: tope por IP para que un abuso no sature la cola.
        RateLimiter::for('webhooks', fn (Request $request): Limit => Limit::perMinute(
            (int) config('messaging.webhook_rate_limit_per_minute', 300)
        )->by($request->ip()));

        // "Pagar servicios" en el menú: solo para quien administra un negocio con plan Plus en Oria.
        View::composer('layouts.app', function ($view): void {
            $user = auth()->user();
            $plus = false;
            if ($user?->can('manage-settings')) {
                $oria = app(OriaPlus::class);
                $connection = $oria->connectionFor($user->organization_id);
                $plus = (bool) ($connection ? $oria->refreshed($connection) : null)?->isPlus();
            }
            $view->with('oriaPlus', $plus);
        });
    }
}
