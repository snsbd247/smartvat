<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\Expense;
use App\Shared\Numbering\NumberGenerator;
use App\Shared\Tenancy\CurrentCompany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Expense entry — a specialised journal: Dr Expense account / Cr Cash|Bank.
 */
class ExpenseService
{
    public function __construct(
        private CurrentCompany $current,
        private PostingService $posting,
        private NumberGenerator $numbers,
    ) {}

    public function record(array $data): Expense
    {
        return DB::transaction(function () use ($data) {
            $amount = (string) $data['amount'];

            $expense = Expense::create([
                'company_id' => $this->current->idOrFail(),
                'expense_no' => $this->numbers->next('expense', 'EXP-'),
                'date' => Carbon::parse($data['date'] ?? now())->toDateString(),
                'expense_account_id' => $data['expense_account_id'],
                'paid_from_account_id' => $data['paid_from_account_id'],
                'cost_center_id' => $data['cost_center_id'] ?? null,
                'amount' => $amount,
                'narration' => $data['narration'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $this->posting->post([
                'date' => $expense->date,
                'narration' => $data['narration'] ?? "Expense {$expense->expense_no}",
                'source_type' => 'expense',
                'source_id' => $expense->id,
                'purpose' => 'primary',
                'lines' => [
                    ['account_id' => $expense->expense_account_id, 'debit' => $amount, 'cost_center_id' => $expense->cost_center_id],
                    ['account_id' => $expense->paid_from_account_id, 'credit' => $amount],
                ],
            ]);

            return $expense;
        });
    }
}
