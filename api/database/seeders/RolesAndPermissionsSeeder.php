<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Permissions are global definitions. Roles are created as global (team_id null)
     * so they can be assigned to users within any company (team) context.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'company.view', 'company.configure',
            'factory.view', 'factory.create', 'factory.update', 'factory.delete',
            'warehouse.view', 'warehouse.create', 'warehouse.update', 'warehouse.delete',
            'product.view', 'product.create', 'product.update', 'product.delete',
            'inventory.view', 'inventory.operate',
            'supplier.view', 'supplier.create', 'supplier.update', 'supplier.delete',
            'purchase.view', 'purchase.manage',
            'customer.view', 'customer.create', 'customer.update', 'customer.delete',
            'sales.view', 'sales.manage',
            'production.view', 'production.manage',
            'accounting.view', 'accounting.manage',
            'asset.view', 'asset.manage',
            'vat.view', 'vat.manage',
            'vds.view', 'vds.manage',
            'jobwork.view', 'jobwork.manage',
        ];

        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // Ensure roles are created without a team context (global).
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        $superAdmin = Role::findOrCreate('super-admin', 'web');
        $admin = Role::findOrCreate('admin', 'web');
        $viewer = Role::findOrCreate('viewer', 'web');

        // super-admin is also short-circuited by Gate::before, but keep it complete.
        $superAdmin->syncPermissions(Permission::all());
        $admin->syncPermissions($permissions);
        $viewer->syncPermissions(array_filter($permissions, fn ($p) => str_ends_with($p, '.view')));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
