<?php

namespace App\Modules\Manufacturing\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Manufacturing\Models\Bom;
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

    public function boms(): JsonResponse
    {
        $this->authorize('production-view');

        return response()->json(Bom::with('lines')->orderByDesc('id')->get());
    }

    public function orders(): JsonResponse
    {
        $this->authorize('production-view');

        return response()->json(ProductionOrder::orderByDesc('id')->limit(100)->get());
    }

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
            'planned_start_date' => ['sometimes', 'nullable', 'date'],
            'planned_end_date' => ['sometimes', 'nullable', 'date'],
        ]);

        return response()->json($this->production->createOrder($data), 201);
    }

    public function qc(Request $request, ProductionOrder $productionOrder): JsonResponse
    {
        $this->authorize('production-manage');

        $data = $request->validate([
            'inspected_qty' => ['required', 'numeric', 'gt:0'],
            'passed_qty' => ['required', 'numeric', 'min:0'],
            'rejected_qty' => ['sometimes', 'numeric', 'min:0'],
            'inspector' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:255'],
            'date' => ['sometimes', 'date'],
        ]);

        return response()->json($this->production->recordQc($productionOrder, $data), 201);
    }

    public function qcList(): JsonResponse
    {
        $this->authorize('production-view');

        return response()->json(
            \App\Modules\Manufacturing\Models\ProductionQc::with('order:id,order_no')->orderByDesc('id')->limit(300)->get()
        );
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
