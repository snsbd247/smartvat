<?php

namespace App\Modules\Mis\Services;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\AccountingReportService;
use App\Modules\Accounting\Services\AccountResolver;
use App\Modules\Accounting\Services\LedgerService;
use App\Modules\Inventory\Services\InventoryReportService;
use App\Modules\Manufacturing\Models\ProductionOrder;
use App\Modules\Purchase\Models\PurchaseOrder;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Vat\Models\VatClassification;
use App\Shared\Support\Num;

/**
 * Management information: cross-module KPIs for the executive dashboard, plus the
 * pending-approvals feed. Read-only — it never posts business transactions.
 */
class MisService
{
    public function __construct(
        private LedgerService $ledger,
        private AccountingReportService $accReports,
        private InventoryReportService $invReports,
        private AccountResolver $accounts,
    ) {}

    public function executive(): array
    {
        $pl = $this->accReports->profitAndLoss();

        return [
            'revenue' => $pl['income'],
            'expense' => $pl['expense'],
            'net_profit' => $pl['net_profit'],
            'receivables' => $this->balance('accounts_receivable'),
            'payables' => $this->balance('accounts_payable'),
            'vds_payable' => $this->balance('vds_payable'),
            'stock_value' => $this->invReports->stockValueTotal(),
            'cash_bank' => $this->cashBank(),
            'pending_approvals' => $this->pendingApprovalsCount(),
            'vat_unmapped' => VatClassification::where('status', 'review_required')->count(),
        ];
    }

    public function approvals(): array
    {
        $items = [];

        foreach (PurchaseOrder::where('status', 'draft')->get() as $po) {
            $items[] = ['type' => 'purchase_order', 'id' => $po->id, 'number' => $po->po_no, 'amount' => (string) $po->total];
        }
        foreach (SalesOrder::where('status', 'draft')->get() as $so) {
            $items[] = ['type' => 'sales_order', 'id' => $so->id, 'number' => $so->so_no, 'amount' => (string) $so->total];
        }
        foreach (ProductionOrder::where('status', 'draft')->get() as $pr) {
            $items[] = ['type' => 'production_order', 'id' => $pr->id, 'number' => $pr->order_no, 'amount' => null];
        }

        return $items;
    }

    private function pendingApprovalsCount(): int
    {
        return PurchaseOrder::where('status', 'draft')->count()
            + SalesOrder::where('status', 'draft')->count()
            + ProductionOrder::where('status', 'draft')->count();
    }

    private function balance(string $key): string
    {
        try {
            $account = Account::find($this->accounts->id($key));
        } catch (\Throwable) {
            return Num::zero();
        }

        return $account ? $this->ledger->accountBalance($account) : Num::zero();
    }

    private function cashBank(): string
    {
        $total = Num::zero();
        foreach (Account::whereIn('code', ['1000', '1010'])->get() as $account) {
            $total = Num::add($total, $this->ledger->accountBalance($account));
        }

        return $total;
    }
}
