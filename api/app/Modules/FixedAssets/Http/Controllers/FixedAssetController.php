<?php

namespace App\Modules\FixedAssets\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FixedAssets\Models\FixedAsset;
use App\Modules\FixedAssets\Services\FixedAssetService;
use App\Shared\Tenancy\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FixedAssetController extends Controller
{
    public function __construct(
        private FixedAssetService $assets,
        private CurrentCompany $current,
    ) {}

    public function index(): JsonResponse
    {
        $this->authorize('assets-view');

        return response()->json(FixedAsset::orderBy('asset_no')->paginate(25));
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $this->authorize('assets-manage');
        $companyId = $this->current->id();
        $acc = Rule::exists('chart_of_accounts', 'id')->where('company_id', $companyId);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', Rule::unique('asset_categories', 'code')->where('company_id', $companyId)],
            'name' => ['required', 'string', 'max:255'],
            'method' => ['sometimes', Rule::in(['straight_line'])],
            'useful_life_months' => ['sometimes', 'integer', 'min:1'],
            'asset_account_id' => ['required', $acc],
            'depreciation_expense_account_id' => ['required', $acc],
            'accumulated_account_id' => ['required', $acc],
        ]);

        return response()->json($this->assets->createCategory($data), 201);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('assets-manage');
        $companyId = $this->current->id();

        $data = $request->validate([
            'asset_category_id' => ['required', Rule::exists('asset_categories', 'id')->where('company_id', $companyId)],
            'name' => ['required', 'string', 'max:255'],
            'acquisition_date' => ['required', 'date'],
            'cost' => ['required', 'numeric', 'gt:0'],
            'salvage_value' => ['sometimes', 'numeric', 'min:0'],
            'useful_life_months' => ['sometimes', 'integer', 'min:1'],
            'credit_account_id' => ['required', Rule::exists('chart_of_accounts', 'id')->where('company_id', $companyId)],
        ]);

        return response()->json($this->assets->acquire($data), 201);
    }

    public function runDepreciation(Request $request): JsonResponse
    {
        $this->authorize('assets-manage');
        $data = $request->validate(['month' => ['sometimes', 'date']]);

        $count = $this->assets->runDepreciation($data['month'] ?? now());

        return response()->json(['depreciated_assets' => $count]);
    }
}
