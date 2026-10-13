<?php

namespace App\Modules\FixedAssets\Services;

use App\Modules\Accounting\Services\AccountResolver;
use App\Modules\Accounting\Services\PostingService;
use App\Modules\FixedAssets\Models\AssetCategory;
use App\Modules\FixedAssets\Models\DepreciationEntry;
use App\Modules\FixedAssets\Models\FixedAsset;
use App\Shared\Numbering\NumberGenerator;
use App\Shared\Support\Num;
use App\Shared\Tenancy\CurrentCompany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Fixed assets, integrated with the posting engine.
 *   Acquire      : Dr Asset / Cr (cash/bank/payable).
 *   Depreciation : Dr Depreciation expense / Cr Accumulated depreciation (per month,
 *                  straight-line, idempotent per asset+month, capped at depreciable base).
 */
class FixedAssetService
{
    public function __construct(
        private CurrentCompany $current,
        private PostingService $posting,
        private NumberGenerator $numbers,
        private AccountResolver $accounts,
    ) {}

    public function acquire(array $data): FixedAsset
    {
        return DB::transaction(function () use ($data) {
            $category = AssetCategory::findOrFail($data['asset_category_id']);
            $cost = (string) $data['cost'];

            $asset = FixedAsset::create([
                'company_id' => $this->current->idOrFail(),
                'asset_no' => $this->numbers->next('fixed_asset', 'FA-'),
                'asset_category_id' => $category->id,
                'name' => $data['name'],
                'acquisition_date' => Carbon::parse($data['acquisition_date'] ?? now())->toDateString(),
                'cost' => $cost,
                'salvage_value' => $data['salvage_value'] ?? 0,
                'useful_life_months' => $data['useful_life_months'] ?? $category->useful_life_months,
                'method' => $category->method,
                'accumulated_depreciation' => 0,
                'book_value' => $cost,
                'status' => 'active',
                'created_by' => auth()->id(),
            ]);

            $this->posting->post([
                'date' => $asset->acquisition_date,
                'narration' => "Asset acquisition {$asset->asset_no}",
                'source_type' => 'fixed_asset',
                'source_id' => $asset->id,
                'purpose' => 'acquisition',
                'lines' => [
                    ['account_id' => $category->asset_account_id, 'debit' => $cost],
                    ['account_id' => $data['credit_account_id'], 'credit' => $cost],
                ],
            ]);

            return $asset;
        });
    }

    /**
     * Post one month of straight-line depreciation for an asset. Idempotent per month.
     */
    public function depreciateMonth(FixedAsset $asset, string|\DateTimeInterface|null $monthDate = null): ?DepreciationEntry
    {
        $date = Carbon::parse($monthDate ?? now());
        $period = $date->format('Y-m');

        if ($asset->status !== 'active') {
            return null;
        }

        // Already depreciated this month?
        if (DepreciationEntry::where('fixed_asset_id', $asset->id)->where('period', $period)->exists()) {
            return null;
        }

        $depreciable = Num::sub((string) $asset->cost, (string) $asset->salvage_value);
        $remaining = Num::sub($depreciable, (string) $asset->accumulated_depreciation);
        if (Num::cmp($remaining, 0) <= 0) {
            return null; // fully depreciated
        }

        $monthly = Num::div($depreciable, (string) $asset->useful_life_months);
        if (Num::cmp($monthly, $remaining) > 0) {
            $monthly = $remaining; // last period cap
        }

        return DB::transaction(function () use ($asset, $period, $date, $monthly) {
            $category = $asset->category;

            $this->posting->post([
                'date' => $date->toDateString(),
                'narration' => "Depreciation {$asset->asset_no} {$period}",
                'source_type' => 'fixed_asset',
                'source_id' => $asset->id,
                'purpose' => 'depreciation_'.$period,
                'lines' => [
                    ['account_id' => $category->depreciation_expense_account_id, 'debit' => $monthly],
                    ['account_id' => $category->accumulated_account_id, 'credit' => $monthly],
                ],
            ]);

            $entry = DepreciationEntry::create([
                'company_id' => $asset->company_id,
                'fixed_asset_id' => $asset->id,
                'period' => $period,
                'amount' => $monthly,
                'date' => $date->toDateString(),
            ]);

            $asset->update([
                'accumulated_depreciation' => Num::add((string) $asset->accumulated_depreciation, $monthly),
                'book_value' => Num::sub((string) $asset->book_value, $monthly),
            ]);

            return $entry;
        });
    }

    /** Run depreciation for all active assets for a given month. */
    public function runDepreciation(string|\DateTimeInterface|null $monthDate = null): int
    {
        $count = 0;
        foreach (FixedAsset::where('status', 'active')->get() as $asset) {
            if ($this->depreciateMonth($asset, $monthDate) !== null) {
                $count++;
            }
        }

        return $count;
    }

    public function createCategory(array $data): AssetCategory
    {
        return AssetCategory::create(array_merge($data, ['company_id' => $this->current->idOrFail()]));
    }

    /**
     * Dispose an asset: remove cost and accumulated depreciation, book proceeds, and
     * recognise the gain/loss vs book value.
     *   Dr Accumulated depreciation, Dr Cash|Bank (proceeds), Cr Asset (cost),
     *   and the balancing difference to the asset-disposal gain/loss account.
     */
    public function dispose(FixedAsset $asset, string|float $proceeds, int $receivedAccountId, string|\DateTimeInterface|null $date = null): FixedAsset
    {
        if ($asset->status === 'disposed') {
            throw new InvalidArgumentException('Asset is already disposed.');
        }

        return DB::transaction(function () use ($asset, $proceeds, $receivedAccountId, $date) {
            $category = $asset->category;
            $cost = (string) $asset->cost;
            $accum = (string) $asset->accumulated_depreciation;
            $bookValue = (string) $asset->book_value;
            $proceeds = (string) $proceeds;

            $lines = [];
            if (! Num::isZero($accum)) {
                $lines[] = ['account_id' => $category->accumulated_account_id, 'debit' => $accum];
            }
            if (! Num::isZero($proceeds)) {
                $lines[] = ['account_id' => $receivedAccountId, 'debit' => $proceeds];
            }
            $lines[] = ['account_id' => $category->asset_account_id, 'credit' => $cost];

            $gain = Num::sub($proceeds, $bookValue); // >0 gain, <0 loss
            if (! Num::isZero($gain)) {
                $disposal = $this->accounts->id('asset_disposal');
                if (Num::cmp($gain, 0) > 0) {
                    $lines[] = ['account_id' => $disposal, 'credit' => $gain];
                } else {
                    $lines[] = ['account_id' => $disposal, 'debit' => Num::sub(0, $gain)];
                }
            }

            $this->posting->post([
                'date' => Carbon::parse($date ?? now())->toDateString(),
                'narration' => "Asset disposal {$asset->asset_no}",
                'source_type' => 'fixed_asset',
                'source_id' => $asset->id,
                'purpose' => 'disposal',
                'lines' => $lines,
            ]);

            $asset->update(['status' => 'disposed', 'book_value' => 0]);

            return $asset->fresh();
        });
    }
}
