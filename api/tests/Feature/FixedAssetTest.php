<?php

use App\Modules\Accounting\Services\LedgerService;
use App\Modules\FixedAssets\Models\DepreciationEntry;
use App\Modules\FixedAssets\Services\FixedAssetService;

beforeEach(function () {
    $this->company = makeCompany('ACME');
    setCompanyContext($this->company);
    $this->assetAcc = makeAccount($this->company, '1500', 'asset');
    $this->accumAcc = makeAccount($this->company, '1590', 'asset'); // contra-asset
    $this->depExp = makeAccount($this->company, '5300', 'expense');
    $this->cash = makeAccount($this->company, '1000', 'asset');
    $this->svc = app(FixedAssetService::class);

    $this->category = $this->svc->createCategory([
        'code' => 'MACH', 'name' => 'Machinery', 'method' => 'straight_line',
        'useful_life_months' => 12,
        'asset_account_id' => $this->assetAcc->id,
        'depreciation_expense_account_id' => $this->depExp->id,
        'accumulated_account_id' => $this->accumAcc->id,
    ]);
});

function acc(string $code): float
{
    return (float) app(LedgerService::class)->accountBalance(
        App\Modules\Accounting\Models\Account::where('code', $code)->first()
    );
}

it('acquires an asset and posts Dr Asset / Cr Cash', function () {
    $asset = $this->svc->acquire([
        'asset_category_id' => $this->category->id,
        'name' => 'CNC Machine',
        'acquisition_date' => '2026-01-10',
        'cost' => 12000,
        'useful_life_months' => 12,
        'credit_account_id' => $this->cash->id,
    ]);

    expect((float) $asset->book_value)->toBe(12000.0);
    expect(acc('1500'))->toBe(12000.0);
    expect(acc('1000'))->toBe(-12000.0);
});

it('posts one month of straight-line depreciation', function () {
    $asset = $this->svc->acquire([
        'asset_category_id' => $this->category->id, 'name' => 'CNC', 'acquisition_date' => '2026-01-10',
        'cost' => 12000, 'useful_life_months' => 12, 'credit_account_id' => $this->cash->id,
    ]);

    $this->svc->depreciateMonth($asset, '2026-01-31'); // 12000/12 = 1000

    $asset->refresh();
    expect((float) $asset->accumulated_depreciation)->toBe(1000.0);
    expect((float) $asset->book_value)->toBe(11000.0);
    expect(acc('5300'))->toBe(1000.0);   // depreciation expense
    expect(acc('1590'))->toBe(-1000.0);  // accumulated (contra-asset, credit balance)
});

it('does not depreciate the same month twice (idempotent)', function () {
    $asset = $this->svc->acquire([
        'asset_category_id' => $this->category->id, 'name' => 'CNC', 'acquisition_date' => '2026-01-10',
        'cost' => 12000, 'useful_life_months' => 12, 'credit_account_id' => $this->cash->id,
    ]);

    $this->svc->depreciateMonth($asset, '2026-01-31');
    $second = $this->svc->depreciateMonth($asset->fresh(), '2026-01-15'); // same month

    expect($second)->toBeNull();
    expect(DepreciationEntry::where('fixed_asset_id', $asset->id)->count())->toBe(1);
    expect((float) $asset->fresh()->accumulated_depreciation)->toBe(1000.0);
});
