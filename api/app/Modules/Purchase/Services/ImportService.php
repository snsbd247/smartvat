<?php

namespace App\Modules\Purchase\Services;

use App\Modules\Accounting\Services\AccountResolver;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Purchase\Models\ImportShipment;
use App\Modules\Purchase\Models\ImportShipmentLine;
use App\Shared\Numbering\NumberGenerator;
use App\Shared\Support\Num;
use App\Shared\Tenancy\CurrentCompany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Import shipment lifecycle. Receiving runs the landed-cost allocation and brings
 * stock in at landed unit cost, posting:
 *   Dr Inventory (landed total)
 *     Cr GRN-clearing (goods value, base currency)
 *     Cr Landed-cost clearing (allocated non-recoverable charges)
 * Receiving is guarded so a shipment is received (and allocated) only once.
 */
class ImportService
{
    public function __construct(
        private CurrentCompany $current,
        private StockService $stock,
        private PostingService $posting,
        private AccountResolver $accounts,
        private NumberGenerator $numbers,
        private LandedCostService $landedCost,
    ) {}

    public function createShipment(array $data): ImportShipment
    {
        return DB::transaction(function () use ($data) {
            $companyId = $this->current->idOrFail();

            $shipment = ImportShipment::create([
                'company_id' => $companyId,
                'shipment_no' => $this->numbers->next('import_shipment', 'IMP-'),
                'supplier_id' => $data['supplier_id'],
                'warehouse_id' => $data['warehouse_id'],
                'currency' => $data['currency'],
                'exchange_rate' => $data['exchange_rate'],
                'incoterm' => $data['incoterm'] ?? null,
                'bl_no' => $data['bl_no'] ?? null,
                'date' => Carbon::parse($data['date'] ?? now())->toDateString(),
                'reference' => $data['reference'] ?? null,
                'status' => 'draft',
                'created_by' => auth()->id(),
            ]);

            foreach ($data['lines'] as $l) {
                $shipment->lines()->create([
                    'company_id' => $companyId,
                    'product_id' => $l['product_id'],
                    'batch_id' => $l['batch_id'] ?? 0,
                    'qty' => $l['qty'],
                    'fc_unit_price' => $l['fc_unit_price'],
                    'weight' => $l['weight'] ?? 0,
                    'volume' => $l['volume'] ?? 0,
                ]);
            }

            foreach ($data['charges'] ?? [] as $c) {
                $rate = (string) ($c['exchange_rate'] ?? 1);
                $bc = $c['bc_amount'] ?? Num::mul((string) $c['fc_amount'], $rate);
                $shipment->charges()->create([
                    'company_id' => $companyId,
                    'type' => $c['type'],
                    'currency' => $c['currency'] ?? ($this->current->model()->base_currency ?? 'BDT'),
                    'fc_amount' => $c['fc_amount'],
                    'exchange_rate' => $rate,
                    'bc_amount' => $bc,
                    'recoverable' => (bool) ($c['recoverable'] ?? false),
                    'allocation_basis' => $c['allocation_basis'] ?? 'value',
                ]);
            }

            return $shipment->load('lines', 'charges');
        });
    }

    /** Preview the landed cost without posting. */
    public function preview(ImportShipment $shipment): array
    {
        return $this->landedCost->allocate($shipment->load('lines', 'charges'), persist: false);
    }

    public function receive(ImportShipment $shipment): ImportShipment
    {
        if ($shipment->status !== 'draft') {
            throw new InvalidArgumentException('Only a draft shipment can be received.');
        }

        return DB::transaction(function () use ($shipment) {
            $shipment->load('lines', 'charges');
            $result = $this->landedCost->allocate($shipment, persist: true);

            // Stock in at landed unit cost; record landed cost on each line.
            $stockLines = [];
            foreach ($result['lines'] as $pl) {
                ImportShipmentLine::whereKey($pl['line_id'])->update(['landed_unit_cost' => $pl['landed_unit_cost']]);
                $stockLines[] = [
                    'product_id' => $pl['product_id'],
                    'warehouse_id' => $shipment->warehouse_id,
                    'qty' => $pl['qty'],
                    'unit_cost' => $pl['landed_unit_cost'],
                    'batch_id' => $pl['batch_id'],
                    'source_type' => 'import_shipment',
                    'source_id' => $shipment->id,
                    'reference' => $shipment->shipment_no,
                ];
            }
            $this->stock->apply($stockLines);

            // GL: inventory debited with the full landed value.
            $glLines = [
                ['account_id' => $this->accounts->id('inventory'), 'debit' => $result['landed_total']],
                ['account_id' => $this->accounts->id('grn_clearing'), 'credit' => $result['goods_bc_total']],
            ];
            if (! Num::isZero($result['nonrecoverable_total'])) {
                $glLines[] = [
                    'account_id' => $this->accounts->id('landed_cost_clearing'),
                    'credit' => $result['nonrecoverable_total'],
                ];
            }

            $this->posting->post([
                'date' => $shipment->date,
                'narration' => "Import receipt {$shipment->shipment_no}",
                'source_type' => 'import_shipment',
                'source_id' => $shipment->id,
                'purpose' => 'inventory',
                'lines' => $glLines,
            ]);

            $shipment->update([
                'status' => 'received',
                'goods_value_bc' => $result['goods_bc_total'],
                'landed_value_bc' => $result['landed_total'],
            ]);

            return $shipment->fresh(['lines', 'charges']);
        });
    }
}
