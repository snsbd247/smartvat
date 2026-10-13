<?php

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\LedgerService;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Services\InventoryReportService;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Manufacturing\Services\ProductionService;

beforeEach(function () {
    $this->company = makeCompany('ACME');
    setCompanyContext($this->company);
    mapInventoryAccounts($this->company);
    $this->rawA = makeProduct($this->company, 'RAW-A');
    $this->rawB = makeProduct($this->company, 'RAW-B');
    $this->fg = makeProduct($this->company, 'FG');
    $this->wh = makeWarehouse($this->company, 'WH1');

    $inv = app(InventoryService::class);
    $inv->receipt(['warehouse_id' => $this->wh->id, 'lines' => [['product_id' => $this->rawA->id, 'qty' => 100, 'unit_cost' => 10]]]);
    $inv->receipt(['warehouse_id' => $this->wh->id, 'lines' => [['product_id' => $this->rawB->id, 'qty' => 100, 'unit_cost' => 5]]]);

    $this->prod = app(ProductionService::class);

    $this->bom = $this->prod->createBom([
        'product_id' => $this->fg->id,
        'output_qty' => 1,
        'lines' => [
            ['component_product_id' => $this->rawA->id, 'qty' => 2],
            ['component_product_id' => $this->rawB->id, 'qty' => 1],
        ],
    ]);
    $this->order = $this->prod->createOrder([
        'product_id' => $this->fg->id, 'bom_id' => $this->bom->id,
        'warehouse_id' => $this->wh->id, 'planned_qty' => 10,
    ]);
});

function wipBalance(): float
{
    return (float) app(LedgerService::class)->accountBalance(Account::where('code', '1350')->first());
}
function invBalance(): float
{
    return (float) app(LedgerService::class)->accountBalance(Account::where('code', '1300')->first());
}
function tbBalanced2(): bool
{
    $tb = app(LedgerService::class)->trialBalance();
    $d = array_sum(array_map(fn ($r) => (float) $r['debit'], $tb));
    $c = array_sum(array_map(fn ($r) => (float) $r['credit'], $tb));

    return abs($d - $c) < 0.0001;
}

it('issues BOM materials to WIP (Dr WIP / Cr Inventory)', function () {
    $this->prod->issueMaterials($this->order);

    // A: 20*10=200, B: 10*5=50 => 250 consumed.
    expect(wipBalance())->toBe(250.0);
    expect(invBalance())->toBe(1250.0); // 1500 - 250
    expect((float) StockBalance::where('product_id', $this->rawA->id)->first()->qty)->toBe(80.0);
    expect(tbBalanced2())->toBeTrue();
});

it('receives finished goods at WIP/qty cost and nets WIP to zero, reconciled', function () {
    $this->prod->issueMaterials($this->order);
    $order = $this->prod->receiveFinished($this->order->fresh(), 10);

    expect($order->status)->toBe('completed');
    expect((float) $order->produced_qty)->toBe(10.0);
    expect(wipBalance())->toBe(0.0);
    expect(invBalance())->toBe(1500.0); // back to original total

    $fg = StockBalance::where('product_id', $this->fg->id)->first();
    expect((float) $fg->qty)->toBe(10.0);
    expect((float) $fg->value)->toBe(250.0); // unit cost 25

    $recon = app(InventoryReportService::class)->reconciliation();
    expect($recon['in_balance'])->toBeTrue();
    expect(tbBalanced2())->toBeTrue();
});

it('absorbs yield loss into finished-goods unit cost', function () {
    $this->prod->issueMaterials($this->order);
    // Only 8 good units from material meant for 10 => unit cost 250/8 = 31.25.
    $this->prod->receiveFinished($this->order->fresh(), 8, 2);

    $fg = StockBalance::where('product_id', $this->fg->id)->first();
    expect((float) $fg->qty)->toBe(8.0);
    expect((float) $fg->value)->toBe(250.0);
    expect(wipBalance())->toBe(0.0);
    expect(app(InventoryReportService::class)->reconciliation()['in_balance'])->toBeTrue();
});
