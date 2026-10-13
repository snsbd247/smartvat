<?php

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\LedgerService;
use App\Modules\Inventory\Services\InventoryReportService;
use App\Modules\Purchase\Models\Supplier;
use App\Modules\Purchase\Services\PurchaseService;
use App\Modules\Purchase\Services\SupplierLedgerService;
use App\Shared\Support\Num;

beforeEach(function () {
    $this->company = makeCompany('ACME');
    setCompanyContext($this->company);
    mapInventoryAccounts($this->company);
    $this->cash = makeAccount($this->company, '1000', 'asset');
    $this->product = makeProduct($this->company, 'RM1');
    $this->wh = makeWarehouse($this->company, 'WH1');
    $this->supplier = Supplier::create([
        'company_id' => $this->company->id, 'code' => 'SUP1', 'name' => 'Acme Resin Co', 'status' => 'active',
    ]);
    $this->svc = app(PurchaseService::class);
    $this->ledger = app(SupplierLedgerService::class);
});

function tbBalanced(): bool
{
    $tb = app(LedgerService::class)->trialBalance();
    $d = array_sum(array_map(fn ($r) => (float) $r['debit'], $tb));
    $c = array_sum(array_map(fn ($r) => (float) $r['credit'], $tb));

    return abs($d - $c) < 0.0001;
}

it('approves a purchase order', function () {
    $po = $this->svc->createOrder([
        'supplier_id' => $this->supplier->id,
        'lines' => [['product_id' => $this->product->id, 'qty' => 100, 'unit_price' => 10]],
    ]);
    expect($po->status)->toBe('draft');
    expect((float) $po->total)->toBe(1000.0);

    $approved = $this->svc->approveOrder($po);
    expect($approved->status)->toBe('approved');
});

it('receives goods: increases stock and posts to inventory GL, reconciled', function () {
    $this->svc->receiveGoods([
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->wh->id,
        'lines' => [['product_id' => $this->product->id, 'qty' => 100, 'unit_cost' => 10]],
    ]);

    $recon = app(InventoryReportService::class)->reconciliation();
    expect($recon['in_balance'])->toBeTrue();
    expect((float) $recon['inventory_gl_balance'])->toBe(1000.0);
    expect(tbBalanced())->toBeTrue();
});

it('material invoice clears GRN-clearing into AP and raises supplier outstanding', function () {
    $grn = $this->svc->receiveGoods([
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->wh->id,
        'lines' => [['product_id' => $this->product->id, 'qty' => 100, 'unit_cost' => 10]],
    ]);

    $this->svc->postInvoice([
        'supplier_id' => $this->supplier->id,
        'type' => 'material',
        'goods_receipt_note_id' => $grn->id,
        'lines' => [['product_id' => $this->product->id, 'line_total' => 1000]],
    ]);

    // GRN-clearing nets to zero after invoice matches the receipt.
    $clearing = Account::where('code', '2050')->first();
    expect((float) app(LedgerService::class)->accountBalance($clearing))->toBe(0.0);

    // Supplier now owed 1000.
    expect((float) $this->ledger->outstanding($this->supplier->id))->toBe(1000.0);
    expect(tbBalanced())->toBeTrue();
});

it('service invoice debits expense and raises AP, no stock', function () {
    $expense = makeAccount($this->company, '5200', 'expense');

    $this->svc->postInvoice([
        'supplier_id' => $this->supplier->id,
        'type' => 'service',
        'lines' => [['account_id' => $expense->id, 'description' => 'Transport', 'line_total' => 300]],
    ]);

    expect((float) app(LedgerService::class)->accountBalance($expense))->toBe(300.0);
    expect((float) $this->ledger->outstanding($this->supplier->id))->toBe(300.0);
    // No stock created by a service invoice.
    expect(\App\Modules\Inventory\Models\StockBalance::count())->toBe(0);
});

it('supplier payment reduces outstanding', function () {
    $grn = $this->svc->receiveGoods([
        'supplier_id' => $this->supplier->id,
        'warehouse_id' => $this->wh->id,
        'lines' => [['product_id' => $this->product->id, 'qty' => 100, 'unit_cost' => 10]],
    ]);
    $this->svc->postInvoice([
        'supplier_id' => $this->supplier->id, 'type' => 'material', 'goods_receipt_note_id' => $grn->id,
        'lines' => [['product_id' => $this->product->id, 'line_total' => 1000]],
    ]);

    $this->svc->paySupplier([
        'supplier_id' => $this->supplier->id,
        'amount' => 400,
        'paid_from_account_id' => $this->cash->id,
    ]);

    expect((float) $this->ledger->outstanding($this->supplier->id))->toBe(600.0);
    expect((float) app(LedgerService::class)->accountBalance($this->cash))->toBe(-400.0); // cash paid out
    expect(tbBalanced())->toBeTrue();
});
