<?php

namespace App\Modules\Vds\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Vds\Models\VdsDeduction;
use App\Modules\Vds\Models\VdsRule;
use App\Modules\Vds\Services\VdsService;
use App\Shared\Tenancy\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VdsController extends Controller
{
    public function __construct(
        private VdsService $vds,
        private CurrentCompany $current,
    ) {}

    public function rules(): JsonResponse
    {
        $this->authorize('vds-view');

        return response()->json(VdsRule::orderByDesc('id')->get());
    }

    public function deductions(): JsonResponse
    {
        $this->authorize('vds-view');

        return response()->json(VdsDeduction::orderByDesc('id')->limit(100)->get());
    }

    public function storeRule(Request $request): JsonResponse
    {
        $this->authorize('vds-manage');
        $data = $request->validate([
            'service_category' => ['required', 'string', 'max:64'],
            'rate' => ['required', 'numeric', 'min:0'],
            'effective_from' => ['sometimes', 'date'],
            'legal_ref' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json(VdsRule::create($data + [
            'company_id' => $this->current->id(),
            'approval_status' => 'draft',
            'verification_status' => 'requires_review',
        ]), 201);
    }

    public function approveRule(VdsRule $rule): JsonResponse
    {
        $this->authorize('vds-manage');
        $rule->update([
            'approval_status' => 'approved',
            'verification_status' => 'verified',
            'verified_by' => auth()->id(),
        ]);

        return response()->json($rule);
    }

    public function deduct(Request $request): JsonResponse
    {
        $this->authorize('vds-manage');
        $companyId = $this->current->id();
        $data = $request->validate([
            'supplier_id' => ['required', Rule::exists('suppliers', 'id')->where('company_id', $companyId)],
            'service_category' => ['required', 'string', 'max:64'],
            'base_amount' => ['required', 'numeric', 'gt:0'],
            'date' => ['sometimes', 'date'],
        ]);

        return response()->json($this->vds->deduct($data), 201);
    }

    public function issueCertificate(VdsDeduction $deduction): JsonResponse
    {
        $this->authorize('vds-manage');

        return response()->json($this->vds->issueCertificate($deduction), 201);
    }

    public function deposit(Request $request): JsonResponse
    {
        $this->authorize('vds-manage');
        $companyId = $this->current->id();
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'paid_from_account_id' => ['required', Rule::exists('chart_of_accounts', 'id')->where('company_id', $companyId)],
            'date' => ['sometimes', 'date'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json($this->vds->depositToTreasury($data), 201);
    }

    public function reconciliation(): JsonResponse
    {
        $this->authorize('vds-view');

        return response()->json($this->vds->reconciliation());
    }
}
