<?php

use App\Modules\Accounting\Services\AgingService;
use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Services\SalesService;

beforeEach(function () {
    $this->company = makeCompany('ACME');
    setCompanyContext($this->company);
    mapInventoryAccounts($this->company);
    $this->product = makeProduct($this->company, 'FG1');
    $this->customer = Customer::create(['company_id' => $this->company->id, 'code' => 'C1', 'name' => 'Buyer', 'status' => 'active']);
});

it('buckets an outstanding invoice by age and nets FIFO receipts', function () {
    $svc = app(SalesService::class);

    // Old invoice (100 days ago) 1000, newer invoice (10 days ago) 500.
    $old = $svc->postInvoice(['customer_id' => $this->customer->id, 'date' => now()->subDays(100)->toDateString(),
        'lines' => [['product_id' => $this->product->id, 'qty' => 1, 'unit_price' => 1000, 'line_total' => 1000]]]);
    $svc->postInvoice(['customer_id' => $this->customer->id, 'date' => now()->subDays(10)->toDateString(),
        'lines' => [['product_id' => $this->product->id, 'qty' => 1, 'unit_price' => 500, 'line_total' => 500]]]);

    // A receipt of 1000 should clear the OLD invoice first (FIFO), leaving the 500 current.
    $svc->recordReceipt(['customer_id' => $this->customer->id, 'amount' => 1000, 'received_in_account_id' => makeAccount($this->company, '1010', 'asset')->id]);

    $aging = app(AgingService::class)->receivables();

    expect($aging['rows'])->toHaveCount(1);
    expect((float) $aging['totals']['total'])->toBe(500.0);     // only the newer invoice remains
    expect((float) $aging['totals']['current'])->toBe(500.0);   // bucketed as current (10 days)
    expect((float) $aging['totals']['older'])->toBe(0.0);       // old one cleared
});

it('exposes aging over the API', function () {
    \Laravel\Sanctum\Sanctum::actingAs(makeUserInCompany($this->company, 'admin'));
    app(SalesService::class)->postInvoice(['customer_id' => $this->customer->id, 'date' => now()->subDays(45)->toDateString(),
        'lines' => [['product_id' => $this->product->id, 'qty' => 1, 'unit_price' => 700, 'line_total' => 700]]]);

    $res = $this->withHeaders(['X-Company-Id' => (string) $this->company->id])
        ->getJson('/api/accounting/aging-receivable')->assertOk();

    expect((float) $res->json('totals.d30'))->toBe(700.0);  // 45 days -> 31-60 bucket
});
