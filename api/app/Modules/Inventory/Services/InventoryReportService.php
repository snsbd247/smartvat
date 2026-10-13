<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\AccountResolver;
use App\Modules\Accounting\Services\LedgerService;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockMovement;
use App\Shared\Support\Num;

class InventoryReportService
{
    public function __construct(
        private LedgerService $ledger,
        private AccountResolver $accounts,
    ) {}

    /** Company-owned stock valuation (qty + value + average cost) per balance row. */
    public function valuation(): array
    {
        return StockBalance::query()
            ->where('owner_type', 'company')
            ->orderBy('product_id')
            ->get()
            ->map(fn ($b) => [
                'product_id' => $b->product_id,
                'warehouse_id' => $b->warehouse_id,
                'batch_id' => $b->batch_id,
                'qty' => (string) $b->qty,
                'value' => (string) $b->value,
                'avg_cost' => Num::cmp($b->qty, 0) > 0 ? Num::div((string) $b->value, (string) $b->qty) : Num::zero(),
            ])
            ->all();
    }

    /** Total company-owned stock value. */
    public function stockValueTotal(): string
    {
        $total = Num::zero();
        foreach (StockBalance::where('owner_type', 'company')->pluck('value') as $v) {
            $total = Num::add($total, (string) $v);
        }

        return $total;
    }

    /** Stock movement ledger (optionally filtered). */
    public function ledger(array $filters = []): array
    {
        return StockMovement::query()
            ->when($filters['product_id'] ?? null, fn ($q, $v) => $q->where('product_id', $v))
            ->when($filters['warehouse_id'] ?? null, fn ($q, $v) => $q->where('warehouse_id', $v))
            ->orderBy('id')
            ->get()
            ->all();
    }

    /**
     * Inventory-to-GL reconciliation: total company-owned stock value must equal the
     * Inventory GL account balance.
     */
    public function reconciliation(): array
    {
        $stockTotal = $this->stockValueTotal();
        $account = Account::findOrFail($this->accounts->id('inventory'));
        $glBalance = $this->ledger->accountBalance($account);
        $difference = Num::sub($stockTotal, $glBalance);

        return [
            'stock_value_total' => $stockTotal,
            'inventory_gl_balance' => $glBalance,
            'difference' => $difference,
            'in_balance' => Num::isZero($difference),
        ];
    }
}
