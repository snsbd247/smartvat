<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->beforeEach(function () {
        // Fresh roles/permissions for every test (DB is reset by RefreshDatabase).
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        (new \Database\Seeders\RolesAndPermissionsSeeder)->run();
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

use App\Models\User;
use App\Modules\Organization\Models\Company;
use Spatie\Permission\PermissionRegistrar;

/** Create a company (tenant). */
function makeCompany(string $code = 'C1'): Company
{
    return Company::create([
        'code' => $code,
        'name' => $code.' Company',
        'base_currency' => 'BDT',
        'country' => 'BD',
        'status' => 'active',
    ]);
}

/** Create an active user bound to a company, optionally with a role in that company. */
function makeUserInCompany(Company $company, ?string $role = null): User
{
    $user = User::factory()->create(['status' => 'active']);
    $company->users()->attach($user->id, ['is_default' => true]);

    if ($role !== null) {
        app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);
        $user->assignRole($role);
    }

    return $user;
}

/** Set the active tenant for service-level (non-HTTP) tests. */
function setCompanyContext(Company $company): void
{
    app(\App\Shared\Tenancy\CurrentCompany::class)->set($company);
    app(PermissionRegistrar::class)->setPermissionsTeamId($company->id);
}

/** Create a postable account in the current/given company. */
function makeAccount(Company $company, string $code, string $type): \App\Modules\Accounting\Models\Account
{
    return \App\Modules\Accounting\Models\Account::create([
        'company_id' => $company->id,
        'code' => $code,
        'name' => ucfirst($type).' '.$code,
        'type' => $type,
        'normal_balance' => \App\Modules\Accounting\Models\Account::normalBalanceFor($type),
        'is_postable' => true,
        'status' => 'active',
    ]);
}

/** Create a product (defaults to weighted-average costing). */
function makeProduct(Company $company, string $code = 'P1'): \App\Modules\MasterData\Models\Product
{
    return \App\Modules\MasterData\Models\Product::create([
        'company_id' => $company->id,
        'code' => $code,
        'name' => "Product $code",
        'type' => 'raw',
        'base_uom' => 'KG',
        'costing_method' => 'weighted_average',
        'status' => 'active',
    ]);
}

/** Create a warehouse directly in a company. */
function makeWarehouse(Company $company, string $code = 'WH1'): \App\Modules\Organization\Models\Warehouse
{
    return \App\Modules\Organization\Models\Warehouse::create([
        'company_id' => $company->id,
        'code' => $code,
        'name' => "Warehouse $code",
        'status' => 'active',
    ]);
}

/** Create the inventory GL accounts and the company account map for inventory posting. */
function mapInventoryAccounts(Company $company): void
{
    $defs = [
        'inventory' => ['1300', 'asset'],
        'grn_clearing' => ['2050', 'liability'],
        'cogs' => ['5000', 'expense'],
        'inventory_adjustment' => ['5100', 'expense'],
        'accounts_payable' => ['2000', 'liability'],
        'landed_cost_clearing' => ['2060', 'liability'],
        'accounts_receivable' => ['1200', 'asset'],
        'sales_revenue' => ['4000', 'income'],
        'export_revenue' => ['4100', 'income'],
        'exchange_gain_loss' => ['4200', 'income'],
    ];

    foreach ($defs as $key => [$code, $type]) {
        $account = makeAccount($company, $code, $type);
        \App\Modules\Accounting\Models\CompanyAccountMap::create([
            'company_id' => $company->id,
            'key' => $key,
            'account_id' => $account->id,
        ]);
    }
}
