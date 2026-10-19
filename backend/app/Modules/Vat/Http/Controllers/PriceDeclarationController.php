<?php

namespace App\Modules\Vat\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Vat\Models\PriceDeclaration;
use App\Modules\Vat\Services\PriceDeclarationService;
use App\Shared\Tenancy\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PriceDeclarationController extends Controller
{
    public function __construct(private PriceDeclarationService $service, private CurrentCompany $current) {}

    public function index(): JsonResponse
    {
        $this->authorize('vat-view');

        return response()->json(
            PriceDeclaration::with('product:id,code,name')->orderByDesc('id')->limit(300)->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('vat-manage');
        $companyId = $this->current->id();

        $data = $request->validate([
            'product_id' => ['required', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'declared_price' => ['required', 'numeric', 'min:0'],
            'purchase_value' => ['nullable', 'numeric', 'min:0'],
            'effective_from' => ['sometimes', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json($this->service->create($data), 201);
    }

    public function approve(PriceDeclaration $priceDeclaration): JsonResponse
    {
        $this->authorize('vat-manage');

        return response()->json($this->service->approve($priceDeclaration));
    }

    /** Current approved declared price for a product (used at sales time). */
    public function current(int $productId): JsonResponse
    {
        $this->authorize('vat-view');
        $decl = $this->service->currentFor($productId);

        return response()->json([
            'product_id' => $productId,
            'declared_price' => $decl?->declared_price,
            'effective_from' => $decl?->effective_from,
            'decl_no' => $decl?->decl_no,
        ]);
    }
}
