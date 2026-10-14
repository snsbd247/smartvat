<?php

use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Services\SalesService;

beforeEach(function () {
    $this->company = makeCompany('ACME');
    setCompanyContext($this->company);
    mapInventoryAccounts($this->company);
    $this->product = makeProduct($this->company, 'FG1');
    $this->customer = Customer::create(['company_id' => $this->company->id, 'code' => 'CUS1', 'name' => 'Buyer', 'status' => 'active']);
});

function orderData(): array
{
    return [
        'customer_id' => test()->customer->id,
        'lines' => [
            ['product_id' => test()->product->id, 'qty' => 10, 'unit_price' => 50, 'line_total' => 500],
            ['product_id' => test()->product->id, 'qty' => 2, 'unit_price' => 25, 'line_total' => 50],
        ],
    ];
}

it('creates a quotation that does not touch the ledger', function () {
    $svc = app(SalesService::class);
    $quote = $svc->createOrder(orderData(), 'quotation');

    expect($quote->status)->toBe('quotation');
    expect($quote->so_no)->toStartWith('QO-');
    expect((float) $quote->total)->toBe(550.0);
    expect($quote->lines)->toHaveCount(2);
    // No GL yet.
    expect(\App\Modules\Accounting\Models\JournalEntry::count())->toBe(0);
});

it('confirms a quotation into a firm order', function () {
    $svc = app(SalesService::class);
    $quote = $svc->createOrder(orderData(), 'quotation');
    $order = $svc->confirmOrder($quote);

    expect($order->status)->toBe('confirmed');
});

it('refuses to confirm anything but a quotation', function () {
    $svc = app(SalesService::class);
    $order = $svc->createOrder(orderData(), 'confirmed');

    expect(fn () => $svc->confirmOrder($order))->toThrow(InvalidArgumentException::class);
});

it('invoices a confirmed order and posts Dr AR / Cr Revenue', function () {
    $svc = app(SalesService::class);
    $order = $svc->createOrder(orderData(), 'confirmed');
    $invoice = $svc->invoiceFromOrder($order);

    expect($invoice->sales_order_id)->toBe($order->id);
    expect((float) $invoice->total)->toBe(550.0);
    expect($order->fresh()->status)->toBe('invoiced');

    $ar = \App\Modules\Accounting\Models\Account::where('code', '1200')->first();
    expect((float) app(\App\Modules\Accounting\Services\LedgerService::class)->accountBalance($ar))->toBe(550.0);
});

it('refuses to invoice an unconfirmed quotation', function () {
    $svc = app(SalesService::class);
    $quote = $svc->createOrder(orderData(), 'quotation');

    expect(fn () => $svc->invoiceFromOrder($quote))->toThrow(InvalidArgumentException::class);
});

it('resolves a bound company-scoped model after clearing context (middleware order)', function () {
    $quote = app(SalesService::class)->createOrder(orderData(), 'quotation');

    \Laravel\Sanctum\Sanctum::actingAs(makeUserInCompany($this->company, 'admin'));
    $headers = ['X-Company-Id' => (string) $this->company->id];

    // Simulate a fresh request: the tenant is NOT pre-set, so the request must
    // resolve the company (via middleware) BEFORE route-model binding runs.
    app(\App\Shared\Tenancy\CurrentCompany::class)->clear();

    $this->withHeaders($headers)
        ->postJson("/api/sales-orders/{$quote->id}/confirm")
        ->assertOk()
        ->assertJsonPath('status', 'confirmed');
});

it('exposes the order flow over the API', function () {
    \Laravel\Sanctum\Sanctum::actingAs(makeUserInCompany($this->company, 'admin'));
    $headers = ['X-Company-Id' => (string) $this->company->id];

    $quote = $this->withHeaders($headers)->postJson('/api/sales-orders', [
        ...orderData(),
        'status' => 'quotation',
    ])->assertCreated()->json();

    $this->withHeaders($headers)->postJson("/api/sales-orders/{$quote['id']}/confirm")->assertOk()->assertJsonPath('status', 'confirmed');
    $this->withHeaders($headers)->postJson("/api/sales-orders/{$quote['id']}/invoice")->assertCreated();

    $this->withHeaders($headers)->getJson('/api/sales-orders?status=invoiced')->assertOk()->assertJsonCount(1);
});
