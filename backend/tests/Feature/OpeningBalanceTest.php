<?php

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\LedgerService;
use App\Modules\Accounting\Services\OpeningBalanceService;
use App\Modules\Inventory\Models\StockBalance;

beforeEach(function () {
    $this->company = makeCompany('ACME');
    setCompanyContext($this->company);
    mapInventoryAccounts($this->company);
    $this->wh = makeWarehouse($this->company, 'WH1');
    $this->p1 = makeProduct($this->company, 'P1');
    // A bank account for an opening account balance.
    $this->bank = makeAccount($this->company, '1010', 'asset');
});

$bal = fn (string $code) => (float) app(LedgerService::class)->accountBalance(Account::where('code', $code)->first());
$tbBalanced = function () {
    $tb = app(LedgerService::class)->trialBalance();
    $d = array_sum(array_map(fn ($r) => (float) $r['debit'], $tb));
    $c = array_sum(array_map(fn ($r) => (float) $r['credit'], $tb));
    return abs($d - $c) < 0.0001;
};

it('books opening stock: Dr Inventory / Cr Opening Balance Equity', function () use ($bal, $tbBalanced) {
    app(OpeningBalanceService::class)->openingStock($this->wh->id, [
        ['product_id' => $this->p1->id, 'qty' => 100, 'unit_cost' => 50],
    ]);

    expect((float) StockBalance::where('product_id', $this->p1->id)->where('owner_type', 'company')->value('qty'))->toBe(100.0);
    expect($bal('1300'))->toBe(5000.0);   // inventory
    expect($bal('3200'))->toBe(5000.0);   // opening balance equity (credit)
    expect($tbBalanced())->toBeTrue();
});

it('books an opening account balance and keeps the trial balance balanced', function () use ($bal, $tbBalanced) {
    app(OpeningBalanceService::class)->openingAccounts([
        ['account_id' => $this->bank->id, 'debit' => 20000],
    ]);

    expect($bal('1010'))->toBe(20000.0);   // bank debit
    expect($tbBalanced())->toBeTrue();
});

it('rejects a line that is both debit and credit', function () {
    expect(fn () => app(OpeningBalanceService::class)->openingAccounts([
        ['account_id' => $this->bank->id, 'debit' => 10, 'credit' => 5],
    ]))->toThrow(InvalidArgumentException::class);
});

it('exposes opening balances over the API', function () {
    \Laravel\Sanctum\Sanctum::actingAs(makeUserInCompany($this->company, 'admin'));
    $h = ['X-Company-Id' => (string) $this->company->id];

    $this->withHeaders($h)->postJson('/api/accounting/opening-stock', [
        'warehouse_id' => $this->wh->id,
        'lines' => [['product_id' => $this->p1->id, 'qty' => 10, 'unit_cost' => 5]],
    ])->assertCreated();

    $this->withHeaders($h)->postJson('/api/accounting/opening-accounts', [
        'lines' => [['account_id' => $this->bank->id, 'debit' => 1000]],
    ])->assertCreated()->assertJsonPath('posted', 1);
});
