<?php

use App\Modules\Organization\Services\CompanyConfigurator;
use Laravel\Sanctum\Sanctum;

function configureCompany(App\Modules\Organization\Models\Company $c, array $types): void
{
    app(CompanyConfigurator::class)->apply($c, $types);
}

it('allows a manufacturer to access the inventory (warehouses) module', function () {
    $c = makeCompany('MFG');
    configureCompany($c, ['manufacturing']);
    $admin = makeUserInCompany($c, 'admin');
    Sanctum::actingAs($admin);

    $this->withHeader('X-Company-Id', (string) $c->id)
        ->getJson('/api/warehouses')
        ->assertOk();
});

it('blocks a service company from the inventory module even with permission (403)', function () {
    $c = makeCompany('SVC');
    configureCompany($c, ['service']);
    $admin = makeUserInCompany($c, 'admin'); // admin has warehouse.view, but module is off
    Sanctum::actingAs($admin);

    $this->withHeader('X-Company-Id', (string) $c->id)
        ->getJson('/api/warehouses')
        ->assertForbidden();
});

it('still allows the always-on organization module (factories) for a service company', function () {
    $c = makeCompany('SVC');
    configureCompany($c, ['service']);
    $admin = makeUserInCompany($c, 'admin');
    Sanctum::actingAs($admin);

    $this->withHeader('X-Company-Id', (string) $c->id)
        ->getJson('/api/factories')
        ->assertOk();
});

it('returns the enabled module manifest', function () {
    $c = makeCompany('MFG');
    configureCompany($c, ['manufacturing']);
    $admin = makeUserInCompany($c, 'admin');
    Sanctum::actingAs($admin);

    $response = $this->withHeader('X-Company-Id', (string) $c->id)
        ->getJson('/api/company/modules')
        ->assertOk();

    expect($response->json('modules'))->toContain('inventory', 'production');
});
