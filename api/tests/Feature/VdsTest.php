<?php

use App\Modules\Purchase\Models\Supplier;
use App\Modules\Vds\Models\VdsRule;
use App\Modules\Vds\Services\VdsService;

beforeEach(function () {
    $this->company = makeCompany('ACME');
    setCompanyContext($this->company);
    mapInventoryAccounts($this->company); // provides accounts_payable + vds_payable
    $this->cash = makeAccount($this->company, '1000', 'asset');
    $this->supplier = Supplier::create([
        'company_id' => $this->company->id, 'code' => 'SUP1', 'name' => 'Service Co', 'status' => 'active',
    ]);
    $this->vds = app(VdsService::class);
});

function approvedRule(App\Modules\Organization\Models\Company $c, string $category, float $rate): VdsRule
{
    return VdsRule::create([
        'company_id' => $c->id, 'service_category' => $category, 'rate' => $rate,
        'approval_status' => 'approved', 'verification_status' => 'verified',
    ]);
}

it('refuses to deduct without a verified rule', function () {
    expect(fn () => $this->vds->deduct([
        'supplier_id' => $this->supplier->id, 'service_category' => 'consultancy', 'base_amount' => 1000,
    ]))->toThrow(RuntimeException::class);
});

it('ignores an unapproved rule', function () {
    VdsRule::create([
        'company_id' => $this->company->id, 'service_category' => 'consultancy', 'rate' => 10,
        'approval_status' => 'draft', 'verification_status' => 'requires_review',
    ]);

    expect($this->vds->rateFor('consultancy'))->toBeNull();
});

it('deducts VDS and posts Dr AP / Cr VDS payable', function () {
    approvedRule($this->company, 'consultancy', 10);

    $d = $this->vds->deduct([
        'supplier_id' => $this->supplier->id, 'service_category' => 'consultancy', 'base_amount' => 1000,
    ]);

    expect((float) $d->vds_amount)->toBe(100.0);
    $recon = $this->vds->reconciliation();
    expect((float) $recon['deducted'])->toBe(100.0);
    expect((float) $recon['deposited'])->toBe(0.0);
    expect((float) $recon['payable'])->toBe(100.0);
});

it('deposits to treasury and reconciles deducted vs deposited', function () {
    approvedRule($this->company, 'consultancy', 10);
    $this->vds->deduct(['supplier_id' => $this->supplier->id, 'service_category' => 'consultancy', 'base_amount' => 1000]);

    $this->vds->depositToTreasury(['amount' => 100, 'paid_from_account_id' => $this->cash->id]);

    $recon = $this->vds->reconciliation();
    expect((float) $recon['deducted'])->toBe(100.0);
    expect((float) $recon['deposited'])->toBe(100.0);
    expect((float) $recon['payable'])->toBe(0.0);
});

it('issues a VDS certificate for a deduction', function () {
    approvedRule($this->company, 'consultancy', 10);
    $d = $this->vds->deduct(['supplier_id' => $this->supplier->id, 'service_category' => 'consultancy', 'base_amount' => 1000]);

    $cert = $this->vds->issueCertificate($d);
    expect($cert->certificate_no)->toStartWith('VDSC-');
});
