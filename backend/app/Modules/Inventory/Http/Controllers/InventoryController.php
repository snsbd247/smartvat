<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Services\InventoryReportService;
use App\Modules\Inventory\Services\InventoryService;
use App\Shared\Tenancy\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function __construct(
        private InventoryService $inventory,
        private InventoryReportService $reports,
        private CurrentCompany $current,
    ) {}

    public function receipt(Request $request): JsonResponse
    {
        $this->authorize('inventory-operate');
        $data = $this->validatePayload($request, requireCost: true);

        return $this->ok($this->inventory->receipt($data));
    }

    public function issue(Request $request): JsonResponse
    {
        $this->authorize('inventory-operate');
        $data = $this->validatePayload($request);

        return $this->ok($this->inventory->issue($data));
    }

    public function adjustment(Request $request): JsonResponse
    {
        $this->authorize('inventory-operate');
        $data = $this->validatePayload($request, allowNegativeQty: true);

        return $this->ok($this->inventory->adjustment($data));
    }

    public function transfer(Request $request): JsonResponse
    {
        $this->authorize('inventory-operate');
        $data = $this->validatePayload($request, transfer: true);

        return $this->ok($this->inventory->transfer($data));
    }

    public function stock(): JsonResponse
    {
        $this->authorize('inventory-view');

        return response()->json(['data' => $this->reports->valuation()]);
    }

    public function ledger(Request $request): JsonResponse
    {
        $this->authorize('inventory-view');

        return response()->json([
            'data' => $this->reports->ledger($request->only('product_id', 'warehouse_id')),
        ]);
    }

    public function reconciliation(): JsonResponse
    {
        $this->authorize('inventory-view');

        return response()->json($this->reports->reconciliation());
    }

    // ---- helpers ------------------------------------------------------------

    private function validatePayload(
        Request $request,
        bool $requireCost = false,
        bool $allowNegativeQty = false,
        bool $transfer = false,
    ): array {
        $companyId = $this->current->id();
        $qtyRule = $allowNegativeQty ? ['required', 'numeric', 'not_in:0'] : ['required', 'numeric', 'gt:0'];
        $costRule = $requireCost ? ['required', 'numeric', 'min:0'] : ['sometimes', 'numeric', 'min:0'];

        return $request->validate([
            'date' => ['sometimes', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'narration' => ['nullable', 'string', 'max:255'],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('company_id', $companyId)],
            'to_warehouse_id' => [
                Rule::requiredIf($transfer),
                'nullable',
                Rule::exists('warehouses', 'id')->where('company_id', $companyId),
            ],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'lines.*.qty' => $qtyRule,
            'lines.*.unit_cost' => $costRule,
            'lines.*.batch_id' => ['sometimes', 'integer', 'min:0'],
        ]);
    }

    private function ok($txn): JsonResponse
    {
        return response()->json([
            'id' => $txn->id,
            'type' => $txn->type,
            'txn_no' => $txn->txn_no,
            'date' => $txn->date->toDateString(),
            'status' => $txn->status,
        ], 201);
    }
}
