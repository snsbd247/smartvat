<?php

namespace App\Modules\Purchase\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Purchase\Models\ImportShipment;
use App\Modules\Purchase\Services\ImportService;
use App\Shared\Tenancy\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ImportController extends Controller
{
    public function __construct(
        private ImportService $imports,
        private CurrentCompany $current,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $this->authorize('purchase-manage');
        $companyId = $this->current->id();

        $data = $request->validate([
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')->where('company_id', $companyId)],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('company_id', $companyId)],
            'currency' => ['required', 'string', 'size:3'],
            'exchange_rate' => ['required', 'numeric', 'gt:0'],
            'incoterm' => ['nullable', 'string', 'max:8'],
            'bl_no' => ['nullable', 'string', 'max:255'],
            'date' => ['sometimes', 'date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.fc_unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.weight' => ['sometimes', 'numeric', 'min:0'],
            'lines.*.volume' => ['sometimes', 'numeric', 'min:0'],
            'charges' => ['sometimes', 'array'],
            'charges.*.type' => ['required_with:charges', 'string', 'max:24'],
            'charges.*.fc_amount' => ['required_with:charges', 'numeric', 'min:0'],
            'charges.*.exchange_rate' => ['sometimes', 'numeric', 'gt:0'],
            'charges.*.bc_amount' => ['sometimes', 'numeric', 'min:0'],
            'charges.*.recoverable' => ['sometimes', 'boolean'],
            'charges.*.allocation_basis' => ['sometimes', Rule::in(['quantity', 'weight', 'volume', 'value'])],
        ]);

        return response()->json($this->imports->createShipment($data), 201);
    }

    public function preview(ImportShipment $importShipment): JsonResponse
    {
        $this->authorize('purchase-view');

        return response()->json($this->imports->preview($importShipment));
    }

    public function receive(ImportShipment $importShipment): JsonResponse
    {
        $this->authorize('purchase-manage');

        return response()->json($this->imports->receive($importShipment));
    }
}
