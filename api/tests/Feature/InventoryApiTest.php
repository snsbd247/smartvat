<?php

use App\Modules\Organization\Services\CompanyConfigurator;
use Laravel\Sanctum\Sanctum;

function mfgCompanyWithInventory(string $code): App\Modules\Organization\Models\Company
{
    $c = makeCompany($code);
    app(CompanyConfigurator::class)->apply($c, ['manufacturing']); // enables inventory
    mapInventoryAccounts($c);

    return $c;
}

it('lets an admin create a product and forbids a viewer', function () {
    $c = mfgCompanyWithInventory('ACME');
    $admin = makeUserInCompany($c, 'admin');
    $viewer = makeUserInCompany($c, 'viewer');

    Sanctum::actingAs($admin);
    $this->withHeader('X-Company-Id', (string) $c->id)
        ->postJson('/api/products', ['code' => 'P1', 'name' => 'Resin'])
        ->assertCreated();

    Sanctum::actingAs($viewer);
    $this->withHeader('X-Company-Id', (string) $c->id)
        ->postJson('/api/products', ['code' => 'P2', 'name' => 'Nope'])
        ->assertForbidden();
});

it('runs a receipt through the API and reports a balanced reconciliation', function () {
    $c = mfgCompanyWithInventory('ACME');
    $admin = makeUserInCompany($c, 'admin');
    Sanctum::actingAs($admin);
    $headers = ['X-Company-Id' => (string) $c->id];

    $product = $this->withHeaders($headers)
        ->postJson('/api/products', ['code' => 'RM1', 'name' => 'Resin'])
        ->json('data.id');
    $wh = makeWarehouse($c, 'WH1');

    $this->withHeaders($headers)->postJson('/api/inventory/receipts', [
        'warehouse_id' => $wh->id,
        'lines' => [['product_id' => $product, 'qty' => 100, 'unit_cost' => 10]],
    ])->assertCreated();

    $recon = $this->withHeaders($headers)->getJson('/api/inventory/reconciliation')->assertOk();
    expect($recon->json('in_balance'))->toBeTrue();
    expect((float) $recon->json('inventory_gl_balance'))->toBe(1000.0);
});

it('blocks inventory operations for a company without the inventory module', function () {
    $c = makeCompany('SVC');
    app(CompanyConfigurator::class)->apply($c, ['service']); // no inventory module
    $admin = makeUserInCompany($c, 'admin');
    Sanctum::actingAs($admin);

    $this->withHeader('X-Company-Id', (string) $c->id)
        ->postJson('/api/inventory/receipts', [
            'warehouse_id' => 1,
            'lines' => [['product_id' => 1, 'qty' => 1, 'unit_cost' => 1]],
        ])
        ->assertForbidden();
});
