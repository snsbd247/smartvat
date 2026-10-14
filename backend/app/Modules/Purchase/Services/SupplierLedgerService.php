<?php

namespace App\Modules\Purchase\Services;

use App\Modules\Accounting\Models\JournalLine;
use App\Modules\Accounting\Services\AccountResolver;
use App\Shared\Support\Num;

/**
 * Supplier balances derived from the Accounts Payable ledger lines tagged with the
 * supplier as the party. Payable (credit-normal): outstanding = credits − debits.
 */
class SupplierLedgerService
{
    public function __construct(private AccountResolver $accounts) {}

    private function baseQuery(int $supplierId)
    {
        return JournalLine::query()
            ->where('account_id', $this->accounts->id('accounts_payable'))
            ->where('party_type', 'supplier')
            ->where('party_id', $supplierId)
            ->whereHas('entry', fn ($q) => $q->whereIn('status', ['posted', 'reversed']));
    }

    public function outstanding(int $supplierId): string
    {
        $row = $this->baseQuery($supplierId)
            ->selectRaw('COALESCE(SUM(credit),0) c, COALESCE(SUM(debit),0) d')
            ->first();

        return Num::sub((string) ($row->c ?? 0), (string) ($row->d ?? 0));
    }

    /** @return array<int, array<string, mixed>> */
    public function ledger(int $supplierId): array
    {
        return $this->baseQuery($supplierId)
            ->with('entry:id,entry_no,date,narration')
            ->orderBy('id')
            ->get()
            ->map(fn ($l) => [
                'date' => $l->entry->date->toDateString(),
                'entry_no' => $l->entry->entry_no,
                'narration' => $l->entry->narration,
                'debit' => (string) $l->debit,
                'credit' => (string) $l->credit,
            ])
            ->all();
    }
}
