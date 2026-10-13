<?php

namespace App\Modules\Sales\Services;

use App\Modules\Accounting\Services\AccountResolver;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Sales\Models\CustomerReceipt;
use App\Modules\Sales\Models\DeliveryChallan;
use App\Modules\Sales\Models\SalesInvoice;
use App\Modules\Sales\Models\SalesReturn;
use App\Shared\Numbering\NumberGenerator;
use App\Shared\Support\Num;
use App\Shared\Tenancy\CurrentCompany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Sales — local and export — integrated with the shared engines.
 *   Invoice  : Dr AR / Cr Revenue (base currency; export converts FC at the rate).
 *   Challan  : separate document; stock out at avg cost + Dr COGS / Cr Inventory.
 *   Receipt  : Dr Cash|Bank / Cr AR (export realization books exchange gain/loss).
 *   Return   : goods back (Dr Inventory / Cr COGS) + credit note (Dr Revenue / Cr AR).
 * AR lines carry the customer as the party, so the customer ledger derives from the GL.
 */
class SalesService
{
    public function __construct(
        private CurrentCompany $current,
        private StockService $stock,
        private PostingService $posting,
        private AccountResolver $accounts,
        private NumberGenerator $numbers,
    ) {}

    public function postInvoice(array $data): SalesInvoice
    {
        return DB::transaction(function () use ($data) {
            $companyId = $this->current->idOrFail();
            $kind = $data['kind'] ?? 'local';
            $rate = $kind === 'export' ? (string) ($data['exchange_rate'] ?? 1) : '1';
            $currency = $kind === 'export'
                ? ($data['currency'] ?? 'USD')
                : ($this->current->model()->base_currency ?? 'BDT');

            $lines = $data['lines'] ?? [];
            if ($lines === []) {
                throw new InvalidArgumentException('A sales invoice needs at least one line.');
            }

            $subtotalFc = Num::zero();
            foreach ($lines as $l) {
                $subtotalFc = Num::add($subtotalFc, (string) $l['line_total']);
            }
            $subtotalBc = Num::mul($subtotalFc, $rate);

            $invoice = SalesInvoice::create([
                'company_id' => $companyId,
                'inv_no' => $this->numbers->next('sales_invoice', 'INV-'),
                'customer_id' => $data['customer_id'],
                'sales_order_id' => $data['sales_order_id'] ?? null,
                'kind' => $kind,
                'currency' => $currency,
                'exchange_rate' => $rate,
                'date' => Carbon::parse($data['date'] ?? now())->toDateString(),
                'subtotal_fc' => $subtotalFc,
                'subtotal' => $subtotalBc,
                'total' => $subtotalBc,
                'narration' => $data['narration'] ?? null,
                'status' => 'posted',
                'created_by' => auth()->id(),
            ]);

            foreach ($lines as $l) {
                $invoice->lines()->create([
                    'company_id' => $companyId,
                    'product_id' => $l['product_id'] ?? null,
                    'description' => $l['description'] ?? null,
                    'qty' => $l['qty'] ?? 1,
                    'unit_price' => $l['unit_price'] ?? $l['line_total'],
                    'line_total' => $l['line_total'],
                ]);
            }

            $revenueKey = $kind === 'export' ? 'export_revenue' : 'sales_revenue';

            $this->posting->post([
                'date' => $invoice->date,
                'narration' => "Sales invoice {$invoice->inv_no}",
                'source_type' => 'sales_invoice',
                'source_id' => $invoice->id,
                'purpose' => 'primary',
                'lines' => [
                    [
                        'account_id' => $this->accounts->id('accounts_receivable'),
                        'debit' => $subtotalBc,
                        'party_type' => 'customer',
                        'party_id' => $invoice->customer_id,
                    ],
                    ['account_id' => $this->accounts->id($revenueKey), 'credit' => $subtotalBc],
                ],
            ]);

            return $invoice->load('lines');
        });
    }

    /**
     * Delivery challan — stock out at weighted-average cost, Dr COGS / Cr Inventory.
     */
    public function deliver(array $data): DeliveryChallan
    {
        return DB::transaction(function () use ($data) {
            $companyId = $this->current->idOrFail();

            $challan = DeliveryChallan::create([
                'company_id' => $companyId,
                'challan_no' => $this->numbers->next('delivery_challan', 'DC-'),
                'customer_id' => $data['customer_id'],
                'sales_invoice_id' => $data['sales_invoice_id'] ?? null,
                'warehouse_id' => $data['warehouse_id'],
                'date' => Carbon::parse($data['date'] ?? now())->toDateString(),
                'reference' => $data['reference'] ?? null,
                'status' => 'posted',
                'created_by' => auth()->id(),
            ]);

            $stockLines = [];
            foreach ($data['lines'] as $l) {
                $challan->lines()->create([
                    'company_id' => $companyId,
                    'product_id' => $l['product_id'],
                    'batch_id' => $l['batch_id'] ?? 0,
                    'qty' => $l['qty'],
                ]);
                $stockLines[] = [
                    'product_id' => $l['product_id'],
                    'warehouse_id' => $data['warehouse_id'],
                    'qty' => Num::sub(0, (string) $l['qty']), // outbound
                    'batch_id' => $l['batch_id'] ?? 0,
                    'source_type' => 'delivery_challan',
                    'source_id' => $challan->id,
                    'reference' => $challan->challan_no,
                ];
            }

            $movements = $this->stock->apply($stockLines);

            $cogs = Num::zero();
            foreach ($movements as $m) {
                $cogs = Num::add($cogs, Num::sub(0, (string) $m->value)); // value negative on out
            }
            $challan->update(['cogs_value' => $cogs]);

            if (! Num::isZero($cogs)) {
                $this->posting->post([
                    'date' => $challan->date,
                    'narration' => "Delivery challan {$challan->challan_no}",
                    'source_type' => 'delivery_challan',
                    'source_id' => $challan->id,
                    'purpose' => 'cogs',
                    'lines' => [
                        ['account_id' => $this->accounts->id('cogs'), 'debit' => $cogs],
                        ['account_id' => $this->accounts->id('inventory'), 'credit' => $cogs],
                    ],
                ]);
            }

            return $challan->load('lines');
        });
    }

    /**
     * Customer receipt. Local: Dr Cash|Bank / Cr AR. Export: realization books the
     * exchange gain/loss between the invoice rate and the realization rate.
     */
    public function recordReceipt(array $data): CustomerReceipt
    {
        return DB::transaction(function () use ($data) {
            $companyId = $this->current->idOrFail();
            $isExport = ($data['kind'] ?? 'local') === 'export';

            if ($isExport) {
                $fc = (string) $data['fc_amount'];
                $realRate = (string) $data['realization_rate'];
                $invRate = (string) $data['invoice_rate'];
                $received = Num::mul($fc, $realRate);
                $arRelieved = Num::mul($fc, $invRate);
            } else {
                $fc = '0';
                $realRate = '1';
                $invRate = '1';
                $received = (string) $data['amount'];
                $arRelieved = $received;
            }

            if (Num::cmp($received, 0) <= 0) {
                throw new InvalidArgumentException('Receipt amount must be positive.');
            }

            $receipt = CustomerReceipt::create([
                'company_id' => $companyId,
                'receipt_no' => $this->numbers->next('customer_receipt', 'RCP-'),
                'customer_id' => $data['customer_id'],
                'date' => Carbon::parse($data['date'] ?? now())->toDateString(),
                'currency' => $data['currency'] ?? ($isExport ? 'USD' : 'BDT'),
                'fc_amount' => $fc,
                'realization_rate' => $realRate,
                'invoice_rate' => $invRate,
                'amount' => $received,
                'received_in_account_id' => $data['received_in_account_id'],
                'reference' => $data['reference'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $lines = [
                ['account_id' => $receipt->received_in_account_id, 'debit' => $received],
                [
                    'account_id' => $this->accounts->id('accounts_receivable'),
                    'credit' => $arRelieved,
                    'party_type' => 'customer',
                    'party_id' => $receipt->customer_id,
                ],
            ];

            // Exchange gain/loss balances received vs AR relieved.
            $diff = Num::sub($received, $arRelieved);
            if (! Num::isZero($diff)) {
                $gl = $this->accounts->id('exchange_gain_loss');
                if (Num::cmp($diff, 0) > 0) {
                    $lines[] = ['account_id' => $gl, 'credit' => $diff];       // gain
                } else {
                    $lines[] = ['account_id' => $gl, 'debit' => Num::sub(0, $diff)]; // loss
                }
            }

            $this->posting->post([
                'date' => $receipt->date,
                'narration' => "Customer receipt {$receipt->receipt_no}",
                'source_type' => 'customer_receipt',
                'source_id' => $receipt->id,
                'purpose' => 'primary',
                'lines' => $lines,
            ]);

            return $receipt;
        });
    }

    /**
     * Sales return + credit note. Goods back at avg cost (Dr Inventory / Cr COGS);
     * credit note reduces revenue and AR (Dr Revenue / Cr AR).
     */
    public function returnGoods(array $data): SalesReturn
    {
        return DB::transaction(function () use ($data) {
            $companyId = $this->current->idOrFail();

            $return = SalesReturn::create([
                'company_id' => $companyId,
                'return_no' => $this->numbers->next('sales_return', 'SR-'),
                'customer_id' => $data['customer_id'],
                'sales_invoice_id' => $data['sales_invoice_id'] ?? null,
                'warehouse_id' => $data['warehouse_id'],
                'date' => Carbon::parse($data['date'] ?? now())->toDateString(),
                'reference' => $data['reference'] ?? null,
                'status' => 'posted',
                'created_by' => auth()->id(),
            ]);

            $amount = Num::zero();
            $stockLines = [];
            foreach ($data['lines'] as $l) {
                $lineTotal = Num::mul((string) $l['qty'], (string) $l['unit_price']);
                $amount = Num::add($amount, $lineTotal);
                $return->lines()->create([
                    'company_id' => $companyId,
                    'product_id' => $l['product_id'],
                    'batch_id' => $l['batch_id'] ?? 0,
                    'qty' => $l['qty'],
                    'unit_price' => $l['unit_price'],
                    'line_total' => $lineTotal,
                ]);
                $avg = $this->stock->averageCost((int) $l['product_id'], (int) $data['warehouse_id'], (int) ($l['batch_id'] ?? 0));
                $stockLines[] = [
                    'product_id' => $l['product_id'],
                    'warehouse_id' => $data['warehouse_id'],
                    'qty' => $l['qty'], // inbound (goods back)
                    'unit_cost' => $avg,
                    'batch_id' => $l['batch_id'] ?? 0,
                    'source_type' => 'sales_return',
                    'source_id' => $return->id,
                    'reference' => $return->return_no,
                ];
            }

            $movements = $this->stock->apply($stockLines);
            $cogs = Num::zero();
            foreach ($movements as $m) {
                $cogs = Num::add($cogs, (string) $m->value); // positive on in
            }

            $return->update(['amount' => $amount, 'cogs_value' => $cogs]);

            $glLines = [
                // Credit note: reduce revenue, relieve receivable.
                ['account_id' => $this->accounts->id('sales_revenue'), 'debit' => $amount],
                [
                    'account_id' => $this->accounts->id('accounts_receivable'),
                    'credit' => $amount,
                    'party_type' => 'customer',
                    'party_id' => $return->customer_id,
                ],
            ];
            if (! Num::isZero($cogs)) {
                // Goods back into inventory, reverse COGS.
                $glLines[] = ['account_id' => $this->accounts->id('inventory'), 'debit' => $cogs];
                $glLines[] = ['account_id' => $this->accounts->id('cogs'), 'credit' => $cogs];
            }

            $this->posting->post([
                'date' => $return->date,
                'narration' => "Sales return {$return->return_no}",
                'source_type' => 'sales_return',
                'source_id' => $return->id,
                'purpose' => 'primary',
                'lines' => $glLines,
            ]);

            return $return->load('lines');
        });
    }
}
