<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Exceptions\InsufficientStockException;
use App\Modules\Inventory\Models\StockBalance;
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
        if ($product->costing_method !== Product::COSTING_WEIGHTED_AVERAGE) {
            throw new RuntimeException(
                "Costing method '{$product->costing_method}' is not yet supported; use weighted_average."
            );
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
        } else {
            // Outbound (qty negative).
            $outQty = Num::sub(0, $qty); // positive magnitude
            if (! $allowNegative && Num::cmp($outQty, $curQty) > 0) {
                throw new InsufficientStockException($productId, $warehouseId, $curQty, $outQty);
            }

            $avg = Num::cmp($curQty, 0) > 0 ? Num::div($curValue, $curQty) : Num::zero();
            $outValue = Num::mul($outQty, $avg);
            $newQty = Num::sub($curQty, $outQty);
            $newValue = Num::sub($curValue, $outValue);
            $recordedUnitCost = $avg;
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
