<?php

namespace App\Modules\Purchase\Services;

use App\Modules\Accounting\Services\AccountResolver;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Purchase\Models\GoodsReceiptNote;
use App\Modules\Purchase\Models\PurchaseInvoice;
use App\Modules\Purchase\Models\PurchaseOrder;
use App\Modules\Purchase\Models\PurchaseReturn;
use App\Modules\Purchase\Models\SupplierPayment;
use App\Shared\Numbering\NumberGenerator;
use App\Shared\Support\Num;
use App\Shared\Tenancy\CurrentCompany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Local purchase procure-to-pay, integrated with the shared engines:
 *   PO (no GL) -> GRN (stock in + Dr Inventory / Cr GRN-clearing)
 *   -> Purchase Invoice (material: Dr GRN-clearing / Cr AP; service: Dr Expense / Cr AP)
 *   -> Supplier Payment (Dr AP / Cr Cash|Bank).
 * All GL lines on AP carry the supplier as the party, so the supplier ledger and
 * outstanding balance are derived straight from the ledger.
 */
class PurchaseService
{
    public function __construct(
        private CurrentCompany $current,
        private StockService $stock,
        private PostingService $posting,
        private AccountResolver $accounts,
        private NumberGenerator $numbers,
    ) {}

    public function createOrder(array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($data) {
            $lines = $data['lines'] ?? [];
            if ($lines === []) {
                throw new InvalidArgumentException('A purchase order needs at least one line.');
            }

            $total = Num::zero();
            foreach ($lines as $l) {
                $total = Num::add($total, Num::mul((string) $l['qty'], (string) $l['unit_price']));
            }

            $po = PurchaseOrder::create([
                'company_id' => $this->current->idOrFail(),
                'po_no' => $this->numbers->next('purchase_order', 'PO-'),
                'supplier_id' => $data['supplier_id'],
                'date' => Carbon::parse($data['date'] ?? now())->toDateString(),
                'status' => 'draft',
                'reference' => $data['reference'] ?? null,
                'narration' => $data['narration'] ?? null,
                'total' => $total,
                'created_by' => auth()->id(),
            ]);

            foreach ($lines as $l) {
                $po->lines()->create([
                    'company_id' => $po->company_id,
                    'product_id' => $l['product_id'] ?? null,
                    'description' => $l['description'] ?? null,
                    'is_service' => (bool) ($l['is_service'] ?? false),
                    'qty' => $l['qty'],
                    'unit_price' => $l['unit_price'],
                    'line_total' => Num::mul((string) $l['qty'], (string) $l['unit_price']),
                ]);
            }

            return $po->load('lines');
        });
    }

    public function approveOrder(PurchaseOrder $po): PurchaseOrder
    {
        if ($po->status !== 'draft') {
            throw new InvalidArgumentException('Only a draft purchase order can be approved.');
        }

        $po->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return $po;
    }

    /**
     * Goods receipt for material: stock in + Dr Inventory / Cr GRN-clearing.
     */
    public function receiveGoods(array $data): GoodsReceiptNote
    {
        return DB::transaction(function () use ($data) {
            $companyId = $this->current->idOrFail();
            $total = Num::zero();

            $grn = GoodsReceiptNote::create([
                'company_id' => $companyId,
                'grn_no' => $this->numbers->next('grn', 'GRN-'),
                'supplier_id' => $data['supplier_id'],
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'warehouse_id' => $data['warehouse_id'],
                'date' => Carbon::parse($data['date'] ?? now())->toDateString(),
                'reference' => $data['reference'] ?? null,
                'status' => 'posted',
                'created_by' => auth()->id(),
            ]);

            $stockLines = [];
            foreach ($data['lines'] as $l) {
                $value = Num::mul((string) $l['qty'], (string) $l['unit_cost']);
                $total = Num::add($total, $value);

                $grn->lines()->create([
                    'company_id' => $companyId,
                    'product_id' => $l['product_id'],
                    'batch_id' => $l['batch_id'] ?? 0,
                    'qty' => $l['qty'],
                    'unit_cost' => $l['unit_cost'],
                    'line_value' => $value,
                ]);

                $stockLines[] = [
                    'product_id' => $l['product_id'],
                    'warehouse_id' => $data['warehouse_id'],
                    'qty' => $l['qty'],
                    'unit_cost' => $l['unit_cost'],
                    'batch_id' => $l['batch_id'] ?? 0,
                    'source_type' => 'goods_receipt_note',
                    'source_id' => $grn->id,
                    'reference' => $grn->grn_no,
                ];
            }

            $this->stock->apply($stockLines);
            $grn->update(['total_value' => $total]);

            $this->posting->post([
                'date' => $grn->date,
                'narration' => "Goods receipt {$grn->grn_no}",
                'source_type' => 'goods_receipt_note',
                'source_id' => $grn->id,
                'purpose' => 'inventory',
                'lines' => [
                    ['account_id' => $this->accounts->id('inventory'), 'debit' => $total],
                    ['account_id' => $this->accounts->id('grn_clearing'), 'credit' => $total],
                ],
            ]);

            return $grn->load('lines');
        });
    }

    /**
     * Purchase invoice. Material clears GRN-clearing into AP; service debits expense.
     * Credits Accounts Payable with the supplier as the party.
     */
    public function postInvoice(array $data): PurchaseInvoice
    {
        return DB::transaction(function () use ($data) {
            $companyId = $this->current->idOrFail();
            $type = $data['type'] ?? PurchaseInvoice::TYPE_MATERIAL;
            $lines = $data['lines'] ?? [];
            if ($lines === []) {
                throw new InvalidArgumentException('A purchase invoice needs at least one line.');
            }

            $subtotal = Num::zero();
            foreach ($lines as $l) {
                $subtotal = Num::add($subtotal, (string) $l['line_total']);
            }

            $pi = PurchaseInvoice::create([
                'company_id' => $companyId,
                'pi_no' => $this->numbers->next('purchase_invoice', 'PI-'),
                'supplier_id' => $data['supplier_id'],
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'goods_receipt_note_id' => $data['goods_receipt_note_id'] ?? null,
                'type' => $type,
                'date' => Carbon::parse($data['date'] ?? now())->toDateString(),
                'supplier_invoice_no' => $data['supplier_invoice_no'] ?? null,
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'narration' => $data['narration'] ?? null,
                'status' => 'posted',
                'created_by' => auth()->id(),
            ]);

            foreach ($lines as $l) {
                $pi->lines()->create([
                    'company_id' => $companyId,
                    'product_id' => $l['product_id'] ?? null,
                    'account_id' => $l['account_id'] ?? null,
                    'description' => $l['description'] ?? null,
                    'qty' => $l['qty'] ?? 1,
                    'unit_price' => $l['unit_price'] ?? $l['line_total'],
                    'line_total' => $l['line_total'],
                ]);
            }

            // Debit side.
            $debitLines = [];
            if ($type === PurchaseInvoice::TYPE_MATERIAL) {
                // Clear the GRN-clearing liability raised at receipt.
                $debitLines[] = ['account_id' => $this->accounts->id('grn_clearing'), 'debit' => $subtotal];
            } else {
                // Service: debit each expense account line.
                foreach ($lines as $l) {
                    if (empty($l['account_id'])) {
                        throw new InvalidArgumentException('Service invoice lines require an expense account_id.');
                    }
                    $debitLines[] = ['account_id' => $l['account_id'], 'debit' => $l['line_total']];
                }
            }

            // VAT hook: input VAT splitting is added in the VAT phase.
            $glLines = array_merge($debitLines, [[
                'account_id' => $this->accounts->id('accounts_payable'),
                'credit' => $subtotal,
                'party_type' => 'supplier',
                'party_id' => $pi->supplier_id,
            ]]);

            $this->posting->post([
                'date' => $pi->date,
                'narration' => "Purchase invoice {$pi->pi_no}",
                'source_type' => 'purchase_invoice',
                'source_id' => $pi->id,
                'purpose' => 'primary',
                'lines' => $glLines,
            ]);

            return $pi->load('lines');
        });
    }

    /**
     * Purchase return: send goods back to the supplier at weighted-average cost.
     * Stock out + Dr Accounts Payable (supplier) / Cr Inventory.
     */
    public function returnGoods(array $data): PurchaseReturn
    {
        return DB::transaction(function () use ($data) {
            $companyId = $this->current->idOrFail();

            $return = PurchaseReturn::create([
                'company_id' => $companyId,
                'return_no' => $this->numbers->next('purchase_return', 'PR-'),
                'supplier_id' => $data['supplier_id'],
                'warehouse_id' => $data['warehouse_id'],
                'date' => Carbon::parse($data['date'] ?? now())->toDateString(),
                'reference' => $data['reference'] ?? null,
                'status' => 'posted',
                'created_by' => auth()->id(),
            ]);

            $stockLines = [];
            foreach ($data['lines'] as $l) {
                $stockLines[] = [
                    'product_id' => $l['product_id'],
                    'warehouse_id' => $data['warehouse_id'],
                    'qty' => Num::sub(0, (string) $l['qty']), // outbound
                    'batch_id' => $l['batch_id'] ?? 0,
                    'source_type' => 'purchase_return',
                    'source_id' => $return->id,
                    'reference' => $return->return_no,
                ];
            }
            $movements = $this->stock->apply($stockLines);

            $total = Num::zero();
            foreach ($movements as $i => $m) {
                $value = Num::sub(0, (string) $m->value); // value negative on out
                $total = Num::add($total, $value);
                $return->lines()->create([
                    'company_id' => $companyId,
                    'product_id' => $data['lines'][$i]['product_id'],
                    'batch_id' => $data['lines'][$i]['batch_id'] ?? 0,
                    'qty' => $data['lines'][$i]['qty'],
                    'unit_cost' => $m->unit_cost,
                    'line_value' => $value,
                ]);
            }
            $return->update(['amount' => $total]);

            if (! Num::isZero($total)) {
                $this->posting->post([
                    'date' => $return->date,
                    'narration' => "Purchase return {$return->return_no}",
                    'source_type' => 'purchase_return',
                    'source_id' => $return->id,
                    'purpose' => 'primary',
                    'lines' => [
                        [
                            'account_id' => $this->accounts->id('accounts_payable'),
                            'debit' => $total,
                            'party_type' => 'supplier',
                            'party_id' => $return->supplier_id,
                        ],
                        ['account_id' => $this->accounts->id('inventory'), 'credit' => $total],
                    ],
                ]);
            }

            return $return->load('lines');
        });
    }

    /**
     * Supplier payment: Dr Accounts Payable (supplier) / Cr Cash|Bank.
     */
    public function paySupplier(array $data): SupplierPayment
    {
        return DB::transaction(function () use ($data) {
            $amount = (string) $data['amount'];
            if (Num::cmp($amount, 0) <= 0) {
                throw new InvalidArgumentException('Payment amount must be positive.');
            }

            $payment = SupplierPayment::create([
                'company_id' => $this->current->idOrFail(),
                'payment_no' => $this->numbers->next('supplier_payment', 'PAY-'),
                'supplier_id' => $data['supplier_id'],
                'date' => Carbon::parse($data['date'] ?? now())->toDateString(),
                'amount' => $amount,
                'paid_from_account_id' => $data['paid_from_account_id'],
                'reference' => $data['reference'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $this->posting->post([
                'date' => $payment->date,
                'narration' => "Supplier payment {$payment->payment_no}",
                'source_type' => 'supplier_payment',
                'source_id' => $payment->id,
                'purpose' => 'primary',
                'lines' => [
                    [
                        'account_id' => $this->accounts->id('accounts_payable'),
                        'debit' => $amount,
                        'party_type' => 'supplier',
                        'party_id' => $payment->supplier_id,
                    ],
                    ['account_id' => $payment->paid_from_account_id, 'credit' => $amount],
                ],
            ]);

            return $payment;
        });
    }
}
