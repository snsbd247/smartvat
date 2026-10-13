<?php

namespace App\Modules\Vat\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Vat\Models\VatNoteMapping;
use App\Modules\Vat\Models\VatRate;
use App\Modules\Vat\Models\VatReturnNote;
use App\Modules\Vat\Models\VatTaxTreatment;
use App\Modules\Vat\Models\VatTransactionType;
use App\Modules\Vat\Services\VatClassifier;
use App\Modules\Vat\Services\VatReportService;
use App\Shared\Tenancy\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VatController extends Controller
{
    public function __construct(
        private VatClassifier $classifier,
        private VatReportService $reports,
        private CurrentCompany $current,
    ) {}

    private function cid(): int
    {
        return $this->current->id();
    }

    public function storeRate(Request $request): JsonResponse
    {
        $this->authorize('vat-manage');
        $data = $request->validate([
            'code' => ['required', 'string', 'max:24', Rule::unique('vat_rates', 'code')->where('company_id', $this->cid())],
            'rate' => ['required', 'numeric', 'min:0'],
            'effective_from' => ['sometimes', 'date'],
        ]);

        return response()->json(VatRate::create($data + ['company_id' => $this->cid()]), 201);
    }

    public function storeType(Request $request): JsonResponse
    {
        $this->authorize('vat-manage');
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', Rule::unique('vat_transaction_types', 'code')->where('company_id', $this->cid())],
            'name' => ['required', 'string', 'max:255'],
        ]);

        return response()->json(VatTransactionType::create($data + ['company_id' => $this->cid()]), 201);
    }

    public function storeTreatment(Request $request): JsonResponse
    {
        $this->authorize('vat-manage');
        $data = $request->validate([
            'code' => ['required', 'string', 'max:32', Rule::unique('vat_tax_treatments', 'code')->where('company_id', $this->cid())],
            'name' => ['required', 'string', 'max:255'],
        ]);

        return response()->json(VatTaxTreatment::create($data + ['company_id' => $this->cid()]), 201);
    }

    public function storeNote(Request $request): JsonResponse
    {
        $this->authorize('vat-manage');
        $data = $request->validate([
            'code' => ['required', 'string', 'max:24', Rule::unique('vat_return_notes', 'code')->where('company_id', $this->cid())],
            'title' => ['required', 'string', 'max:255'],
            'subform' => ['nullable', 'string', 'max:255'],
            'effective_from' => ['sometimes', 'date'],
            'legal_ref' => ['nullable', 'string', 'max:255'],
            'verification_status' => ['sometimes', Rule::in(['requires_review', 'verified'])],
        ]);

        return response()->json(VatReturnNote::create($data + ['company_id' => $this->cid()]), 201);
    }

    public function storeMapping(Request $request): JsonResponse
    {
        $this->authorize('vat-manage');
        $data = $request->validate([
            'transaction_type_code' => ['required', 'string', 'max:32'],
            'tax_treatment_code' => ['required', 'string', 'max:32'],
            'vat_rate_code' => ['nullable', 'string', 'max:24'],
            'category_code' => ['nullable', 'string', 'max:64'],
            'vat_return_note_id' => ['required', Rule::exists('vat_return_notes', 'id')->where('company_id', $this->cid())],
            'subform' => ['nullable', 'string', 'max:255'],
            'effective_from' => ['sometimes', 'date'],
            'rule_version' => ['sometimes', 'string', 'max:24'],
        ]);

        // Mappings start unapproved/unverified — they cannot classify until approved.
        return response()->json(VatNoteMapping::create($data + [
            'company_id' => $this->cid(),
            'approval_status' => 'draft',
            'verification_status' => 'requires_review',
        ]), 201);
    }

    public function approveMapping(VatNoteMapping $mapping): JsonResponse
    {
        $this->authorize('vat-manage');
        // Approval = a practitioner verified this rule for production use.
        $mapping->update([
            'approval_status' => 'approved',
            'verification_status' => 'verified',
            'verified_by' => auth()->id(),
        ]);

        return response()->json($mapping);
    }

    public function classify(Request $request): JsonResponse
    {
        $this->authorize('vat-manage');
        $data = $request->validate([
            'transaction_type' => ['required', 'string'],
            'tax_treatment' => ['required', 'string'],
            'vat_rate' => ['nullable', 'string'],
            'category' => ['nullable', 'string'],
            'date' => ['sometimes', 'date'],
            'taxable_amount' => ['sometimes', 'numeric'],
            'vat_amount' => ['sometimes', 'numeric'],
            'source_type' => ['nullable', 'string'],
            'source_id' => ['nullable', 'integer'],
            'line_ref' => ['nullable', 'string'],
        ]);

        return response()->json($this->classifier->classify($data));
    }

    public function noteSummary(Request $request): JsonResponse
    {
        $this->authorize('vat-view');

        return response()->json([
            'rows' => $this->reports->noteSummary($request->query('from'), $request->query('to')),
        ]);
    }

    public function unmapped(): JsonResponse
    {
        $this->authorize('vat-view');

        return response()->json(['rows' => $this->reports->unmapped()]);
    }

    public function register(Request $request): JsonResponse
    {
        $this->authorize('vat-view');
        $direction = $request->query('direction', 'output');

        return response()->json(['rows' => $this->reports->register($direction)]);
    }
}
