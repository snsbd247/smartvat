<?php

namespace App\Modules\Sales\Services;

use App\Modules\Accounting\Models\JournalLine;
use App\Modules\Accounting\Services\AccountResolver;
use App\Shared\Support\Num;

/**
 * Customer balances derived from Accounts Receivable ledger lines tagged with the
 * customer as the party. Receivable (debit-normal): outstanding = debits − credits.
 */
class CustomerLedgerService
{
    public function __construct(private AccountResolver $accounts) {}

    private function baseQuery(int $customerId)
    {
        return JournalLine::query()
            ->where('account_id', $this->accounts->id('accounts_receivable'))
            ->where('party_type', 'customer')
            ->where('party_id', $customerId)
            ->whereHas('entry', fn ($q) => $q->whereIn('status', ['posted', 'reversed']));
    }

    public function outstanding(int $customerId): string
    {
        $row = $this->baseQuery($customerId)
            ->selectRaw('COALESCE(SUM(debit),0) d, COALESCE(SUM(credit),0) c')
            ->first();

        return Num::sub((string) ($row->d ?? 0), (string) ($row->c ?? 0));
    }

    /** @return array<int, array<string, mixed>> */
    public function ledger(int $customerId): array
    {
        return $this->baseQuery($customerId)
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
