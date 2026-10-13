<?php

namespace App\Providers;

use App\Modules\MasterData\Models\Product;
use App\Modules\MasterData\Policies\ProductPolicy;
use App\Modules\Purchase\Models\Supplier;
use App\Modules\Purchase\Policies\SupplierPolicy;
use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Policies\CustomerPolicy;
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

        $this->registerBackupServices();
    }

    private function registerBackupServices(): void
    {
        $this->app->bind(\App\Modules\Backup\Contracts\BackupStorage::class, function () {
            if (config('backup.driver') === 'google') {
                return new \App\Modules\Backup\Storage\GoogleDriveStorage(
                    config('backup.google.folder_id'),
                    config('backup.google.credentials_json'),
                );
            }

            return new \App\Modules\Backup\Storage\LocalBackupStorage(config('backup.path'));
        });

        $this->app->bind(\App\Modules\Backup\Contracts\DatabaseDumper::class, function () {
            $conn = config('database.default');
            $cfg = config("database.connections.{$conn}");
            if (($cfg['driver'] ?? null) === 'sqlite') {
                return new \App\Modules\Backup\Dumpers\SqliteDumper($cfg['database']);
            }

            return new \App\Modules\Backup\Dumpers\MysqlDumper(
                $cfg['host'] ?? '127.0.0.1', (int) ($cfg['port'] ?? 3306),
                $cfg['database'] ?? '', $cfg['username'] ?? '', (string) ($cfg['password'] ?? ''),
            );
        });

        $this->app->bind(\App\Modules\Backup\Services\BackupService::class, function ($app) {
            return new \App\Modules\Backup\Services\BackupService(
                $app->make(\App\Modules\Backup\Contracts\BackupStorage::class),
                $app->make(\App\Modules\Backup\Contracts\DatabaseDumper::class),
                (string) config('backup.encryption_key'),
                (int) config('backup.retention_days'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Policies for module models (not auto-discovered outside app/Policies).
        Gate::policy(Factory::class, FactoryPolicy::class);
        Gate::policy(Warehouse::class, WarehousePolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Supplier::class, SupplierPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);

        // Ability used by the Company Setup/Config endpoints.
        Gate::define('configure-company', fn ($user) => $user->can('company.configure'));

        // Inventory operations / reports.
        Gate::define('inventory-operate', fn ($user) => $user->can('inventory.operate'));
        Gate::define('inventory-view', fn ($user) => $user->can('inventory.view'));

        // Purchase operations / reports.
        Gate::define('purchase-manage', fn ($user) => $user->can('purchase.manage'));
        Gate::define('purchase-view', fn ($user) => $user->can('purchase.view'));

        // Sales operations / reports.
        Gate::define('sales-manage', fn ($user) => $user->can('sales.manage'));
        Gate::define('sales-view', fn ($user) => $user->can('sales.view'));

        // Manufacturing.
        Gate::define('production-manage', fn ($user) => $user->can('production.manage'));
        Gate::define('production-view', fn ($user) => $user->can('production.view'));

        // Accounting.
        Gate::define('accounting-manage', fn ($user) => $user->can('accounting.manage'));
        Gate::define('accounting-view', fn ($user) => $user->can('accounting.view'));

        // Fixed assets.
        Gate::define('assets-manage', fn ($user) => $user->can('asset.manage'));
        Gate::define('assets-view', fn ($user) => $user->can('asset.view'));

        // VAT / Mushak.
        Gate::define('vat-manage', fn ($user) => $user->can('vat.manage'));
        Gate::define('vat-view', fn ($user) => $user->can('vat.view'));

        // VDS.
        Gate::define('vds-manage', fn ($user) => $user->can('vds.manage'));
        Gate::define('vds-view', fn ($user) => $user->can('vds.view'));

        // Job work / contract manufacturing.
        Gate::define('jobwork-manage', fn ($user) => $user->can('jobwork.manage'));
        Gate::define('jobwork-view', fn ($user) => $user->can('jobwork.view'));

        // Reporting + MIS (read).
        Gate::define('reports-view', fn ($user) => $user->can('reports.view'));
        Gate::define('mis-view', fn ($user) => $user->can('mis.view'));

        // Backup & system health.
        Gate::define('backup-view', fn ($user) => $user->can('backup.view'));
        Gate::define('backup-manage', fn ($user) => $user->can('backup.manage'));

        // Super-admin bypasses all permission checks (still tenant-scoped by data).
        Gate::before(function ($user, $ability) {
            return $user->hasRole('super-admin') ? true : null;
        });
    }
}
