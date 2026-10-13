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
