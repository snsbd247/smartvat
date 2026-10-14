<?php

namespace App\Modules\Sales\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Sales\Models\Customer;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Services\CustomerLedgerService;
use App\Modules\Sales\Services\SalesService;
use App\Shared\Tenancy\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SalesController extends Controller
{
    public function __construct(
        private SalesService $sales,
        private CustomerLedgerService $ledger,
        private CurrentCompany $current,
    ) {}

    public function invoice(Request $request): JsonResponse
    {
        $this->authorize('sales-manage');
        $companyId = $this->current->id();

        $data = $request->validate([
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('company_id', $companyId)],
            'kind' => ['sometimes', Rule::in(['local', 'export'])],
            'currency' => ['sometimes', 'string', 'size:3'],
            'exchange_rate' => ['sometimes', 'numeric', 'gt:0'],
            'date' => ['sometimes', 'date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['nullable', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.qty' => ['sometimes', 'numeric'],
            'lines.*.unit_price' => ['sometimes', 'numeric'],
            'lines.*.line_total' => ['required', 'numeric', 'min:0'],
        ]);

        return response()->json($this->sales->postInvoice($data), 201);
    }

    /** List quotations and sales orders, optionally filtered by status. */
    public function orders(Request $request): JsonResponse
    {
        $this->authorize('sales-view');
        $status = $request->query('status');

        $rows = SalesOrder::query()
            ->when(in_array($status, ['quotation', 'confirmed', 'invoiced', 'cancelled'], true), fn ($q) => $q->where('status', $status))
            ->with('lines')
            ->orderByDesc('id')
            ->limit(300)
            ->get();

        return response()->json($rows);
    }

    /** Create a quotation (status=quotation) or a firm order (status=confirmed). */
    public function storeOrder(Request $request): JsonResponse
    {
        $this->authorize('sales-manage');
        $companyId = $this->current->id();

        $data = $request->validate([
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('company_id', $companyId)],
            'status' => ['sometimes', Rule::in(['quotation', 'confirmed'])],
            'kind' => ['sometimes', Rule::in(['local', 'export'])],
            'currency' => ['sometimes', 'string', 'size:3'],
            'exchange_rate' => ['sometimes', 'numeric', 'gt:0'],
            'date' => ['sometimes', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['nullable', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.line_total' => ['sometimes', 'numeric', 'min:0'],
        ]);

        $status = $data['status'] ?? 'confirmed';

        return response()->json($this->sales->createOrder($data, $status), 201);
    }

    /** Accept a quotation, turning it into a confirmed order. */
    public function confirmOrder(SalesOrder $salesOrder): JsonResponse
    {
        $this->authorize('sales-manage');

        return response()->json($this->sales->confirmOrder($salesOrder));
    }

    /** Raise a sales invoice from a confirmed order. */
    public function invoiceOrder(SalesOrder $salesOrder): JsonResponse
    {
        $this->authorize('sales-manage');

        return response()->json($this->sales->invoiceFromOrder($salesOrder), 201);
    }

    public function deliver(Request $request): JsonResponse
    {
        $this->authorize('sales-manage');
        $companyId = $this->current->id();

        $data = $request->validate([
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('company_id', $companyId)],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('company_id', $companyId)],
            'sales_invoice_id' => ['nullable', Rule::exists('sales_invoices', 'id')->where('company_id', $companyId)],
            'date' => ['sometimes', 'date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.batch_id' => ['sometimes', 'integer', 'min:0'],
        ]);

        return response()->json($this->sales->deliver($data), 201);
    }

    public function receipt(Request $request): JsonResponse
    {
        $this->authorize('sales-manage');
        $companyId = $this->current->id();

        $data = $request->validate([
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('company_id', $companyId)],
            'received_in_account_id' => ['required', Rule::exists('chart_of_accounts', 'id')->where('company_id', $companyId)],
            'kind' => ['sometimes', Rule::in(['local', 'export'])],
            'amount' => ['required_without_all:fc_amount', 'numeric', 'gt:0'],
            'fc_amount' => ['required_if:kind,export', 'numeric', 'gt:0'],
            'realization_rate' => ['required_if:kind,export', 'numeric', 'gt:0'],
            'invoice_rate' => ['required_if:kind,export', 'numeric', 'gt:0'],
            'date' => ['sometimes', 'date'],
        ]);

        return response()->json($this->sales->recordReceipt($data), 201);
    }

    public function salesReturn(Request $request): JsonResponse
    {
        $this->authorize('sales-manage');
        $companyId = $this->current->id();

        $data = $request->validate([
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('company_id', $companyId)],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('company_id', $companyId)],
            'sales_invoice_id' => ['nullable', Rule::exists('sales_invoices', 'id')->where('company_id', $companyId)],
            'date' => ['sometimes', 'date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.batch_id' => ['sometimes', 'integer', 'min:0'],
        ]);

        return response()->json($this->sales->returnGoods($data), 201);
    }

    public function customerLedger(Customer $customer): JsonResponse
    {
        $this->authorize('sales-view');

        return response()->json([
            'customer_id' => $customer->id,
            'outstanding' => $this->ledger->outstanding($customer->id),
            'entries' => $this->ledger->ledger($customer->id),
        ]);
    }
}
