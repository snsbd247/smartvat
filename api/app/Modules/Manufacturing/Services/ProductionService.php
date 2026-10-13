<?php

namespace App\Modules\Manufacturing\Services;

use App\Modules\Accounting\Services\AccountResolver;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Manufacturing\Models\Bom;
use App\Modules\Manufacturing\Models\ProductionOrder;
use App\Shared\Numbering\NumberGenerator;
use App\Shared\Support\Num;
use App\Shared\Tenancy\CurrentCompany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Production, integrated with the shared engines. Costing is actual weighted-average:
 *   Issue materials : stock out of raw at avg cost + Dr WIP / Cr Inventory.
 *   Finished receipt: FG into stock at (WIP value / produced qty) + Dr Inventory / Cr WIP.
 * Net inventory value and WIP both stay consistent; WIP returns to zero on completion.
 */
class ProductionService
{
    public function __construct(
        private CurrentCompany $current,
        private StockService $stock,
        private PostingService $posting,
        private AccountResolver $accounts,
        private NumberGenerator $numbers,
    ) {}

    public function createBom(array $data): Bom
    {
        return DB::transaction(function () use ($data) {
            $bom = Bom::create([
                'company_id' => $this->current->idOrFail(),
                'bom_no' => $this->numbers->next('bom', 'BOM-'),
                'product_id' => $data['product_id'],
                'version' => $data['version'] ?? 1,
                'output_qty' => $data['output_qty'] ?? 1,
                'effective_from' => $data['effective_from'] ?? null,
                'status' => 'active',
            ]);

            foreach ($data['lines'] as $l) {
                $bom->lines()->create([
                    'company_id' => $bom->company_id,
                    'component_product_id' => $l['component_product_id'],
                    'qty' => $l['qty'],
                ]);
            }

            return $bom->load('lines');
        });
    }

    public function createOrder(array $data): ProductionOrder
    {
        $order = ProductionOrder::create([
            'company_id' => $this->current->idOrFail(),
            'order_no' => $this->numbers->next('production_order', 'PRD-'),
            'product_id' => $data['product_id'],
            'bom_id' => $data['bom_id'] ?? null,
            'warehouse_id' => $data['warehouse_id'],
            'planned_qty' => $data['planned_qty'],
            'date' => Carbon::parse($data['date'] ?? now())->toDateString(),
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        app(\App\Modules\Approval\Services\ApprovalService::class)->request($order, $order->order_no);

        return $order;
    }

    /**
     * Issue raw materials to production. Lines default to the BOM scaled to the order's
     * planned quantity; explicit lines override.
     */
    public function issueMaterials(ProductionOrder $order, ?array $lines = null): ProductionOrder
    {
        return DB::transaction(function () use ($order, $lines) {
            $lines ??= $this->bomRequirement($order);
            if ($lines === []) {
                throw new InvalidArgumentException('No materials to issue (provide lines or a BOM).');
            }

            $stockLines = [];
            foreach ($lines as $l) {
                $stockLines[] = [
                    'product_id' => $l['product_id'] ?? $l['component_product_id'],
                    'warehouse_id' => $order->warehouse_id,
                    'qty' => Num::sub(0, (string) $l['qty']), // outbound
                    'batch_id' => $l['batch_id'] ?? 0,
                    'source_type' => 'production_order',
                    'source_id' => $order->id,
                    'reference' => $order->order_no,
                ];
            }

            $movements = $this->stock->apply($stockLines);

            $consumed = Num::zero();
            foreach ($movements as $m) {
                $consumed = Num::add($consumed, Num::sub(0, (string) $m->value)); // value negative on out
            }

            if (! Num::isZero($consumed)) {
                $this->posting->post([
                    'date' => now()->toDateString(),
                    'narration' => "Material issue to {$order->order_no}",
                    'source_type' => 'production_order',
                    'source_id' => $order->id,
                    'purpose' => 'wip_issue_'.uniqid(),
                    'lines' => [
                        ['account_id' => $this->accounts->id('wip'), 'debit' => $consumed],
                        ['account_id' => $this->accounts->id('inventory'), 'credit' => $consumed],
                    ],
                ]);
            }

            $order->update([
                'wip_value' => Num::add((string) $order->wip_value, $consumed),
                'status' => 'released',
            ]);

            return $order->fresh();
        });
    }

    /**
     * Receive finished goods. The accumulated WIP value is moved into finished-goods
     * inventory at WIP/qty unit cost; yield loss is absorbed into the unit cost.
     */
    public function receiveFinished(ProductionOrder $order, string|float $qty, string|float $scrapQty = 0): ProductionOrder
    {
        $qty = (string) $qty;
        if (Num::cmp($qty, 0) <= 0) {
            throw new InvalidArgumentException('Produced quantity must be positive.');
        }

        return DB::transaction(function () use ($order, $qty, $scrapQty) {
            $fgCostTotal = (string) $order->wip_value;
            $unitCost = Num::div($fgCostTotal, $qty);

            $this->stock->apply([[
                'product_id' => $order->product_id,
                'warehouse_id' => $order->warehouse_id,
                'qty' => $qty,
                'unit_cost' => $unitCost,
                'source_type' => 'production_order',
                'source_id' => $order->id,
                'reference' => $order->order_no,
            ]]);

            if (! Num::isZero($fgCostTotal)) {
                $this->posting->post([
                    'date' => now()->toDateString(),
                    'narration' => "Finished goods receipt {$order->order_no}",
                    'source_type' => 'production_order',
                    'source_id' => $order->id,
                    'purpose' => 'fg_receipt_'.uniqid(),
                    'lines' => [
                        ['account_id' => $this->accounts->id('inventory'), 'debit' => $fgCostTotal],
                        ['account_id' => $this->accounts->id('wip'), 'credit' => $fgCostTotal],
                    ],
                ]);
            }

            $order->update([
                'produced_qty' => Num::add((string) $order->produced_qty, $qty),
                'scrap_qty' => Num::add((string) $order->scrap_qty, (string) $scrapQty),
                'wip_value' => 0,
                'status' => 'completed',
            ]);

            return $order->fresh();
        });
    }

    /** BOM scaled to the order's planned quantity. */
    public function bomRequirement(ProductionOrder $order): array
    {
        if ($order->bom_id === null) {
            return [];
        }
        $bom = $order->bom()->with('lines')->first();
        $factor = Num::div((string) $order->planned_qty, (string) $bom->output_qty);

        return $bom->lines->map(fn ($line) => [
            'product_id' => $line->component_product_id,
            'qty' => Num::mul((string) $line->qty, $factor),
        ])->all();
    }
}
