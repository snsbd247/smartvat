<?php

use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Services\InventoryService;
use App\Modules\Organization\Services\CompanyConfigurator;

beforeEach(function () {
    $this->company = makeCompany('ACME');
    app(CompanyConfigurator::class)->apply($this->company, ['manufacturing']); // enables inventory module
    setCompanyContext($this->company);
    mapInventoryAccounts($this->company);
    $this->p1 = makeProduct($this->company, 'P1');
    $this->p2 = makeProduct($this->company, 'P2');
    $this->wh = makeWarehouse($this->company, 'WH1');

    $inv = app(InventoryService::class);
    $inv->receipt(['warehouse_id' => $this->wh->id, 'lines' => [
        ['product_id' => $this->p1->id, 'qty' => 100, 'unit_cost' => 10],
        ['product_id' => $this->p2->id, 'qty' => 50, 'unit_cost' => 20],
    ]]);
});

$sysQty = fn (int $pid, int $wid) => (float) (StockBalance::where('product_id', $pid)->where('warehouse_id', $wid)
    ->where('owner_type', 'company')->where('batch_id', 0)->value('qty') ?? 0);

it('records variance and adjusts stock to the counted quantity', function () use ($sysQty) {
    $count = app(InventoryService::class)->physicalCount([
        'warehouse_id' => $this->wh->id,
        'lines' => [
            ['product_id' => $this->p1->id, 'counted_qty' => 95],  // shortage of 5
            ['product_id' => $this->p2->id, 'counted_qty' => 53],  // surplus of 3
        ],
    ]);

    expect($count->count_no)->toStartWith('PCT-');
    $l1 = $count->lines->firstWhere('product_id', $this->p1->id);
    expect((float) $l1->system_qty)->toBe(100.0);
    expect((float) $l1->variance)->toBe(-5.0);

    // System stock now matches the count.
    expect($sysQty($this->p1->id, $this->wh->id))->toBe(95.0);
    expect($sysQty($this->p2->id, $this->wh->id))->toBe(53.0);
});

it('records a count with zero variance and makes no change', function () use ($sysQty) {
    app(InventoryService::class)->physicalCount([
        'warehouse_id' => $this->wh->id,
        'lines' => [['product_id' => $this->p1->id, 'counted_qty' => 100]],
    ]);
    expect($sysQty($this->p1->id, $this->wh->id))->toBe(100.0);
});

it('exposes physical count over the API', function () {
    \Laravel\Sanctum\Sanctum::actingAs(makeUserInCompany($this->company, 'admin'));
    $this->withHeaders(['X-Company-Id' => (string) $this->company->id])
        ->postJson('/api/inventory/physical-count', [
            'warehouse_id' => $this->wh->id,
            'lines' => [['product_id' => $this->p1->id, 'counted_qty' => 98]],
        ])->assertCreated()->assertJsonPath('count_no', fn ($v) => str_starts_with($v, 'PCT-'));
});
