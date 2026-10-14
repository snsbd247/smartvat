<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Budget;
use App\Shared\Support\Num;
use App\Shared\Tenancy\CurrentCompany;
use Illuminate\Support\Facades\DB;

class BudgetService
{
    public function __construct(
        private CurrentCompany $current,
        private LedgerService $ledger,
    ) {}

    public function create(array $data): Budget
    {
        return DB::transaction(function () use ($data) {
            $budget = Budget::create([
                'company_id' => $this->current->idOrFail(),
                'name' => $data['name'],
                'financial_year_id' => $data['financial_year_id'] ?? null,
                'status' => 'active',
                'created_by' => auth()->id(),
            ]);

            foreach ($data['lines'] as $l) {
                $budget->lines()->create([
                    'company_id' => $budget->company_id,
                    'account_id' => $l['account_id'],
                    'amount' => $l['amount'],
                ]);
            }

            return $budget->load('lines');
        });
    }

    /** Budget vs actual: budgeted amount against the account's current balance. */
    public function vsActual(Budget $budget): array
    {
        $rows = [];
        foreach ($budget->lines()->get() as $line) {
            $account = Account::find($line->account_id);
            $actual = $account ? $this->ledger->accountBalance($account) : Num::zero();
            $rows[] = [
                'account_id' => $line->account_id,
                'account' => $account ? $account->code.' '.$account->name : '',
                'budget' => (string) $line->amount,
                'actual' => $actual,
                'variance' => Num::sub((string) $line->amount, $actual),
            ];
        }

        return $rows;
    }
}
