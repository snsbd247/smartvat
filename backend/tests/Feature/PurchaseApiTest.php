<?php

use App\Modules\Purchase\Models\Supplier;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->company = makeCompany('ACME');
    setCompanyContext($this->company);
    mapInventoryAccounts($this->company);
    $this->product = makeProduct($this->company, 'RM1');
    $this->wh = makeWarehouse($this->company, 'WH1');
    $this->headers = ['X-Company-Id' => (string) $this->company->id];
});

it('lets an admin create a supplier and forbids a viewer', function () {
    $admin = makeUserInCompany($this->company, 'admin');
    $viewer = makeUserInCompany($this->company, 'viewer');

    Sanctum::actingAs($admin);
    $this->withHeaders($this->headers)
        ->postJson('/api/suppliers', ['code' => 'SUP1', 'name' => 'Resin Co'])
        ->assertCreated();

    Sanctum::actingAs($viewer);
    $this->withHeaders($this->headers)
        ->postJson('/api/suppliers', ['code' => 'SUP2', 'name' => 'Nope'])
        ->assertForbidden();
});

it('runs receipt + material invoice via API and reports supplier outstanding', function () {
    $admin = makeUserInCompany($this->company, 'admin');
    Sanctum::actingAs($admin);

    $supplier = Supplier::create([
        'company_id' => $this->company->id, 'code' => 'SUP1', 'name' => 'Resin Co', 'status' => 'active',
    ]);

    $this->withHeaders($this->headers)->postJson('/api/goods-receipts', [
        'supplier_id' => $supplier->id,
        'warehouse_id' => $this->wh->id,
        'lines' => [['product_id' => $this->product->id, 'qty' => 100, 'unit_cost' => 10]],
    ])->assertCreated();

    $this->withHeaders($this->headers)->postJson('/api/purchase-invoices', [
        'supplier_id' => $supplier->id,
        'type' => 'material',
        'lines' => [['product_id' => $this->product->id, 'line_total' => 1000]],
    ])->assertCreated();

    $ledger = $this->withHeaders($this->headers)
        ->getJson("/api/suppliers/{$supplier->id}/ledger")
        ->assertOk();

    expect((float) $ledger->json('outstanding'))->toBe(1000.0);
});
