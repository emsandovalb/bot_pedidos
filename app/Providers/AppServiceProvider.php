<?php

namespace App\Providers;

use App\Models\IntakeRequest;
use App\Models\NumberLimit;
use App\Models\BranchDailyClosure;
use App\Policies\IntakeRequestPolicy;
use App\Policies\NumberLimitPolicy;
use App\Policies\BranchDailyClosurePolicy;
use Illuminate\Support\Facades\Gate;
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
    }
}
