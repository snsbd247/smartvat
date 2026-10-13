<?php

use App\Modules\Vat\Models\VatNoteMapping;
use App\Modules\Vat\Models\VatReturnNote;
use App\Modules\Vat\Services\VatClassifier;
use App\Modules\Vat\Services\VatReportService;

beforeEach(function () {
    $this->company = makeCompany('ACME');
    setCompanyContext($this->company);
    $this->classifier = app(VatClassifier::class);
});

function note(string $code, string $title = 'Note'): VatReturnNote
{
    return VatReturnNote::create([
        'company_id' => app(App\Shared\Tenancy\CurrentCompany::class)->id(),
        'code' => $code, 'title' => $title, 'verification_status' => 'verified',
    ]);
}

function mapping(array $attrs): VatNoteMapping
{
    return VatNoteMapping::create(array_merge([
        'company_id' => app(App\Shared\Tenancy\CurrentCompany::class)->id(),
        'approval_status' => 'approved', 'verification_status' => 'verified', 'rule_version' => 'v1',
    ], $attrs));
}

it('classifies a line to a verified, approved note mapping', function () {
    $n = note('6.3', 'Local standard sale');
    mapping([
        'transaction_type_code' => 'local_sale', 'tax_treatment_code' => 'standard',
        'vat_rate_code' => 'STD15', 'vat_return_note_id' => $n->id, 'subform' => 'A',
    ]);

    $r = $this->classifier->classify([
        'transaction_type' => 'local_sale', 'tax_treatment' => 'standard', 'vat_rate' => 'STD15',
        'taxable_amount' => 1000, 'vat_amount' => 150,
    ]);

    expect($r['status'])->toBe('classified');
    expect($r['vat_return_note_id'])->toBe($n->id);
    expect($r['subform'])->toBe('A');
});

it('flags review_required and never invents a note when no mapping applies', function () {
    $r = $this->classifier->classify([
        'transaction_type' => 'export_sale', 'tax_treatment' => 'zero_rated', 'taxable_amount' => 500,
    ]);

    expect($r['status'])->toBe('review_required');
    expect($r['vat_return_note_id'])->toBeNull();
});

it('does not let VAT rate alone determine the note — treatment matters', function () {
    $standardNote = note('6.3', 'Standard');
    $exemptNote = note('6.5', 'Exempt');
    mapping(['transaction_type_code' => 'local_sale', 'tax_treatment_code' => 'standard', 'vat_rate_code' => 'STD15', 'vat_return_note_id' => $standardNote->id]);
    mapping(['transaction_type_code' => 'local_sale', 'tax_treatment_code' => 'exempt', 'vat_rate_code' => 'STD15', 'vat_return_note_id' => $exemptNote->id]);

    $a = $this->classifier->classify(['transaction_type' => 'local_sale', 'tax_treatment' => 'standard', 'vat_rate' => 'STD15'], persist: false);
    $b = $this->classifier->classify(['transaction_type' => 'local_sale', 'tax_treatment' => 'exempt', 'vat_rate' => 'STD15'], persist: false);

    expect($a['vat_return_note_id'])->toBe($standardNote->id);
    expect($b['vat_return_note_id'])->toBe($exemptNote->id);
});

it('ignores unapproved/unverified mappings', function () {
    $n = note('6.3');
    VatNoteMapping::create([
        'company_id' => $this->company->id,
        'transaction_type_code' => 'local_sale', 'tax_treatment_code' => 'standard',
        'vat_return_note_id' => $n->id, 'approval_status' => 'draft', 'verification_status' => 'requires_review',
    ]);

    $r = $this->classifier->classify(['transaction_type' => 'local_sale', 'tax_treatment' => 'standard'], persist: false);

    expect($r['status'])->toBe('review_required');
});

it('summarises classified amounts by note and lists unmapped', function () {
    $n = note('6.3');
    mapping(['transaction_type_code' => 'local_sale', 'tax_treatment_code' => 'standard', 'vat_return_note_id' => $n->id]);

    $this->classifier->classify(['transaction_type' => 'local_sale', 'tax_treatment' => 'standard', 'taxable_amount' => 1000, 'vat_amount' => 150, 'date' => '2026-10-01']);
    $this->classifier->classify(['transaction_type' => 'local_sale', 'tax_treatment' => 'standard', 'taxable_amount' => 500, 'vat_amount' => 75, 'date' => '2026-10-02']);
    $this->classifier->classify(['transaction_type' => 'export_sale', 'tax_treatment' => 'zero_rated', 'taxable_amount' => 900]); // unmapped

    $reports = app(VatReportService::class);
    $summary = $reports->noteSummary();
    expect($summary)->toHaveCount(1);
    expect((float) $summary[0]['taxable_amount'])->toBe(1500.0);
    expect((float) $summary[0]['vat_amount'])->toBe(225.0);

    expect($reports->unmapped())->toHaveCount(1);
});
