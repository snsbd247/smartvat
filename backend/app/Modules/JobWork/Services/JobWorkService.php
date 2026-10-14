<?php

namespace App\Modules\JobWork\Services;

use App\Modules\Accounting\Services\AccountResolver;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Services\StockService;
use App\Modules\JobWork\Models\JobWorkOrder;
use App\Modules\JobWork\Models\ManufacturingContract;
use App\Modules\JobWork\Models\ProcessingInvoice;
use App\Shared\Numbering\NumberGenerator;
use App\Shared\Support\Num;
use App\Shared\Tenancy\CurrentCompany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Contract manufacturing / job work. CORE INVARIANT: customer-owned material is never
 * company inventory, asset, or sale. All custody movements use owner_type='customer',
 * which the stock engine excludes from company valuation and the GL — so there is NO
 * journal entry for receiving/consuming/delivering customer material. Only the
 * processing (conversion) invoice posts GL, as service revenue.
 */
class JobWorkService
{
    public function __construct(
        private CurrentCompany $current,
        private StockService $stock,
        private PostingService $posting,
        private AccountResolver $accounts,
        private NumberGenerator $numbers,
    ) {}

    public function createContract(array $data): ManufacturingContract
    {
        return ManufacturingContract::create([
            'company_id' => $this->current->idOrFail(),
            'contract_no' => $this->numbers->next('mfg_contract', 'MC-'),
            'customer_id' => $data['customer_id'],
            'processing_rate' => $data['processing_rate'] ?? 0,
            'scrap_ownership' => $data['scrap_ownership'] ?? 'customer',
            'terms' => $data['terms'] ?? null,
            'date' => Carbon::parse($data['date'] ?? now())->toDateString(),
            'status' => 'active',
        ]);
    }

    public function createOrder(array $data): JobWorkOrder
    {
        return JobWorkOrder::create([
            'company_id' => $this->current->idOrFail(),
            'order_no' => $this->numbers->next('job_work_order', 'JW-'),
            'manufacturing_contract_id' => $data['manufacturing_contract_id'] ?? null,
            'customer_id' => $data['customer_id'],
            'product_id' => $data['product_id'] ?? null,
            'warehouse_id' => $data['warehouse_id'],
            'planned_qty' => $data['planned_qty'] ?? 0,
            'status' => 'open',
            'created_by' => auth()->id(),
        ]);
    }

    /** Receive customer-owned material into custody (owner=customer, no GL). */
    public function receiveCustomerMaterial(JobWorkOrder $order, array $lines): void
    {
        $this->moveCustomer($order, $lines, inbound: true);
    }

    /** Consume / issue customer-owned material (owner=customer, no GL). */
    public function consumeCustomerMaterial(JobWorkOrder $order, array $lines): void
    {
        $this->moveCustomer($order, $lines, inbound: false);
    }

    /** Return unused customer material or deliver customer-owned goods out (no GL). */
    public function deliverCustomerGoods(JobWorkOrder $order, array $lines): void
    {
        $this->moveCustomer($order, $lines, inbound: false);
    }

    private function moveCustomer(JobWorkOrder $order, array $lines, bool $inbound): void
    {
        $stockLines = [];
        foreach ($lines as $l) {
            $qty = (string) $l['qty'];
            $stockLines[] = [
                'product_id' => $l['product_id'],
                'warehouse_id' => $order->warehouse_id,
                'qty' => $inbound ? $qty : Num::sub(0, $qty),
                'unit_cost' => 0, // customer-owned: no company value
                'owner_type' => 'customer',
                'owner_id' => $order->customer_id,
                'batch_id' => $l['batch_id'] ?? 0,
                'source_type' => 'job_work_order',
                'source_id' => $order->id,
                'reference' => $order->order_no,
            ];
        }
        $this->stock->apply($stockLines);
    }

    /** Processing invoice — charges the conversion service (company revenue), not goods. */
    public function processingInvoice(array $data): ProcessingInvoice
    {
        return DB::transaction(function () use ($data) {
            $qty = (string) $data['qty'];
            $rate = (string) $data['rate'];
            $amount = Num::mul($qty, $rate);

            $invoice = ProcessingInvoice::create([
                'company_id' => $this->current->idOrFail(),
                'invoice_no' => $this->numbers->next('processing_invoice', 'PINV-'),
                'customer_id' => $data['customer_id'],
                'job_work_order_id' => $data['job_work_order_id'] ?? null,
                'qty' => $qty,
                'rate' => $rate,
                'amount' => $amount,
                'date' => Carbon::parse($data['date'] ?? now())->toDateString(),
                'status' => 'posted',
                'created_by' => auth()->id(),
            ]);

            $this->posting->post([
                'date' => $invoice->date,
                'narration' => "Processing invoice {$invoice->invoice_no}",
                'source_type' => 'processing_invoice',
                'source_id' => $invoice->id,
                'purpose' => 'primary',
                'lines' => [
                    [
                        'account_id' => $this->accounts->id('accounts_receivable'),
                        'debit' => $amount,
                        'party_type' => 'customer',
                        'party_id' => $invoice->customer_id,
                    ],
                    ['account_id' => $this->accounts->id('processing_income'), 'credit' => $amount],
                ],
            ]);

            return $invoice;
        });
    }

    /**
     * Customer material reconciliation for a product: received − consumed/delivered = balance.
     */
    public function materialReconciliation(int $customerId, int $productId): array
    {
        $movements = StockMovement::where('owner_type', 'customer')
            ->where('owner_id', $customerId)
            ->where('product_id', $productId)
            ->get();

        $received = Num::zero();
        $issued = Num::zero();
        foreach ($movements as $m) {
            if (Num::cmp($m->qty, 0) > 0) {
                $received = Num::add($received, (string) $m->qty);
            } else {
                $issued = Num::add($issued, Num::sub(0, (string) $m->qty));
            }
        }

        return [
            'received' => $received,
            'consumed_or_delivered' => $issued,
            'balance' => Num::sub($received, $issued),
        ];
    }
}
