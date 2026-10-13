<?php

use App\Modules\Organization\Services\CompanyConfigurator;
use Laravel\Sanctum\Sanctum;

it('lets an admin reconfigure business types and re-derive modules', function () {
    $c = makeCompany('X');
    app(CompanyConfigurator::class)->apply($c, ['service']); // starts without inventory
    $admin = makeUserInCompany($c, 'admin');
    Sanctum::actingAs($admin);

    $response = $this->withHeader('X-Company-Id', (string) $c->id)
        ->putJson('/api/company/config', [
            'business_types' => ['manufacturing', 'trading'],
            'primary_business_type' => 'manufacturing',
        ])
        ->assertOk();

    expect($response->json('modules'))->toContain('inventory', 'production');
    expect($response->json('primary_business_type'))->toBe('manufacturing');
});

it('forbids a viewer from reconfiguring the company', function () {
    $c = makeCompany('X');
    app(CompanyConfigurator::class)->apply($c, ['service']);
    $viewer = makeUserInCompany($c, 'viewer');
    Sanctum::actingAs($viewer);

    $this->withHeader('X-Company-Id', (string) $c->id)
        ->putJson('/api/company/config', ['business_types' => ['manufacturing']])
        ->assertForbidden();
});

it('validates business types on reconfigure', function () {
    $c = makeCompany('X');
    app(CompanyConfigurator::class)->apply($c, ['service']);
    $admin = makeUserInCompany($c, 'admin');
    Sanctum::actingAs($admin);

    $this->withHeader('X-Company-Id', (string) $c->id)
        ->putJson('/api/company/config', ['business_types' => ['mining']])
        ->assertStatus(422);
});
