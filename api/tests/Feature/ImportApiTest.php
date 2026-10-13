<?php

use App\Modules\Purchase\Models\Supplier;
use Laravel\Sanctum\Sanctum;

it('creates, previews and receives an import shipment via API', function () {
    $company = makeCompany('ACME');
    setCompanyContext($company);
    app(App\Modules\Organization\Services\CompanyConfigurator::class)->apply($company, ['manufacturing']); // enable inventory module
    mapInventoryAccounts($company);
    $product = makeProduct($company, 'P1');
    $wh = makeWarehouse($company, 'WH1');
    $supplier = Supplier::create([
        'company_id' => $company->id, 'code' => 'SUP1', 'name' => 'Overseas Co', 'status' => 'active',
    ]);
    $admin = makeUserInCompany($company, 'admin');
    Sanctum::actingAs($admin);
    $headers = ['X-Company-Id' => (string) $company->id];

    $shipmentId = $this->withHeaders($headers)->postJson('/api/import-shipments', [
        'supplier_id' => $supplier->id,
        'warehouse_id' => $wh->id,
        'currency' => 'USD',
        'exchange_rate' => 120,
        'lines' => [['product_id' => $product->id, 'qty' => 10, 'fc_unit_price' => 5]],
        'charges' => [['type' => 'freight', 'fc_amount' => 600, 'allocation_basis' => 'value']],
    ])->assertCreated()->json('id');

    // Preview landed cost: goods = 10*5*120 = 6000, + freight 600 = 6600.
    $preview = $this->withHeaders($headers)
        ->getJson("/api/import-shipments/{$shipmentId}/landed-cost")->assertOk();
    expect((float) $preview->json('landed_total'))->toBe(6600.0);

    $this->withHeaders($headers)
        ->postJson("/api/import-shipments/{$shipmentId}/receive")->assertOk();

    $recon = $this->withHeaders($headers)->getJson('/api/inventory/reconciliation')->assertOk();
    expect($recon->json('in_balance'))->toBeTrue();
    expect((float) $recon->json('inventory_gl_balance'))->toBe(6600.0);
});
