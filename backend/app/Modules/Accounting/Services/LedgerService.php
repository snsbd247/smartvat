<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\JournalLine;
use App\Shared\Support\Num;

/**
 * Read-only ledger aggregation. Balances include posted and reversed entries
 * (a reversal's own lines cancel the original), never drafts.
 */
class LedgerService
{
    /** Net balance of an account in its natural (normal-balance) sign. */
    public function accountBalance(Account $account): string
    {
        [$debit, $credit] = $this->debitCredit($account->id);
        $raw = Num::sub($debit, $credit); // debit-positive

        return $account->normal_balance === 'debit' ? $raw : Num::sub(0, $raw);
    }

    /** @return array{0:string,1:string} [totalDebit, totalCredit] for an account */
    public function debitCredit(int $accountId): array
    {
        $row = JournalLine::query()
            ->where('account_id', $accountId)
            ->whereHas('entry', fn ($q) => $q->whereIn('status', ['posted', 'reversed']))
            ->selectRaw('COALESCE(SUM(debit),0) as d, COALESCE(SUM(credit),0) as c')
            ->first();

        return [Num::add($row->d ?? 0, 0), Num::add($row->c ?? 0, 0)];
    }

    /**
     * Trial balance across all postable accounts of the current company.
     * The grand totals of debit and credit must be equal.
     *
     * @return array<int, array{account_id:int, code:string, name:string, debit:string, credit:string}>
     */
    public function trialBalance(): array
    {
        $rows = [];

        foreach (Account::orderBy('code')->get() as $account) {
            [$debit, $credit] = $this->debitCredit($account->id);
            $net = Num::sub($debit, $credit);

            if (Num::isZero($net)) {
                continue;
            }

            $rows[] = [
                'account_id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'debit' => Num::cmp($net, 0) > 0 ? $net : Num::zero(),
                'credit' => Num::cmp($net, 0) < 0 ? Num::sub(0, $net) : Num::zero(),
            ];
        }

        return $rows;
    }
}
