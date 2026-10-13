<?php

namespace App\Modules\Purchase\Services;

use App\Modules\Purchase\Models\ImportShipment;
use App\Shared\Support\Num;
use RuntimeException;

/**
 * Landed-cost allocation engine. Distributes each eligible (non-recoverable) import
 * charge across the shipment's product lines using a configured basis
 * (quantity / weight / volume / value), producing a landed unit cost per line.
 *
 * Recoverable charges (e.g. import VAT) are excluded from inventory cost.
 * Allocation is idempotent: persisting replaces any prior allocation for the
 * shipment, so a charge is never allocated twice.
 */
class LandedCostService
{
    public function allocate(ImportShipment $shipment, bool $persist = true): array
    {
        $rate = (string) $shipment->exchange_rate;
        $lines = $shipment->lines()->orderBy('id')->get();

        if ($lines->isEmpty()) {
            throw new RuntimeException('Shipment has no lines to allocate against.');
        }

        // Goods value (base currency) per line.
        $goods = [];
        $goodsTotal = Num::zero();
        foreach ($lines as $line) {
            $bc = Num::mul(Num::mul((string) $line->qty, (string) $line->fc_unit_price), $rate);
            $goods[$line->id] = $bc;
            $goodsTotal = Num::add($goodsTotal, $bc);
        }

        // Allocate each non-recoverable charge across lines.
        $allocated = array_fill_keys($lines->pluck('id')->all(), Num::zero());
        $allocationRows = [];
        $nonRecoverableTotal = Num::zero();

        foreach ($shipment->charges as $charge) {
            if ($charge->recoverable) {
                continue; // not capitalized
            }
            $nonRecoverableTotal = Num::add($nonRecoverableTotal, (string) $charge->bc_amount);

            $metrics = $this->lineMetrics($lines, $charge->allocation_basis, $goods);
            $totalMetric = array_reduce($metrics, fn ($c, $m) => Num::add($c, $m), Num::zero());

            if (Num::isZero($totalMetric)) {
                throw new RuntimeException(
                    "Cannot allocate charge by '{$charge->allocation_basis}': the basis total is zero."
                );
            }

            $running = Num::zero();
            $last = $lines->last()->id;
            foreach ($lines as $line) {
                if ($line->id === $last) {
                    // Last line absorbs the rounding remainder for an exact total.
                    $portion = Num::sub((string) $charge->bc_amount, $running);
                } else {
                    $portion = Num::mul((string) $charge->bc_amount, Num::div($metrics[$line->id], $totalMetric));
                    $running = Num::add($running, $portion);
                }

                $allocated[$line->id] = Num::add($allocated[$line->id], $portion);
                $allocationRows[] = [
                    'company_id' => $shipment->company_id,
                    'import_shipment_id' => $shipment->id,
                    'import_charge_id' => $charge->id,
                    'import_shipment_line_id' => $line->id,
                    'amount' => $portion,
                ];
            }
        }

        // Build per-line landed cost.
        $perLine = [];
        $landedTotal = Num::zero();
        foreach ($lines as $line) {
            $landedValue = Num::add($goods[$line->id], $allocated[$line->id]);
            $landedTotal = Num::add($landedTotal, $landedValue);
            $perLine[] = [
                'line_id' => $line->id,
                'product_id' => $line->product_id,
                'batch_id' => $line->batch_id,
                'qty' => (string) $line->qty,
                'goods_bc' => $goods[$line->id],
                'allocated' => $allocated[$line->id],
                'landed_value' => $landedValue,
                'landed_unit_cost' => Num::div($landedValue, (string) $line->qty),
            ];
        }

        if ($persist) {
            $shipment->allocations()->delete();
            foreach ($allocationRows as $row) {
                $shipment->allocations()->create($row);
            }
        }

        return [
            'lines' => $perLine,
            'goods_bc_total' => $goodsTotal,
            'nonrecoverable_total' => $nonRecoverableTotal,
            'landed_total' => $landedTotal,
        ];
    }

    private function lineMetrics($lines, string $basis, array $goods): array
    {
        $metrics = [];
        foreach ($lines as $line) {
            $metrics[$line->id] = match ($basis) {
                'quantity' => (string) $line->qty,
                'weight' => (string) $line->weight,
                'volume' => (string) $line->volume,
                'value' => $goods[$line->id],
                default => throw new RuntimeException("Unknown allocation basis: {$basis}"),
            };
        }

        return $metrics;
    }
}
