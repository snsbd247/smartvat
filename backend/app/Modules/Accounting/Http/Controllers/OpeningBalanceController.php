<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Services\OpeningBalanceService;
use App\Shared\Tenancy\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OpeningBalanceController extends Controller
{
    public function __construct(private OpeningBalanceService $service, private CurrentCompany $current) {}

    public function stock(Request $request): JsonResponse
    {
        $this->authorize('accounting-manage');
        $companyId = $this->current->id();

        $data = $request->validate([
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('company_id', $companyId)],
            'date' => ['sometimes', 'date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0'],
        ]);

        return response()->json($this->service->openingStock((int) $data['warehouse_id'], $data['lines'], $data['date'] ?? null), 201);
    }

    public function accounts(Request $request): JsonResponse
    {
        $this->authorize('accounting-manage');
        $companyId = $this->current->id();

        $data = $request->validate([
            'date' => ['sometimes', 'date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.account_id' => ['required', Rule::exists('chart_of_accounts', 'id')->where('company_id', $companyId)],
            'lines.*.debit' => ['sometimes', 'numeric', 'min:0'],
            'lines.*.credit' => ['sometimes', 'numeric', 'min:0'],
        ]);

        $count = $this->service->openingAccounts($data['lines'], $data['date'] ?? null);

        return response()->json(['posted' => $count], 201);
    }
}
