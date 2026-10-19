<?php

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\LedgerService;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockLayer;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\MasterData\Models\Product;

beforeEach(function () {
    $this->company = makeCompany('ACME');
    setCompanyContext($this->company);
    mapInventoryAccounts($this->company);
    $this->wh = makeWarehouse($this->company, 'WH1');
});

function costingProduct(string $code, string $method): Product
{
    return Product::create([
        'company_id' => test()->company->id, 'code' => $code, 'name' => $code,
        'type' => 'finished', 'base_uom' => 'PCS', 'costing_method' => $method, 'status' => 'active',
    ]);
}

$qtyOf = fn (int $pid, int $wid) => (float) (StockBalance::where('product_id', $pid)->where('warehouse_id', $wid)->where('owner_type', 'company')->value('qty') ?? 0);
$valueOf = fn (int $pid, int $wid) => (float) (StockBalance::where('product_id', $pid)->where('warehouse_id', $wid)->where('owner_type', 'company')->value('value') ?? 0);

it('values an issue FIFO — oldest layer consumed first', function () use ($valueOf) {
    $p = costingProduct('FIFO1', Product::COSTING_FIFO);
    $inv = app(InventoryService::class);
    $inv->receipt(['warehouse_id' => $this->wh->id, 'lines' => [['product_id' => $p->id, 'qty' => 10, 'unit_cost' => 100]]]); // layer A: 10 @ 100
    $inv->receipt(['warehouse_id' => $this->wh->id, 'lines' => [['product_id' => $p->id, 'qty' => 10, 'unit_cost' => 120]]]); // layer B: 10 @ 120

    // Issue 15 -> 10@100 + 5@120 = 1600 COGS. Remaining 5 @ 120 = 600.
    $inv->issue(['warehouse_id' => $this->wh->id, 'lines' => [['product_id' => $p->id, 'qty' => 15]]]);

    expect($valueOf($p->id, $this->wh->id))->toBe(600.0);
    // COGS to the GL = 1600.
    $cogs = (float) app(LedgerService::class)->accountBalance(Account::where('code', '5000')->first());
    expect($cogs)->toBe(1600.0);

    $layers = StockLayer::where('product_id', $p->id)->orderBy('id')->get();
    expect((float) $layers[0]->qty_remaining)->toBe(0.0);   // first layer fully consumed
    expect((float) $layers[1]->qty_remaining)->toBe(5.0);   // 5 left in second layer
});

it('values an issue LIFO — newest layer consumed first', function () use ($valueOf) {
    $p = costingProduct('LIFO1', Product::COSTING_LIFO);
    $inv = app(InventoryService::class);
    $inv->receipt(['warehouse_id' => $this->wh->id, 'lines' => [['product_id' => $p->id, 'qty' => 10, 'unit_cost' => 100]]]);
    $inv->receipt(['warehouse_id' => $this->wh->id, 'lines' => [['product_id' => $p->id, 'qty' => 10, 'unit_cost' => 120]]]);

    // Issue 15 -> 10@120 + 5@100 = 1700 COGS. Remaining 5 @ 100 = 500.
    $inv->issue(['warehouse_id' => $this->wh->id, 'lines' => [['product_id' => $p->id, 'qty' => 15]]]);

    expect($valueOf($p->id, $this->wh->id))->toBe(500.0);
    $cogs = (float) app(LedgerService::class)->accountBalance(Account::where('code', '5000')->first());
    expect($cogs)->toBe(1700.0);
});

it('keeps weighted-average products on the average path (regression)', function () use ($valueOf) {
    $p = makeProduct($this->company, 'WA1');
    $inv = app(InventoryService::class);
    $inv->receipt(['warehouse_id' => $this->wh->id, 'lines' => [['product_id' => $p->id, 'qty' => 10, 'unit_cost' => 100]]]);
    $inv->receipt(['warehouse_id' => $this->wh->id, 'lines' => [['product_id' => $p->id, 'qty' => 10, 'unit_cost' => 120]]]);
    // Average = 110. Issue 15 -> value out 1650, remaining 5 @ 110 = 550.
    $inv->issue(['warehouse_id' => $this->wh->id, 'lines' => [['product_id' => $p->id, 'qty' => 15]]]);

    expect($valueOf($p->id, $this->wh->id))->toBe(550.0);
    expect(StockLayer::where('product_id', $p->id)->count())->toBe(0); // no layers used
});
