<?php

use App\Modules\Purchase\Models\Supplier;
use App\Modules\Purchase\Services\PurchaseService;
use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Services\SalesService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->company = makeCompany('ACME');
    setCompanyContext($this->company);
    mapInventoryAccounts($this->company);
    $this->product = makeProduct($this->company, 'FG1');
    $this->customer = Customer::create(['company_id' => $this->company->id, 'code' => 'CUS1', 'name' => 'Buyer', 'status' => 'active']);
    $this->supplier = Supplier::create(['company_id' => $this->company->id, 'code' => 'SUP1', 'name' => 'Seller', 'status' => 'active']);

    // Revenue + AR via a sales invoice.
    app(SalesService::class)->postInvoice([
        'customer_id' => $this->customer->id,
        'lines' => [['product_id' => $this->product->id, 'qty' => 10, 'unit_price' => 50, 'line_total' => 500]],
    ]);
    // A draft purchase order awaiting approval.
    app(PurchaseService::class)->createOrder([
        'supplier_id' => $this->supplier->id,
        'lines' => [['product_id' => $this->product->id, 'qty' => 5, 'unit_price' => 20]],
    ]);

    $this->headers = ['X-Company-Id' => (string) $this->company->id];
});

it('returns executive KPIs', function () {
    Sanctum::actingAs(makeUserInCompany($this->company, 'admin'));

    $res = $this->withHeaders($this->headers)->getJson('/api/mis/executive')->assertOk();

    expect((float) $res->json('revenue'))->toBe(500.0);
    expect((float) $res->json('receivables'))->toBe(500.0);
    expect($res->json('pending_approvals'))->toBe(1);
});

it('lists pending approvals', function () {
    Sanctum::actingAs(makeUserInCompany($this->company, 'admin'));

    $res = $this->withHeaders($this->headers)->getJson('/api/mis/approvals')->assertOk();

    expect($res->json('pending'))->toHaveCount(1);
    expect($res->json('pending.0.type'))->toBe('purchase_order');
});
