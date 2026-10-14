<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\BankReconciliation;
use App\Modules\Accounting\Models\JournalLine;
use App\Shared\Support\Num;
use App\Shared\Tenancy\CurrentCompany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BankReconciliationService
{
    public function __construct(
        private CurrentCompany $current,
        private LedgerService $ledger,
    ) {}

    /** Posted, not-yet-reconciled lines for a bank/cash account. */
    public function unreconciledLines(int $accountId): array
    {
        return JournalLine::query()
            ->where('account_id', $accountId)
            ->where('reconciled', false)
            ->whereHas('entry', fn ($q) => $q->whereIn('status', ['posted', 'reversed']))
            ->with('entry:id,entry_no,date,narration')
            ->orderBy('id')
            ->get()
            ->map(fn ($l) => [
                'id' => $l->id,
                'date' => $l->entry->date->toDateString(),
                'entry_no' => $l->entry->entry_no,
                'narration' => $l->entry->narration,
                'debit' => (string) $l->debit,
                'credit' => (string) $l->credit,
            ])->all();
    }

    /** Mark the given lines as cleared and record the reconciliation summary. */
    public function reconcile(int $accountId, string $statementDate, string|float $statementBalance, array $lineIds): BankReconciliation
    {
        return DB::transaction(function () use ($accountId, $statementDate, $statementBalance, $lineIds) {
            $companyId = $this->current->idOrFail();

            JournalLine::whereIn('id', $lineIds)
                ->where('account_id', $accountId)
                ->update(['reconciled' => true, 'reconciled_at' => now()]);

            // Reconciled balance = debit − credit over all cleared lines (bank is debit-normal).
            $row = JournalLine::where('account_id', $accountId)
                ->where('reconciled', true)
                ->selectRaw('COALESCE(SUM(debit),0) d, COALESCE(SUM(credit),0) c')
                ->first();
            $reconciled = Num::sub((string) $row->d, (string) $row->c);

            $book = $this->ledger->accountBalance(Account::findOrFail($accountId));
            $statement = (string) $statementBalance;

            return BankReconciliation::create([
                'company_id' => $companyId,
                'account_id' => $accountId,
                'statement_date' => Carbon::parse($statementDate)->toDateString(),
                'statement_balance' => $statement,
                'book_balance' => $book,
                'reconciled_balance' => $reconciled,
                'difference' => Num::sub($statement, $reconciled),
                'created_by' => auth()->id(),
            ]);
        });
    }
}
