<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\JournalLine;
use App\Shared\Support\Num;

class AccountingReportService
{
    public function __construct(private LedgerService $ledger) {}

    /** Profit & Loss: income − expense. */
    public function profitAndLoss(): array
    {
        $income = $this->sumByType('income');
        $expense = $this->sumByType('expense');

        return [
            'income' => $income['total'],
            'expense' => $expense['total'],
            'net_profit' => Num::sub($income['total'], $expense['total']),
            'income_lines' => $income['lines'],
            'expense_lines' => $expense['lines'],
        ];
    }

    /**
     * Balance Sheet. Assets = Liabilities + Equity + current-period net profit.
     */
    public function balanceSheet(): array
    {
        $assets = $this->sumByType('asset');
        $liabilities = $this->sumByType('liability');
        $equity = $this->sumByType('equity');
        $income = $this->sumByType('income');
        $expense = $this->sumByType('expense');
        $netProfit = Num::sub($income['total'], $expense['total']);

        $rightSide = Num::add(Num::add($liabilities['total'], $equity['total']), $netProfit);

        return [
            'assets' => $assets['total'],
            'liabilities' => $liabilities['total'],
            'equity' => $equity['total'],
            'net_profit' => $netProfit,
            'liabilities_equity_and_profit' => $rightSide,
            'in_balance' => Num::eq($assets['total'], $rightSide),
            'asset_lines' => $assets['lines'],
            'liability_lines' => $liabilities['lines'],
            'equity_lines' => $equity['lines'],
        ];
    }

    /** Cash/bank book for one account with a running balance. */
    public function cashBook(int $accountId): array
    {
        $account = Account::findOrFail($accountId);
        $running = Num::zero();
        $rows = [];

        $lines = JournalLine::query()
            ->where('account_id', $accountId)
            ->whereHas('entry', fn ($q) => $q->whereIn('status', ['posted', 'reversed']))
            ->with('entry:id,entry_no,date,narration')
            ->orderBy('id')
            ->get();

        foreach ($lines as $l) {
            $delta = $account->normal_balance === 'debit'
                ? Num::sub((string) $l->debit, (string) $l->credit)
                : Num::sub((string) $l->credit, (string) $l->debit);
            $running = Num::add($running, $delta);
            $rows[] = [
                'date' => $l->entry->date->toDateString(),
                'entry_no' => $l->entry->entry_no,
                'narration' => $l->entry->narration,
                'debit' => (string) $l->debit,
                'credit' => (string) $l->credit,
                'balance' => $running,
            ];
        }

        return ['account' => $account->code.' '.$account->name, 'rows' => $rows, 'balance' => $running];
    }

    /**
     * @return array{total:string, lines:array<int,array<string,string>>}
     */
    private function sumByType(string $type): array
    {
        $total = Num::zero();
        $lines = [];

        foreach (Account::where('type', $type)->orderBy('code')->get() as $account) {
            $bal = $this->ledger->accountBalance($account);
            if (Num::isZero($bal)) {
                continue;
            }
            $total = Num::add($total, $bal);
            $lines[] = ['code' => $account->code, 'name' => $account->name, 'balance' => $bal];
        }

        return ['total' => $total, 'lines' => $lines];
    }
}
