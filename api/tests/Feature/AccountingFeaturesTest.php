<?php

use App\Modules\Accounting\Models\Budget;
use App\Modules\Accounting\Models\JournalLine;
use App\Modules\Accounting\Services\BankReconciliationService;
use App\Modules\Accounting\Services\BudgetService;
use App\Modules\Accounting\Services\ExpenseService;
use App\Modules\Accounting\Services\LedgerService;
use App\Modules\Accounting\Services\PostingService;

beforeEach(function () {
    $this->company = makeCompany('ACME');
    setCompanyContext($this->company);
    $this->bank = makeAccount($this->company, '1010', 'asset');
    $this->capital = makeAccount($this->company, '3000', 'equity');
    $this->expenseAcc = makeAccount($this->company, '5000', 'expense');
    $this->cash = makeAccount($this->company, '1000', 'asset');
    $this->post = app(PostingService::class);
});

it('reconciles cleared bank lines against a statement balance', function () {
    $this->post->post(['date' => '2026-10-13', 'lines' => [
        ['account_id' => $this->bank->id, 'debit' => 1000],
        ['account_id' => $this->capital->id, 'credit' => 1000],
    ]]);
    $line = JournalLine::where('account_id', $this->bank->id)->first();

    $svc = app(BankReconciliationService::class);
    expect($svc->unreconciledLines($this->bank->id))->toHaveCount(1);

    $rec = $svc->reconcile($this->bank->id, '2026-10-31', 1000, [$line->id]);

    expect((float) $rec->reconciled_balance)->toBe(1000.0);
    expect((float) $rec->difference)->toBe(0.0);
    expect($svc->unreconciledLines($this->bank->id))->toHaveCount(0);
    expect((bool) $line->fresh()->reconciled)->toBeTrue();
});

it('computes budget vs actual', function () {
    $budget = app(BudgetService::class)->create([
        'name' => 'Opex 2026',
        'lines' => [['account_id' => $this->expenseAcc->id, 'amount' => 1000]],
    ]);

    // Actual expense of 300 against the account.
    app(ExpenseService::class)->record([
        'expense_account_id' => $this->expenseAcc->id,
        'paid_from_account_id' => $this->cash->id,
        'amount' => 300,
    ]);

    $rows = app(BudgetService::class)->vsActual(Budget::find($budget->id));
    expect((float) $rows[0]['budget'])->toBe(1000.0);
    expect((float) $rows[0]['actual'])->toBe(300.0);
    expect((float) $rows[0]['variance'])->toBe(700.0);
});

it('records an expense as Dr Expense / Cr Cash', function () {
    app(ExpenseService::class)->record([
        'expense_account_id' => $this->expenseAcc->id,
        'paid_from_account_id' => $this->cash->id,
        'amount' => 250,
        'narration' => 'Office supplies',
    ]);

    $ledger = app(LedgerService::class);
    expect((float) $ledger->accountBalance($this->expenseAcc->fresh()))->toBe(250.0);
    expect((float) $ledger->accountBalance($this->cash->fresh()))->toBe(-250.0);
});
