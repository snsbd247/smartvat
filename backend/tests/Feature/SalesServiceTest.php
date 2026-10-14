<?php

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\LedgerService;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Services\InventoryReportService;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Services\CustomerLedgerService;
use App\Modules\Sales\Services\SalesService;
use App\Shared\Support\Num;

beforeEach(function () {
    $this->company = makeCompany('ACME');
    setCompanyContext($this->company);
    mapInventoryAccounts($this->company);
    $this->cash = makeAccount($this->company, '1000', 'asset');
    $this->product = makeProduct($this->company, 'FG1');
    $this->wh = makeWarehouse($this->company, 'WH1');
    $this->customer = Customer::create([
        'company_id' => $this->company->id, 'code' => 'CUS1', 'name' => 'Big Buyer Ltd', 'status' => 'active',
    ]);
    // Opening stock through the inventory engine so Inventory GL = stock value.
    app(InventoryService::class)->receipt([
        'warehouse_id' => $this->wh->id,
        'lines' => [['product_id' => $this->product->id, 'qty' => 100, 'unit_cost' => 10]],
    ]);
    $this->sales = app(SalesService::class);
    $this->ledger = app(CustomerLedgerService::class);
});

function tbOk(): bool
{
    $tb = app(LedgerService::class)->trialBalance();
    $d = array_sum(array_map(fn ($r) => (float) $r['debit'], $tb));
    $c = array_sum(array_map(fn ($r) => (float) $r['credit'], $tb));

    return abs($d - $c) < 0.0001;
}

it('local invoice raises AR and revenue; customer outstanding equals total', function () {
    $this->sales->postInvoice([
        'customer_id' => $this->customer->id,
        'lines' => [['product_id' => $this->product->id, 'qty' => 10, 'unit_price' => 25, 'line_total' => 250]],
    ]);

    expect((float) $this->ledger->outstanding($this->customer->id))->toBe(250.0);
    expect((float) app(LedgerService::class)->accountBalance(Account::where('code', '4000')->first()))->toBe(250.0);
    expect(tbOk())->toBeTrue();
});

it('delivery challan issues stock at cost and posts COGS, staying reconciled', function () {
    $this->sales->deliver([
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->wh->id,
        'lines' => [['product_id' => $this->product->id, 'qty' => 10]],
    ]);

    // Stock reduced 100 -> 90 (value 1000 -> 900); COGS 100.
    expect((float) StockBalance::first()->qty)->toBe(90.0);
    expect((float) app(LedgerService::class)->accountBalance(Account::where('code', '5000')->first()))->toBe(100.0);

    $recon = app(InventoryReportService::class)->reconciliation();
    expect($recon['in_balance'])->toBeTrue();
    expect((float) $recon['inventory_gl_balance'])->toBe(900.0);
    expect(tbOk())->toBeTrue();
});

it('invoice is separate from the delivery challan (distinct documents)', function () {
    $inv = $this->sales->postInvoice([
        'customer_id' => $this->customer->id,
        'lines' => [['product_id' => $this->product->id, 'qty' => 10, 'unit_price' => 25, 'line_total' => 250]],
    ]);
    $dc = $this->sales->deliver([
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->wh->id,
        'sales_invoice_id' => $inv->id,
        'lines' => [['product_id' => $this->product->id, 'qty' => 6]],
    ]);
    $dc2 = $this->sales->deliver([
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->wh->id,
        'sales_invoice_id' => $inv->id,
        'lines' => [['product_id' => $this->product->id, 'qty' => 4]],
    ]);

    // One invoice, two partial delivery challans.
    expect($inv->inv_no)->toStartWith('INV-');
    expect($dc->challan_no)->toStartWith('DC-');
    expect($dc2->challan_no)->not->toBe($dc->challan_no);
    expect((float) StockBalance::first()->qty)->toBe(90.0); // 100 - 6 - 4
});

it('local receipt reduces customer outstanding', function () {
    $this->sales->postInvoice([
        'customer_id' => $this->customer->id,
        'lines' => [['product_id' => $this->product->id, 'qty' => 10, 'unit_price' => 25, 'line_total' => 250]],
    ]);
    $this->sales->recordReceipt([
        'customer_id' => $this->customer->id,
        'amount' => 150,
        'received_in_account_id' => $this->cash->id,
    ]);

    expect((float) $this->ledger->outstanding($this->customer->id))->toBe(100.0);
    expect(tbOk())->toBeTrue();
});

it('sales return brings goods back and issues a credit note', function () {
    $this->sales->postInvoice([
        'customer_id' => $this->customer->id,
        'lines' => [['product_id' => $this->product->id, 'qty' => 10, 'unit_price' => 25, 'line_total' => 250]],
    ]);
    $this->sales->deliver([
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->wh->id,
        'lines' => [['product_id' => $this->product->id, 'qty' => 10]],
    ]); // stock 90

    $this->sales->returnGoods([
        'customer_id' => $this->customer->id,
        'warehouse_id' => $this->wh->id,
        'lines' => [['product_id' => $this->product->id, 'qty' => 4, 'unit_price' => 25]],
    ]);

    // Goods back: 90 -> 94. Credit note reduces AR by 100 (250 - 100 = 150).
    expect((float) StockBalance::first()->qty)->toBe(94.0);
    expect((float) $this->ledger->outstanding($this->customer->id))->toBe(150.0);
    expect(tbOk())->toBeTrue();
});

// ---- Phase 9: Export + FX ----

it('export invoice books AR and export revenue in base currency at the rate', function () {
    $this->sales->postInvoice([
        'customer_id' => $this->customer->id,
        'kind' => 'export',
        'currency' => 'USD',
        'exchange_rate' => 120,
        'lines' => [['product_id' => $this->product->id, 'qty' => 10, 'unit_price' => 5, 'line_total' => 50]],
    ]);

    // 50 USD * 120 = 6000 BDT.
    expect((float) $this->ledger->outstanding($this->customer->id))->toBe(6000.0);
    expect((float) app(LedgerService::class)->accountBalance(Account::where('code', '4100')->first()))->toBe(6000.0);
    expect(tbOk())->toBeTrue();
});

it('export realization books an exchange gain when the rate rises', function () {
    $this->sales->postInvoice([
        'customer_id' => $this->customer->id, 'kind' => 'export', 'currency' => 'USD',
        'exchange_rate' => 120,
        'lines' => [['product_id' => $this->product->id, 'qty' => 10, 'unit_price' => 5, 'line_total' => 50]],
    ]);

    // Receive 50 USD at 125 -> 6250 BDT; AR relieved at invoice rate 120 -> 6000; gain 250.
    $this->sales->recordReceipt([
        'customer_id' => $this->customer->id,
        'kind' => 'export',
        'fc_amount' => 50,
        'realization_rate' => 125,
        'invoice_rate' => 120,
        'received_in_account_id' => $this->cash->id,
    ]);

    expect((float) $this->ledger->outstanding($this->customer->id))->toBe(0.0); // AR fully relieved
    expect((float) app(LedgerService::class)->accountBalance(Account::where('code', '4200')->first()))->toBe(250.0); // gain
    expect(tbOk())->toBeTrue();
});

it('export realization books an exchange loss when the rate falls', function () {
    $this->sales->postInvoice([
        'customer_id' => $this->customer->id, 'kind' => 'export', 'currency' => 'USD',
        'exchange_rate' => 120,
        'lines' => [['product_id' => $this->product->id, 'qty' => 10, 'unit_price' => 5, 'line_total' => 50]],
    ]);
    $this->sales->recordReceipt([
        'customer_id' => $this->customer->id, 'kind' => 'export',
        'fc_amount' => 50, 'realization_rate' => 118, 'invoice_rate' => 120,
        'received_in_account_id' => $this->cash->id,
    ]);

    expect((float) $this->ledger->outstanding($this->customer->id))->toBe(0.0);
    // 50*118=5900 received, AR 6000 relieved -> loss 100 (income account shows -100).
    expect((float) app(LedgerService::class)->accountBalance(Account::where('code', '4200')->first()))->toBe(-100.0);
    expect(tbOk())->toBeTrue();
});
