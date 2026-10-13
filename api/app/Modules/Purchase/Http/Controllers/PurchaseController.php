<?php

namespace App\Modules\Purchase\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Purchase\Models\PurchaseOrder;
use App\Modules\Purchase\Models\Supplier;
use App\Modules\Purchase\Services\PurchaseService;
use App\Modules\Purchase\Services\SupplierLedgerService;
use App\Shared\Tenancy\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PurchaseController extends Controller
{
    public function __construct(
        private PurchaseService $purchase,
        private SupplierLedgerService $ledger,
        private CurrentCompany $current,
    ) {}

    public function storeOrder(Request $request): JsonResponse
    {
        $this->authorize('purchase-manage');
        $companyId = $this->current->id();

        $data = $request->validate([
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')->where('company_id', $companyId)],
            'date' => ['sometimes', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['nullable', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.is_service' => ['sometimes', 'boolean'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        return response()->json($this->purchase->createOrder($data), 201);
    }

    public function approveOrder(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('purchase-manage');

        return response()->json($this->purchase->approveOrder($purchaseOrder));
    }

    public function receiveGoods(Request $request): JsonResponse
    {
        $this->authorize('purchase-manage');
        $companyId = $this->current->id();

        $data = $request->validate([
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')->where('company_id', $companyId)],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('company_id', $companyId)],
            'purchase_order_id' => ['nullable', Rule::exists('purchase_orders', 'id')->where('company_id', $companyId)],
            'date' => ['sometimes', 'date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'lines.*.batch_id' => ['sometimes', 'integer', 'min:0'],
        ]);

        return response()->json($this->purchase->receiveGoods($data), 201);
    }

    public function postInvoice(Request $request): JsonResponse
    {
        $this->authorize('purchase-manage');
        $companyId = $this->current->id();

        $data = $request->validate([
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')->where('company_id', $companyId)],
            'type' => ['required', Rule::in(['material', 'service'])],
            'goods_receipt_note_id' => ['nullable', Rule::exists('goods_receipt_notes', 'id')->where('company_id', $companyId)],
            'purchase_order_id' => ['nullable', Rule::exists('purchase_orders', 'id')->where('company_id', $companyId)],
            'date' => ['sometimes', 'date'],
            'supplier_invoice_no' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['nullable', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'lines.*.account_id' => ['nullable', Rule::exists('chart_of_accounts', 'id')->where('company_id', $companyId)],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.qty' => ['sometimes', 'numeric'],
            'lines.*.unit_price' => ['sometimes', 'numeric'],
            'lines.*.line_total' => ['required', 'numeric', 'min:0'],
        ]);

        return response()->json($this->purchase->postInvoice($data), 201);
    }

    public function paySupplier(Request $request): JsonResponse
    {
        $this->authorize('purchase-manage');
        $companyId = $this->current->id();

        $data = $request->validate([
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')->where('company_id', $companyId)],
            'amount' => ['required', 'numeric', 'gt:0'],
            'paid_from_account_id' => ['required', Rule::exists('chart_of_accounts', 'id')->where('company_id', $companyId)],
            'date' => ['sometimes', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json($this->purchase->paySupplier($data), 201);
    }

    public function returnGoods(Request $request): JsonResponse
    {
        $this->authorize('purchase-manage');
        $companyId = $this->current->id();

        $data = $request->validate([
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')->where('company_id', $companyId)],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('company_id', $companyId)],
            'date' => ['sometimes', 'date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.batch_id' => ['sometimes', 'integer', 'min:0'],
        ]);

        return response()->json($this->purchase->returnGoods($data), 201);
    }

    public function supplierLedger(Supplier $supplier): JsonResponse
    {
        $this->authorize('purchase-view');

        return response()->json([
            'supplier_id' => $supplier->id,
            'outstanding' => $this->ledger->outstanding($supplier->id),
            'entries' => $this->ledger->ledger($supplier->id),
        ]);
    }
}
