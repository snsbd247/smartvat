<?php

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\AccountingReportService;
use App\Modules\Accounting\Services\PostingService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->company = makeCompany('ACME');
    setCompanyContext($this->company);
    $this->cash = makeAccount($this->company, '1000', 'asset');
    $this->bank = makeAccount($this->company, '1010', 'asset');
    $this->capital = makeAccount($this->company, '3000', 'equity');
    $this->revenue = makeAccount($this->company, '4000', 'income');
    $this->expense = makeAccount($this->company, '5000', 'expense');
    $this->post = app(PostingService::class);
    $this->reports = app(AccountingReportService::class);
});

it('computes profit and loss from income and expense', function () {
    // Capital injection.
    $this->post->post(['date' => '2026-10-13', 'lines' => [
        ['account_id' => $this->cash->id, 'debit' => 1000],
        ['account_id' => $this->capital->id, 'credit' => 1000],
    ]]);
    // Revenue.
    $this->post->post(['date' => '2026-10-13', 'lines' => [
        ['account_id' => $this->cash->id, 'debit' => 500],
        ['account_id' => $this->revenue->id, 'credit' => 500],
    ]]);
    // Expense.
    $this->post->post(['date' => '2026-10-13', 'lines' => [
        ['account_id' => $this->expense->id, 'debit' => 200],
        ['account_id' => $this->cash->id, 'credit' => 200],
    ]]);

    $pl = $this->reports->profitAndLoss();
    expect((float) $pl['income'])->toBe(500.0);
    expect((float) $pl['expense'])->toBe(200.0);
    expect((float) $pl['net_profit'])->toBe(300.0);
});

it('produces a balance sheet that balances (assets = liabilities + equity + profit)', function () {
    $this->post->post(['date' => '2026-10-13', 'lines' => [
        ['account_id' => $this->cash->id, 'debit' => 1000],
        ['account_id' => $this->capital->id, 'credit' => 1000],
    ]]);
    $this->post->post(['date' => '2026-10-13', 'lines' => [
        ['account_id' => $this->cash->id, 'debit' => 500],
        ['account_id' => $this->revenue->id, 'credit' => 500],
    ]]);
    $this->post->post(['date' => '2026-10-13', 'lines' => [
        ['account_id' => $this->expense->id, 'debit' => 200],
        ['account_id' => $this->cash->id, 'credit' => 200],
    ]]);

    $bs = $this->reports->balanceSheet();
    expect($bs['in_balance'])->toBeTrue();
    expect((float) $bs['assets'])->toBe(1300.0);       // cash 1000+500-200
    expect((float) $bs['net_profit'])->toBe(300.0);
});

it('posts a fund transfer via API (Dr to / Cr from)', function () {
    $admin = makeUserInCompany($this->company, 'admin');
    Sanctum::actingAs($admin);

    $this->withHeader('X-Company-Id', (string) $this->company->id)
        ->postJson('/api/accounting/fund-transfers', [
            'from_account_id' => $this->cash->id,
            'to_account_id' => $this->bank->id,
            'amount' => 400,
        ])->assertCreated();

    expect((float) app(App\Modules\Accounting\Services\LedgerService::class)->accountBalance($this->bank->fresh()))->toBe(400.0);
    expect((float) app(App\Modules\Accounting\Services\LedgerService::class)->accountBalance($this->cash->fresh()))->toBe(-400.0);
});

it('rejects an unbalanced manual journal via API with 422', function () {
    $admin = makeUserInCompany($this->company, 'admin');
    Sanctum::actingAs($admin);

    $this->withHeader('X-Company-Id', (string) $this->company->id)
        ->postJson('/api/accounting/journals', [
            'date' => '2026-10-13',
            'lines' => [
                ['account_id' => $this->cash->id, 'debit' => 100],
                ['account_id' => $this->revenue->id, 'credit' => 90],
            ],
        ])->assertStatus(422);
});
