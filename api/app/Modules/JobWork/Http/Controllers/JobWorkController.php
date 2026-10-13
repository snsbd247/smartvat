<?php

namespace App\Modules\JobWork\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\JobWork\Models\JobWorkOrder;
use App\Modules\JobWork\Services\JobWorkService;
use App\Shared\Tenancy\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JobWorkController extends Controller
{
    public function __construct(
        private JobWorkService $jobWork,
        private CurrentCompany $current,
    ) {}

    public function storeContract(Request $request): JsonResponse
    {
        $this->authorize('jobwork-manage');
        $companyId = $this->current->id();
        $data = $request->validate([
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('company_id', $companyId)],
            'processing_rate' => ['sometimes', 'numeric', 'min:0'],
            'scrap_ownership' => ['sometimes', Rule::in(['customer', 'company'])],
            'terms' => ['nullable', 'string', 'max:255'],
            'date' => ['sometimes', 'date'],
        ]);

        return response()->json($this->jobWork->createContract($data), 201);
    }

    public function storeOrder(Request $request): JsonResponse
    {
        $this->authorize('jobwork-manage');
        $companyId = $this->current->id();
        $data = $request->validate([
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('company_id', $companyId)],
            'manufacturing_contract_id' => ['nullable', Rule::exists('manufacturing_contracts', 'id')->where('company_id', $companyId)],
            'product_id' => ['nullable', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('company_id', $companyId)],
            'planned_qty' => ['sometimes', 'numeric', 'min:0'],
        ]);

        return response()->json($this->jobWork->createOrder($data), 201);
    }

    public function receiveMaterial(Request $request, JobWorkOrder $jobWorkOrder): JsonResponse
    {
        $this->authorize('jobwork-manage');
        $lines = $this->validateLines($request);
        $this->jobWork->receiveCustomerMaterial($jobWorkOrder, $lines);

        return response()->json(['message' => 'Customer material received into custody.'], 201);
    }

    public function consumeMaterial(Request $request, JobWorkOrder $jobWorkOrder): JsonResponse
    {
        $this->authorize('jobwork-manage');
        $lines = $this->validateLines($request);
        $this->jobWork->consumeCustomerMaterial($jobWorkOrder, $lines);

        return response()->json(['message' => 'Customer material consumed.'], 201);
    }

    public function deliver(Request $request, JobWorkOrder $jobWorkOrder): JsonResponse
    {
        $this->authorize('jobwork-manage');
        $lines = $this->validateLines($request);
        $this->jobWork->deliverCustomerGoods($jobWorkOrder, $lines);

        return response()->json(['message' => 'Customer goods delivered.'], 201);
    }

    public function processingInvoice(Request $request): JsonResponse
    {
        $this->authorize('jobwork-manage');
        $companyId = $this->current->id();
        $data = $request->validate([
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('company_id', $companyId)],
            'job_work_order_id' => ['nullable', Rule::exists('job_work_orders', 'id')->where('company_id', $companyId)],
            'qty' => ['required', 'numeric', 'gt:0'],
            'rate' => ['required', 'numeric', 'min:0'],
            'date' => ['sometimes', 'date'],
        ]);

        return response()->json($this->jobWork->processingInvoice($data), 201);
    }

    public function reconciliation(Request $request): JsonResponse
    {
        $this->authorize('jobwork-view');
        $companyId = $this->current->id();
        $data = $request->validate([
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('company_id', $companyId)],
            'product_id' => ['required', Rule::exists('products', 'id')->where('company_id', $companyId)],
        ]);

        return response()->json(
            $this->jobWork->materialReconciliation((int) $data['customer_id'], (int) $data['product_id'])
        );
    }

    private function validateLines(Request $request): array
    {
        $companyId = $this->current->id();

        return $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.batch_id' => ['sometimes', 'integer', 'min:0'],
        ])['lines'];
    }
}
