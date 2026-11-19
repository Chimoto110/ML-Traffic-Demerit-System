<?php

namespace App\Providers;

use App\Models\DriverProfile;
use App\Policies\DriverPolicy;
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
        // DriverProfile uses DriverPolicy (name differs from the
        // App\Models\DriverProfile -> DriverProfilePolicy convention).
        Gate::policy(DriverProfile::class, DriverPolicy::class);
    }
}
