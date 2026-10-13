<?php

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\LedgerService;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Services\InventoryReportService;
use App\Modules\Purchase\Models\LandedCostAllocation;
use App\Modules\Purchase\Models\Supplier;
use App\Modules\Purchase\Services\ImportService;
use App\Modules\Purchase\Services\LandedCostService;

beforeEach(function () {
    $this->company = makeCompany('ACME');
    setCompanyContext($this->company);
    mapInventoryAccounts($this->company);
    $this->p1 = makeProduct($this->company, 'P1');
    $this->p2 = makeProduct($this->company, 'P2');
    $this->wh = makeWarehouse($this->company, 'WH1');
    $this->supplier = Supplier::create([
        'company_id' => $this->company->id, 'code' => 'SUP1', 'name' => 'Overseas Co', 'status' => 'active',
    ]);
    $this->imports = app(ImportService::class);
});

function shipment2Lines($self, array $charges, string $currency = 'BDT', float $rate = 1.0)
{
    return $self->imports->createShipment([
        'supplier_id' => $self->supplier->id,
        'warehouse_id' => $self->wh->id,
        'currency' => $currency,
        'exchange_rate' => $rate,
        'lines' => [
            ['product_id' => $self->p1->id, 'qty' => 10, 'fc_unit_price' => 10, 'weight' => 5],
            ['product_id' => $self->p2->id, 'qty' => 10, 'fc_unit_price' => 30, 'weight' => 15],
        ],
        'charges' => $charges,
    ]);
}

it('allocates a charge by value basis proportionally', function () {
    $s = shipment2Lines($this, [
        ['type' => 'freight', 'fc_amount' => 100, 'allocation_basis' => 'value'],
    ]);

    $r = app(LandedCostService::class)->allocate($s, persist: false);
    // goods: 100 and 300 (total 400). freight 100 -> 25 and 75.
    expect((float) $r['lines'][0]['landed_value'])->toBe(125.0);
    expect((float) $r['lines'][1]['landed_value'])->toBe(375.0);
    expect((float) $r['lines'][0]['landed_unit_cost'])->toBe(12.5);
    expect((float) $r['landed_total'])->toBe(500.0);
});

it('allocates by quantity basis equally when quantities are equal', function () {
    $s = shipment2Lines($this, [
        ['type' => 'port', 'fc_amount' => 100, 'allocation_basis' => 'quantity'],
    ]);

    $r = app(LandedCostService::class)->allocate($s, persist: false);
    expect((float) $r['lines'][0]['allocated'])->toBe(50.0);
    expect((float) $r['lines'][1]['allocated'])->toBe(50.0);
});

it('excludes recoverable charges from landed cost', function () {
    $s = shipment2Lines($this, [
        ['type' => 'freight', 'fc_amount' => 100, 'allocation_basis' => 'value', 'recoverable' => false],
        ['type' => 'import_vat', 'fc_amount' => 60, 'allocation_basis' => 'value', 'recoverable' => true],
    ]);

    $r = app(LandedCostService::class)->allocate($s, persist: false);
    // Only the non-recoverable 100 is capitalized.
    expect((float) $r['nonrecoverable_total'])->toBe(100.0);
    expect((float) $r['landed_total'])->toBe(500.0);
});

it('converts foreign currency at the shipment exchange rate', function () {
    $s = shipment2Lines($this, [], 'USD', 120.0);

    $r = app(LandedCostService::class)->allocate($s, persist: false);
    // L1 goods = 10 * 10 * 120 = 12000
    expect((float) $r['lines'][0]['goods_bc'])->toBe(12000.0);
    expect((float) $r['goods_bc_total'])->toBe(48000.0);
});

it('never allocates the same charge twice (idempotent persist)', function () {
    $s = shipment2Lines($this, [
        ['type' => 'freight', 'fc_amount' => 100, 'allocation_basis' => 'value'],
    ]);

    app(LandedCostService::class)->allocate($s, persist: true);
    app(LandedCostService::class)->allocate($s, persist: true);

    // 1 charge x 2 lines = 2 allocation rows, not 4.
    expect(LandedCostAllocation::where('import_shipment_id', $s->id)->count())->toBe(2);
});

it('receives the shipment at landed cost and reconciles to GL', function () {
    $s = shipment2Lines($this, [
        ['type' => 'freight', 'fc_amount' => 100, 'allocation_basis' => 'value'],
    ]);

    $this->imports->receive($s);

    expect($s->fresh()->status)->toBe('received');

    // Stock value total = landed total 500.
    $stockValue = 0.0;
    foreach (StockBalance::all() as $b) {
        $stockValue += (float) $b->value;
    }
    expect($stockValue)->toBe(500.0);

    $recon = app(InventoryReportService::class)->reconciliation();
    expect($recon['in_balance'])->toBeTrue();
    expect((float) $recon['inventory_gl_balance'])->toBe(500.0);

    // Goods value credited to GRN-clearing (liability, natural credit balance = 400).
    expect((float) app(LedgerService::class)->accountBalance(Account::where('code', '2050')->first()))->toBe(400.0);
    // Non-recoverable charges credited to landed-cost clearing = 100.
    expect((float) app(LedgerService::class)->accountBalance(Account::where('code', '2060')->first()))->toBe(100.0);
});

it('blocks receiving a shipment twice', function () {
    $s = shipment2Lines($this, []);
    $this->imports->receive($s);

    expect(fn () => $this->imports->receive($s->fresh()))->toThrow(InvalidArgumentException::class);
});
