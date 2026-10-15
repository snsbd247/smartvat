<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Accounting\Services\AccountResolver;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Inventory\Models\InventoryTransaction;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockCount;
use App\Shared\Numbering\NumberGenerator;
use App\Shared\Support\Num;
use App\Shared\Tenancy\CurrentCompany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Inventory documents (receipt / issue / transfer / adjustment). Each operation is
 * atomic across BOTH engines: the stock side (StockService) and the GL side
 * (PostingService) are committed together, so inventory value always reconciles to
 * the Inventory GL account. Customer-owned stock is never mixed with company value.
 */
class InventoryService
{
    public function __construct(
        private CurrentCompany $current,
        private StockService $stock,
        private PostingService $posting,
        private AccountResolver $accounts,
        private NumberGenerator $numbers,
    ) {}

    /**
     * Goods receipt (inbound). Dr Inventory / Cr GRN clearing.
     *
     * @param  array{date?:string, warehouse_id:int, reference?:string, narration?:string,
     *   lines: array<int, array{product_id:int, qty:float|string, unit_cost:float|string, batch_id?:int}>}  $data
     */
    public function receipt(array $data): InventoryTransaction
    {
        return DB::transaction(function () use ($data) {
            $txn = $this->header(InventoryTransaction::TYPE_RECEIPT, $data, 'GRN-');

            $stockLines = [];
            $total = Num::zero();
            foreach ($this->requireLines($data) as $line) {
                $qty = (string) $line['qty'];
                $cost = (string) $line['unit_cost'];
                $this->assertPositive($qty, 'receipt qty');
                $total = Num::add($total, Num::mul($qty, $cost));
                $stockLines[] = $this->stockLine($txn, $data['warehouse_id'], $line['product_id'], $qty, $cost, $line['batch_id'] ?? 0);
            }

            $this->stock->apply($stockLines);

            $this->posting->post([
                'date' => $txn->date,
                'narration' => "Goods receipt {$txn->txn_no}",
                'source_type' => 'inventory_transaction',
                'source_id' => $txn->id,
                'purpose' => 'inventory',
                'lines' => [
                    ['account_id' => $this->accounts->id('inventory'), 'debit' => $total],
                    ['account_id' => $this->accounts->id('grn_clearing'), 'credit' => $total],
                ],
            ]);

            return $txn;
        });
    }

    /**
     * Material issue (outbound), valued at weighted-average cost. Dr COGS / Cr Inventory.
     *
     * @param  array{date?:string, warehouse_id:int, reference?:string, narration?:string,
     *   lines: array<int, array{product_id:int, qty:float|string, batch_id?:int}>}  $data
     */
    public function issue(array $data): InventoryTransaction
    {
        return DB::transaction(function () use ($data) {
            $txn = $this->header(InventoryTransaction::TYPE_ISSUE, $data, 'ISS-');

            $stockLines = [];
            foreach ($this->requireLines($data) as $line) {
                $qty = (string) $line['qty'];
                $this->assertPositive($qty, 'issue qty');
                // Outbound = negative signed qty.
                $stockLines[] = $this->stockLine($txn, $data['warehouse_id'], $line['product_id'], Num::sub(0, $qty), null, $line['batch_id'] ?? 0);
            }

            $movements = $this->stock->apply($stockLines);

            // Total value issued = sum of |movement value| (valued at avg cost).
            $total = Num::zero();
            foreach ($movements as $m) {
                $total = Num::add($total, Num::sub(0, (string) $m->value)); // value is negative on out
            }

            $this->posting->post([
                'date' => $txn->date,
                'narration' => "Material issue {$txn->txn_no}",
                'source_type' => 'inventory_transaction',
                'source_id' => $txn->id,
                'purpose' => 'inventory',
                'lines' => [
                    ['account_id' => $this->accounts->id('cogs'), 'debit' => $total],
                    ['account_id' => $this->accounts->id('inventory'), 'credit' => $total],
                ],
            ]);

            return $txn;
        });
    }

    /**
     * Stock adjustment (signed deltas). Posts the net value change against the
     * inventory adjustment account.
     *
     * @param  array{date?:string, warehouse_id:int, reference?:string, narration?:string,
     *   lines: array<int, array{product_id:int, qty:float|string, unit_cost?:float|string, batch_id?:int}>}  $data
     */
    public function adjustment(array $data): InventoryTransaction
    {
        return DB::transaction(function () use ($data) {
            $txn = $this->header(InventoryTransaction::TYPE_ADJUSTMENT, $data, 'ADJ-');

            $stockLines = [];
            foreach ($this->requireLines($data) as $line) {
                $qty = (string) $line['qty'];
                if (Num::isZero($qty)) {
                    throw new InvalidArgumentException('Adjustment qty cannot be zero.');
                }
                $cost = isset($line['unit_cost']) ? (string) $line['unit_cost'] : null;
                $stockLines[] = $this->stockLine($txn, $data['warehouse_id'], $line['product_id'], $qty, $cost, $line['batch_id'] ?? 0);
            }

            $movements = $this->stock->apply($stockLines);

            $net = Num::zero();
            foreach ($movements as $m) {
                $net = Num::add($net, (string) $m->value); // signed
            }

            if (! Num::isZero($net)) {
                $inventory = $this->accounts->id('inventory');
                $adjust = $this->accounts->id('inventory_adjustment');

                $lines = Num::cmp($net, 0) > 0
                    // Inventory value increased.
                    ? [
                        ['account_id' => $inventory, 'debit' => $net],
                        ['account_id' => $adjust, 'credit' => $net],
                    ]
                    // Inventory value decreased.
                    : [
                        ['account_id' => $adjust, 'debit' => Num::sub(0, $net)],
                        ['account_id' => $inventory, 'credit' => Num::sub(0, $net)],
                    ];

                $this->posting->post([
                    'date' => $txn->date,
                    'narration' => "Stock adjustment {$txn->txn_no}",
                    'source_type' => 'inventory_transaction',
                    'source_id' => $txn->id,
                    'purpose' => 'inventory',
                    'lines' => $lines,
                ]);
            }

            return $txn;
        });
    }

    /**
     * Physical stock count. Snapshots the system quantity for each counted product,
     * records the count sheet with per-line variance, and posts a single stock
     * adjustment for the net differences (increases use the current average cost).
     *
     * @param  array{warehouse_id:int, date?:string, note?:string,
     *   lines: array<int, array{product_id:int, counted_qty:float|string}>}  $data
     */
    public function physicalCount(array $data): StockCount
    {
        $lines = $data['lines'] ?? [];
        if ($lines === []) {
            throw new InvalidArgumentException('A stock count needs at least one line.');
        }

        return DB::transaction(function () use ($data, $lines) {
            $companyId = $this->current->idOrFail();
            $warehouseId = (int) $data['warehouse_id'];

            $count = StockCount::create([
                'company_id' => $companyId,
                'count_no' => $this->numbers->next('stock_count', 'PCT-'),
                'warehouse_id' => $warehouseId,
                'date' => Carbon::parse($data['date'] ?? now())->toDateString(),
                'status' => 'posted',
                'note' => $data['note'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $adjLines = [];
            foreach ($lines as $l) {
                $productId = (int) $l['product_id'];
                $system = (string) (StockBalance::query()
                    ->where('product_id', $productId)
                    ->where('warehouse_id', $warehouseId)
                    ->where('owner_type', 'company')
                    ->where('batch_id', 0)
                    ->value('qty') ?? 0);
                $counted = (string) $l['counted_qty'];
                $variance = Num::sub($counted, $system);

                $count->lines()->create([
                    'company_id' => $companyId,
                    'product_id' => $productId,
                    'system_qty' => $system,
                    'counted_qty' => $counted,
                    'variance' => $variance,
                ]);

                if (! Num::isZero($variance)) {
                    $adjLine = ['product_id' => $productId, 'qty' => $variance];
                    if (Num::cmp($variance, 0) > 0) {
                        // A surplus is valued at the current average cost (fallback 0).
                        $adjLine['unit_cost'] = $this->stock->averageCost($productId, $warehouseId) ?: '0';
                    }
                    $adjLines[] = $adjLine;
                }
            }

            if ($adjLines !== []) {
                $this->adjustment([
                    'warehouse_id' => $warehouseId,
                    'date' => $count->date->toDateString(),
                    'reference' => $count->count_no,
                    'lines' => $adjLines,
                ]);
            }

            return $count->load('lines');
        });
    }

    /**
     * Warehouse-to-warehouse transfer at current average cost. No GL impact (company
     * inventory value is unchanged); stock value simply moves between warehouses.
     *
     * @param  array{date?:string, warehouse_id:int, to_warehouse_id:int, reference?:string,
     *   lines: array<int, array{product_id:int, qty:float|string, batch_id?:int}>}  $data
     */
    public function transfer(array $data): InventoryTransaction
    {
        if (empty($data['to_warehouse_id']) || $data['to_warehouse_id'] == $data['warehouse_id']) {
            throw new InvalidArgumentException('Transfer requires a different destination warehouse.');
        }

        return DB::transaction(function () use ($data) {
            $txn = $this->header(InventoryTransaction::TYPE_TRANSFER, $data, 'TRF-');

            foreach ($this->requireLines($data) as $line) {
                $qty = (string) $line['qty'];
                $this->assertPositive($qty, 'transfer qty');
                $batch = $line['batch_id'] ?? 0;

                // Out of source at average cost.
                $out = $this->stock->apply([
                    $this->stockLine($txn, $data['warehouse_id'], $line['product_id'], Num::sub(0, $qty), null, $batch),
                ])[0];

                // In to destination at the same unit cost so total value is preserved.
                $unitCost = (string) $out->unit_cost;
                $this->stock->apply([
                    $this->stockLine($txn, $data['to_warehouse_id'], $line['product_id'], $qty, $unitCost, $batch),
                ]);
            }

            return $txn;
        });
    }

    // ---- helpers ------------------------------------------------------------

    private function header(string $type, array $data, string $prefix): InventoryTransaction
    {
        return InventoryTransaction::create([
            'company_id' => $this->current->idOrFail(),
            'type' => $type,
            'txn_no' => $this->numbers->next("inventory_{$type}", $prefix),
            'date' => Carbon::parse($data['date'] ?? now())->toDateString(),
            'warehouse_id' => $data['warehouse_id'],
            'to_warehouse_id' => $data['to_warehouse_id'] ?? null,
            'reference' => $data['reference'] ?? null,
            'narration' => $data['narration'] ?? null,
            'status' => 'posted',
            'created_by' => auth()->id(),
        ]);
    }

    private function stockLine(InventoryTransaction $txn, int $warehouseId, int $productId, string $qty, ?string $unitCost, int $batchId): array
    {
        return array_filter([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'qty' => $qty,
            'unit_cost' => $unitCost,
            'batch_id' => $batchId,
            'source_type' => 'inventory_transaction',
            'source_id' => $txn->id,
            'reference' => $txn->txn_no,
        ], fn ($v) => $v !== null);
    }

    private function requireLines(array $data): array
    {
        if (empty($data['lines'])) {
            throw new InvalidArgumentException('At least one line is required.');
        }

        return $data['lines'];
    }

    private function assertPositive(string $qty, string $label): void
    {
        if (Num::cmp($qty, 0) <= 0) {
            throw new InvalidArgumentException("{$label} must be positive.");
        }
    }
}
