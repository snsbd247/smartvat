<?php

namespace App\Modules\Manufacturing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Manufacturing\Models\ProductionOrder;
use App\Modules\Manufacturing\Services\ProductionService;
use App\Shared\Tenancy\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductionController extends Controller
{
    public function __construct(
        private ProductionService $production,
        private CurrentCompany $current,
    ) {}

    public function storeBom(Request $request): JsonResponse
    {
        $this->authorize('production-manage');
        $companyId = $this->current->id();

        $data = $request->validate([
            'product_id' => ['required', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'version' => ['sometimes', 'integer', 'min:1'],
            'output_qty' => ['sometimes', 'numeric', 'gt:0'],
            'effective_from' => ['sometimes', 'date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.component_product_id' => ['required', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
        ]);

        return response()->json($this->production->createBom($data), 201);
    }

    public function storeOrder(Request $request): JsonResponse
    {
        $this->authorize('production-manage');
        $companyId = $this->current->id();

        $data = $request->validate([
            'product_id' => ['required', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'bom_id' => ['nullable', Rule::exists('boms', 'id')->where('company_id', $companyId)],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('company_id', $companyId)],
            'planned_qty' => ['required', 'numeric', 'gt:0'],
            'date' => ['sometimes', 'date'],
        ]);

        return response()->json($this->production->createOrder($data), 201);
    }

    public function issue(Request $request, ProductionOrder $productionOrder): JsonResponse
    {
        $this->authorize('production-manage');
        $companyId = $this->current->id();

        $validated = $request->validate([
            'lines' => ['sometimes', 'array', 'min:1'],
            'lines.*.product_id' => ['required_with:lines', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'lines.*.qty' => ['required_with:lines', 'numeric', 'gt:0'],
            'lines.*.batch_id' => ['sometimes', 'integer', 'min:0'],
        ]);

        return response()->json(
            $this->production->issueMaterials($productionOrder, $validated['lines'] ?? null)
        );
    }

    public function receive(Request $request, ProductionOrder $productionOrder): JsonResponse
    {
        $this->authorize('production-manage');

        $data = $request->validate([
            'qty' => ['required', 'numeric', 'gt:0'],
            'scrap_qty' => ['sometimes', 'numeric', 'min:0'],
        ]);

        return response()->json(
            $this->production->receiveFinished($productionOrder, $data['qty'], $data['scrap_qty'] ?? 0)
        );
    }
}
