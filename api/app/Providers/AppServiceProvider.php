<?php

namespace App\Providers;

use App\Modules\Organization\Models\Factory;
use App\Modules\Organization\Models\Warehouse;
use App\Modules\Organization\Policies\FactoryPolicy;
use App\Modules\Organization\Policies\WarehousePolicy;
use App\Shared\Tenancy\CurrentCompany;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One tenant context per request lifecycle.
        $this->app->singleton(CurrentCompany::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Policies for module models (not auto-discovered outside app/Policies).
        Gate::policy(Factory::class, FactoryPolicy::class);
        Gate::policy(Warehouse::class, WarehousePolicy::class);

        // Ability used by the Company Setup/Config endpoints.
        Gate::define('configure-company', fn ($user) => $user->can('company.configure'));

        // Super-admin bypasses all permission checks (still tenant-scoped by data).
        Gate::before(function ($user, $ability) {
            return $user->hasRole('super-admin') ? true : null;
        });
    }
}
