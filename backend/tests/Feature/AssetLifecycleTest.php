<?php

use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Services\LedgerService;
use App\Modules\FixedAssets\Models\AssetCategory;
use App\Modules\FixedAssets\Models\FixedAsset;
use App\Modules\FixedAssets\Services\FixedAssetService;

beforeEach(function () {
    $this->company = makeCompany('ACME');
    setCompanyContext($this->company);
    mapInventoryAccounts($this->company);
    // Asset + accumulated + depreciation-expense accounts for the category.
    $assetAcc = makeAccount($this->company, '1500', 'asset');
    $accumAcc = makeAccount($this->company, '1590', 'asset');
    $deprAcc = makeAccount($this->company, '5400', 'expense');
    $this->bank = makeAccount($this->company, '1010', 'asset');

    $this->category = AssetCategory::create([
        'company_id' => $this->company->id,
        'code' => 'MACH', 'name' => 'Machinery',
        'useful_life_months' => 60, 'method' => 'straight_line',
        'asset_account_id' => $assetAcc->id,
        'accumulated_account_id' => $accumAcc->id,
        'depreciation_expense_account_id' => $deprAcc->id,
    ]);
    $this->credit = makeAccount($this->company, '3000', 'equity');

    $this->asset = app(FixedAssetService::class)->acquire([
        'asset_category_id' => $this->category->id,
        'name' => 'Lathe', 'cost' => 100000, 'credit_account_id' => $this->credit->id,
    ]);
});

$bal = fn (string $code) => (float) app(LedgerService::class)->accountBalance(Account::where('code', $code)->first());

it('transfers location and custodian without any GL impact', function () use ($bal) {
    $before = $bal('1500');
    $mv = app(FixedAssetService::class)->transfer($this->asset, [
        'to_location' => 'Plant B', 'to_custodian' => 'Mr Rahim', 'note' => 'relocation',
    ]);

    expect($mv->to_location)->toBe('Plant B');
    expect($this->asset->fresh()->location)->toBe('Plant B');
    expect($this->asset->fresh()->custodian)->toBe('Mr Rahim');
    expect($bal('1500'))->toBe($before); // unchanged
});

it('revalues upward: Dr Asset / Cr Revaluation Reserve', function () use ($bal) {
    app(FixedAssetService::class)->revalue($this->asset, 120000);

    expect((float) $this->asset->fresh()->book_value)->toBe(120000.0);
    expect($bal('1500'))->toBe(120000.0);      // asset grossed up by 20000
    expect($bal('3100'))->toBe(20000.0);        // revaluation reserve (credit)
});

it('revalues downward: Dr Revaluation Loss / Cr Asset', function () use ($bal) {
    app(FixedAssetService::class)->revalue($this->asset, 90000);

    expect((float) $this->asset->fresh()->book_value)->toBe(90000.0);
    expect($bal('1500'))->toBe(90000.0);
    expect($bal('5310'))->toBe(10000.0);        // revaluation loss (5310)
});

it('records maintenance as an expense: Dr Maintenance / Cr Bank', function () use ($bal) {
    $rec = app(FixedAssetService::class)->maintenance($this->asset, [
        'amount' => 5000, 'paid_from_account_id' => $this->bank->id, 'description' => 'oil change',
    ]);

    expect($rec->maint_no)->toStartWith('AMT-');
    expect($bal('5210'))->toBe(5000.0);         // maintenance expense (5210)
    expect($bal('1010'))->toBe(-5000.0);        // bank credited
    expect((float) $this->asset->fresh()->cost)->toBe(100000.0); // not capitalised
});

it('exposes the lifecycle actions over the API', function () {
    \Laravel\Sanctum\Sanctum::actingAs(makeUserInCompany($this->company, 'admin'));
    $h = ['X-Company-Id' => (string) $this->company->id];

    $this->withHeaders($h)->postJson("/api/fixed-assets/{$this->asset->id}/transfer", ['to_location' => 'Store'])->assertCreated();
    $this->withHeaders($h)->postJson("/api/fixed-assets/{$this->asset->id}/revalue", ['book_value' => 110000])->assertOk();
    $this->withHeaders($h)->postJson("/api/fixed-assets/{$this->asset->id}/maintenance", ['amount' => 1000, 'paid_from_account_id' => $this->bank->id])->assertCreated();
});
