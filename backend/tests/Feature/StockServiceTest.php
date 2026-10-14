<?php

use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Services\StockService;

beforeEach(function () {
    $this->company = makeCompany('ACME');
    setCompanyContext($this->company);
    $this->product = makeProduct($this->company, 'RM1');
    $this->wh = makeWarehouse($this->company, 'WH1');
});

function stock(): StockService
{
    return app(StockService::class);
}

it('records an inbound movement and sets balance value', function () {
    stock()->apply([[
        'product_id' => $this->product->id,
        'warehouse_id' => $this->wh->id,
        'qty' => 100,
        'unit_cost' => 10,
    ]]);

    $bal = StockBalance::first();
    expect((float) $bal->qty)->toBe(100.0);
    expect((float) $bal->value)->toBe(1000.0);
    expect((float) stock()->averageCost($this->product->id, $this->wh->id))->toBe(10.0);
});

it('computes weighted-average cost across inbounds at different prices', function () {
    stock()->apply([[
        'product_id' => $this->product->id, 'warehouse_id' => $this->wh->id,
        'qty' => 100, 'unit_cost' => 10,
    ]]);
    stock()->apply([[
        'product_id' => $this->product->id, 'warehouse_id' => $this->wh->id,
        'qty' => 100, 'unit_cost' => 20,
    ]]);

    // (100*10 + 100*20) / 200 = 15
    expect((float) stock()->averageCost($this->product->id, $this->wh->id))->toBe(15.0);
});

it('values an outbound at the current weighted-average cost', function () {
    stock()->apply([[
        'product_id' => $this->product->id, 'warehouse_id' => $this->wh->id,
        'qty' => 100, 'unit_cost' => 10,
    ]]);
    stock()->apply([[
        'product_id' => $this->product->id, 'warehouse_id' => $this->wh->id,
        'qty' => 100, 'unit_cost' => 20,
    ]]);

    // issue 50 @ avg 15 = 750 out; remaining 150 qty, value 2250
    $move = stock()->apply([[
        'product_id' => $this->product->id, 'warehouse_id' => $this->wh->id,
        'qty' => -50,
    ]])[0];

    expect((float) $move->unit_cost)->toBe(15.0);
    expect((float) $move->value)->toBe(-750.0);

    $bal = StockBalance::first();
    expect((float) $bal->qty)->toBe(150.0);
    expect((float) $bal->value)->toBe(2250.0);
});

it('prevents overselling (negative stock) by default', function () {
    stock()->apply([[
        'product_id' => $this->product->id, 'warehouse_id' => $this->wh->id,
        'qty' => 10, 'unit_cost' => 5,
    ]]);

    expect(fn () => stock()->apply([[
        'product_id' => $this->product->id, 'warehouse_id' => $this->wh->id,
        'qty' => -20,
    ]]))->toThrow(InsufficientStockException::class);

    // Balance unchanged (transaction rolled back).
    expect((float) StockBalance::first()->qty)->toBe(10.0);
});

it('keeps customer-owned stock separate from company-owned', function () {
    stock()->apply([[
        'product_id' => $this->product->id, 'warehouse_id' => $this->wh->id,
        'qty' => 100, 'unit_cost' => 10, // company
    ]]);
    stock()->apply([[
        'product_id' => $this->product->id, 'warehouse_id' => $this->wh->id,
        'qty' => 500, 'unit_cost' => 0, 'owner_type' => 'customer', 'owner_id' => 7,
    ]]);

    expect(StockBalance::count())->toBe(2);

    $company = StockBalance::where('owner_type', 'company')->first();
    $customer = StockBalance::where('owner_type', 'customer')->first();

    expect((float) $company->qty)->toBe(100.0);
    expect((float) $customer->qty)->toBe(500.0);
    expect((int) $customer->owner_id)->toBe(7);
});
