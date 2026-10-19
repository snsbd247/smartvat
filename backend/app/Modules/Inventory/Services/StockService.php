<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockLayer;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\MasterData\Models\Product;
use App\Shared\Support\Num;
use App\Shared\Tenancy\CurrentCompany;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The single server-side entry point for stock changes. Each call is one DB
 * transaction; every affected balance row is locked FOR UPDATE so concurrent
 * movements cannot oversell or corrupt the balance. Movements are append-only.
 *
 * Costing: weighted-average is fully supported. Inbound lines carry a unit cost;
 * outbound lines are valued at the current weighted-average cost.
 */
class StockService
{
    public function __construct(private CurrentCompany $current) {}

    /**
     * Apply one or more stock movements atomically.
     *
     * @param  array<int, array{
     *   product_id:int, warehouse_id:int, qty:float|string,
     *   unit_cost?:float|string, batch_id?:int, owner_type?:string, owner_id?:int,
     *   source_type?:string|null, source_id?:int|null, reference?:string|null,
     *   allow_negative?:bool
     * }>  $lines  qty is signed: positive = in, negative = out
     * @return array<int, StockMovement>
     */
    public function apply(array $lines): array
    {
        if ($lines === []) {
            throw new RuntimeException('No stock movement lines provided.');
        }

        $companyId = $this->current->idOrFail();

        return DB::transaction(function () use ($lines, $companyId) {
            $movements = [];

            foreach ($lines as $line) {
                $movements[] = $this->applyLine($companyId, $line);
            }

            return $movements;
        });
    }

    private function applyLine(int $companyId, array $line): StockMovement
    {
        $productId = (int) $line['product_id'];
        $warehouseId = (int) $line['warehouse_id'];
        $batchId = (int) ($line['batch_id'] ?? 0);
        $ownerType = $line['owner_type'] ?? 'company';
        $ownerId = (int) ($line['owner_id'] ?? 0);
        $qty = (string) $line['qty'];
        $allowNegative = (bool) ($line['allow_negative'] ?? false);

        $product = Product::findOrFail($productId);
        $method = $product->costing_method;
        $usesLayers = in_array($method, [Product::COSTING_FIFO, Product::COSTING_LIFO], true);
        if (! $usesLayers && $method !== Product::COSTING_WEIGHTED_AVERAGE) {
            throw new RuntimeException("Unknown costing method '{$method}'.");
        }

        // Lock (or create) the balance row.
        $balance = StockBalance::query()
            ->where('company_id', $companyId)
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('batch_id', $batchId)
            ->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->lockForUpdate()
            ->first();

        if ($balance === null) {
            $balance = StockBalance::create([
                'company_id' => $companyId,
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'batch_id' => $batchId,
                'owner_type' => $ownerType,
                'owner_id' => $ownerId,
                'qty' => 0,
                'value' => 0,
            ]);
            // Re-lock the freshly created row.
            $balance = StockBalance::whereKey($balance->id)->lockForUpdate()->first();
        }

        $curQty = (string) $balance->qty;
        $curValue = (string) $balance->value;

        if (Num::cmp($qty, 0) > 0) {
            // Inbound.
            $unitCost = (string) ($line['unit_cost'] ?? 0);
            $moveValue = Num::mul($qty, $unitCost);
            $newQty = Num::add($curQty, $qty);
            $newValue = Num::add($curValue, $moveValue);
            $recordedUnitCost = $unitCost;
            $signedValue = $moveValue;

            if ($usesLayers) {
                StockLayer::create([
                    'company_id' => $companyId,
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'batch_id' => $batchId,
                    'owner_type' => $ownerType,
                    'owner_id' => $ownerId,
                    'unit_cost' => $unitCost,
                    'qty_received' => $qty,
                    'qty_remaining' => $qty,
                    'received_at' => now(),
                ]);
            }
        } else {
            // Outbound (qty negative).
            $outQty = Num::sub(0, $qty); // positive magnitude
            if (! $allowNegative && Num::cmp($outQty, $curQty) > 0) {
                throw new InsufficientStockException($productId, $warehouseId, $curQty, $outQty);
            }

            if ($usesLayers) {
                // Consume layers FIFO (oldest first) or LIFO (newest first).
                $outValue = $this->consumeLayers($companyId, $productId, $warehouseId, $batchId, $ownerType, $ownerId, $outQty, $method, $curQty, $curValue);
            } else {
                $avg = Num::cmp($curQty, 0) > 0 ? Num::div($curValue, $curQty) : Num::zero();
                $outValue = Num::mul($outQty, $avg);
            }

            $newQty = Num::sub($curQty, $outQty);
            $newValue = Num::sub($curValue, $outValue);
            $recordedUnitCost = Num::cmp($outQty, 0) > 0 ? Num::div($outValue, $outQty) : Num::zero();
            $signedValue = Num::sub(0, $outValue);
        }

        $balance->update(['qty' => $newQty, 'value' => $newValue]);

        return StockMovement::create([
            'company_id' => $companyId,
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'batch_id' => $batchId,
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
            'qty' => $qty,
            'unit_cost' => $recordedUnitCost,
            'value' => $signedValue,
            'balance_qty' => $newQty,
            'balance_value' => $newValue,
            'source_type' => $line['source_type'] ?? null,
            'source_id' => $line['source_id'] ?? null,
            'reference' => $line['reference'] ?? null,
            'moved_at' => now(),
            'created_by' => auth()->id(),
            'created_at' => now(),
        ]);
    }

    /**
     * Consume cost layers for an outbound movement. FIFO uses the oldest layers
     * first, LIFO the newest. Returns the total cost (COGS) of the quantity issued.
     */
    private function consumeLayers(
        int $companyId, int $productId, int $warehouseId, int $batchId,
        string $ownerType, int $ownerId, string $outQty, string $method,
        string $curQty, string $curValue,
    ): string {
        $layers = StockLayer::query()
            ->where('company_id', $companyId)
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('batch_id', $batchId)
            ->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->where('qty_remaining', '>', 0)
            ->orderBy('received_at', $method === Product::COSTING_LIFO ? 'desc' : 'asc')
            ->orderBy('id', $method === Product::COSTING_LIFO ? 'desc' : 'asc')
            ->lockForUpdate()
            ->get();

        $remaining = $outQty;
        $cost = Num::zero();

        foreach ($layers as $layer) {
            if (Num::cmp($remaining, 0) <= 0) {
                break;
            }
            $take = Num::cmp((string) $layer->qty_remaining, $remaining) <= 0 ? (string) $layer->qty_remaining : $remaining;
            $cost = Num::add($cost, Num::mul($take, (string) $layer->unit_cost));
            $layer->update(['qty_remaining' => Num::sub((string) $layer->qty_remaining, $take)]);
            $remaining = Num::sub($remaining, $take);
        }

        // Any shortfall (e.g. negative stock allowed) is costed at the current average.
        if (Num::cmp($remaining, 0) > 0) {
            $avg = Num::cmp($curQty, 0) > 0 ? Num::div($curValue, $curQty) : Num::zero();
            $cost = Num::add($cost, Num::mul($remaining, $avg));
        }

        return $cost;
    }

    /** Current weighted-average unit cost for a company-owned balance. */
    public function averageCost(int $productId, int $warehouseId, int $batchId = 0): string
    {
        $balance = StockBalance::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('batch_id', $batchId)
            ->where('owner_type', 'company')
            ->first();

        if ($balance === null || Num::cmp($balance->qty, 0) <= 0) {
            return Num::zero();
        }

        return Num::div((string) $balance->value, (string) $balance->qty);
    }
}
