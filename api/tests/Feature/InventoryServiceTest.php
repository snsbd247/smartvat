<?php

use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Services\InventoryReportService;
use App\Modules\Inventory\Services\InventoryService;

beforeEach(function () {
    $this->company = makeCompany('ACME');
    setCompanyContext($this->company);
    mapInventoryAccounts($this->company);
    $this->product = makeProduct($this->company, 'RM1');
    $this->wh = makeWarehouse($this->company, 'WH1');
    $this->inv = app(InventoryService::class);
    $this->reports = app(InventoryReportService::class);
});

it('receipt increases stock and posts to the inventory GL account', function () {
    $this->inv->receipt([
        'warehouse_id' => $this->wh->id,
        'lines' => [['product_id' => $this->product->id, 'qty' => 100, 'unit_cost' => 10]],
    ]);

    $bal = StockBalance::first();
    expect((float) $bal->qty)->toBe(100.0);
    expect((float) $bal->value)->toBe(1000.0);

    $recon = $this->reports->reconciliation();
    expect($recon['in_balance'])->toBeTrue();
    expect((float) $recon['inventory_gl_balance'])->toBe(1000.0);
    expect((float) $recon['stock_value_total'])->toBe(1000.0);
});

it('issue decreases stock and keeps inventory reconciled to GL', function () {
    $this->inv->receipt([
        'warehouse_id' => $this->wh->id,
        'lines' => [['product_id' => $this->product->id, 'qty' => 100, 'unit_cost' => 10]],
    ]);
    $this->inv->issue([
        'warehouse_id' => $this->wh->id,
        'lines' => [['product_id' => $this->product->id, 'qty' => 30]],
    ]);

    $bal = StockBalance::first();
    expect((float) $bal->qty)->toBe(70.0);
    expect((float) $bal->value)->toBe(700.0);

    $recon = $this->reports->reconciliation();
    expect($recon['in_balance'])->toBeTrue();
    expect((float) $recon['inventory_gl_balance'])->toBe(700.0);
});

it('transfer moves value between warehouses with no GL impact and stays reconciled', function () {
    $wh2 = makeWarehouse($this->company, 'WH2');
    $this->inv->receipt([
        'warehouse_id' => $this->wh->id,
        'lines' => [['product_id' => $this->product->id, 'qty' => 100, 'unit_cost' => 10]],
    ]);

    $this->inv->transfer([
        'warehouse_id' => $this->wh->id,
        'to_warehouse_id' => $wh2->id,
        'lines' => [['product_id' => $this->product->id, 'qty' => 40]],
    ]);

    $src = StockBalance::where('warehouse_id', $this->wh->id)->first();
    $dst = StockBalance::where('warehouse_id', $wh2->id)->first();
    expect((float) $src->qty)->toBe(60.0);
    expect((float) $dst->qty)->toBe(40.0);
    expect((float) $dst->value)->toBe(400.0);

    $recon = $this->reports->reconciliation();
    expect($recon['in_balance'])->toBeTrue();
    expect((float) $recon['stock_value_total'])->toBe(1000.0); // total unchanged
});

it('adjustment posts the net value change and stays reconciled', function () {
    $this->inv->receipt([
        'warehouse_id' => $this->wh->id,
        'lines' => [['product_id' => $this->product->id, 'qty' => 100, 'unit_cost' => 10]],
    ]);

    // Write down 10 units (−10 @ avg 10 = −100 value).
    $this->inv->adjustment([
        'warehouse_id' => $this->wh->id,
        'lines' => [['product_id' => $this->product->id, 'qty' => -10]],
    ]);

    $bal = StockBalance::first();
    expect((float) $bal->qty)->toBe(90.0);
    expect((float) $bal->value)->toBe(900.0);

    $recon = $this->reports->reconciliation();
    expect($recon['in_balance'])->toBeTrue();
    expect((float) $recon['inventory_gl_balance'])->toBe(900.0);
});

it('blocks an issue that would oversell and rolls everything back', function () {
    $this->inv->receipt([
        'warehouse_id' => $this->wh->id,
        'lines' => [['product_id' => $this->product->id, 'qty' => 10, 'unit_cost' => 5]],
    ]);

    expect(fn () => $this->inv->issue([
        'warehouse_id' => $this->wh->id,
        'lines' => [['product_id' => $this->product->id, 'qty' => 50]],
    ]))->toThrow(InsufficientStockException::class);

    // Stock and GL unchanged (whole operation rolled back).
    expect((float) StockBalance::first()->qty)->toBe(10.0);
    expect($this->reports->reconciliation()['in_balance'])->toBeTrue();
});
