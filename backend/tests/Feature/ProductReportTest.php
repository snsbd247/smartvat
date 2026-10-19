<?php

use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Mis\Services\ProductReportService;
use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Services\SalesService;

beforeEach(function () {
    $this->company = makeCompany('ACME');
    setCompanyContext($this->company);
    mapInventoryAccounts($this->company);
    $this->wh = makeWarehouse($this->company, 'WH1');
    $this->p = makeProduct($this->company, 'FG1');
    $this->customer = Customer::create(['company_id' => $this->company->id, 'code' => 'C1', 'name' => 'Buyer', 'status' => 'active']);

    // Stock in at cost 40.
    app(InventoryService::class)->receipt(['warehouse_id' => $this->wh->id, 'lines' => [['product_id' => $this->p->id, 'qty' => 100, 'unit_cost' => 40]]]);
});

it('computes profit by product (revenue vs estimated cost)', function () {
    // Sell 10 @ 100 -> revenue 1000, est cost 10*40 = 400, profit 600, margin 60%.
    app(SalesService::class)->postInvoice([
        'customer_id' => $this->customer->id,
        'lines' => [['product_id' => $this->p->id, 'qty' => 10, 'unit_price' => 100, 'line_total' => 1000]],
    ]);

    $rows = app(ProductReportService::class)->profitByProduct()['rows'];
    $row = collect($rows)->firstWhere('product', 'Product FG1');

    expect((float) $row['revenue'])->toBe(1000.0);
    expect((float) $row['est_cost'])->toBe(400.0);
    expect((float) $row['profit'])->toBe(600.0);
    expect((float) $row['margin_pct'])->toBe(60.0);
});

it('summarises stock movement in/out per product', function () {
    app(InventoryService::class)->issue(['warehouse_id' => $this->wh->id, 'lines' => [['product_id' => $this->p->id, 'qty' => 30]]]);

    $rows = app(ProductReportService::class)->stockMovement()['rows'];
    $row = collect($rows)->firstWhere('product', 'Product FG1');

    expect((float) $row['in_qty'])->toBe(100.0);
    expect((float) $row['out_qty'])->toBe(30.0);
    expect((float) $row['net_qty'])->toBe(70.0);
});

it('exposes both reports over the API', function () {
    \Laravel\Sanctum\Sanctum::actingAs(makeUserInCompany($this->company, 'admin'));
    $h = ['X-Company-Id' => (string) $this->company->id];
    $this->withHeaders($h)->getJson('/api/mis/profit-by-product')->assertOk()->assertJsonStructure(['rows']);
    $this->withHeaders($h)->getJson('/api/mis/stock-movement')->assertOk()->assertJsonStructure(['rows']);
});
