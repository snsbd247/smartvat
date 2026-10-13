<?php

use App\Modules\Approval\Models\Approval;
use App\Modules\Purchase\Models\PurchaseOrder;
use App\Modules\Purchase\Models\Supplier;
use App\Modules\Purchase\Services\PurchaseService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->company = makeCompany('ACME');
    setCompanyContext($this->company);
    mapInventoryAccounts($this->company);
    $this->product = makeProduct($this->company, 'FG1');
    $this->supplier = Supplier::create(['company_id' => $this->company->id, 'code' => 'SUP1', 'name' => 'Seller', 'status' => 'active']);
    $this->headers = ['X-Company-Id' => (string) $this->company->id];
});

function makeDraftPo(): PurchaseOrder
{
    return app(PurchaseService::class)->createOrder([
        'supplier_id' => test()->supplier->id,
        'lines' => [['product_id' => test()->product->id, 'qty' => 5, 'unit_price' => 20]],
    ]);
}

it('raises a pending approval when a purchase order is created', function () {
    $po = makeDraftPo();

    $approval = Approval::where('approvable_type', PurchaseOrder::class)->where('approvable_id', $po->id)->first();
    expect($approval)->not->toBeNull();
    expect($approval->status)->toBe('pending');
    expect($approval->typeLabel())->toBe('purchase_order');
    expect($po->fresh()->status)->toBe('draft');
});

it('lists pending approvals via the generic endpoint', function () {
    Sanctum::actingAs(makeUserInCompany($this->company, 'admin'));
    makeDraftPo();

    $res = $this->withHeaders($this->headers)->getJson('/api/approvals')->assertOk();

    expect($res->json())->toHaveCount(1);
    expect($res->json('0.type'))->toBe('purchase_order');
    expect($res->json('0.status'))->toBe('pending');
});

it('approves a document and syncs its status', function () {
    Sanctum::actingAs(makeUserInCompany($this->company, 'admin'));
    $po = makeDraftPo();
    $approval = Approval::first();

    $this->withHeaders($this->headers)
        ->postJson("/api/approvals/{$approval->id}/approve")->assertOk();

    expect($approval->fresh()->status)->toBe('approved');
    expect($po->fresh()->status)->toBe('approved');
});

it('rejects a document and cancels it', function () {
    Sanctum::actingAs(makeUserInCompany($this->company, 'admin'));
    $po = makeDraftPo();
    $approval = Approval::first();

    $this->withHeaders($this->headers)
        ->postJson("/api/approvals/{$approval->id}/reject", ['note' => 'Over budget'])->assertOk();

    expect($approval->fresh()->status)->toBe('rejected');
    expect($approval->fresh()->note)->toBe('Over budget');
    expect($po->fresh()->status)->toBe('cancelled');
});

it('rejects a second action on an already-actioned approval', function () {
    Sanctum::actingAs(makeUserInCompany($this->company, 'admin'));
    makeDraftPo();
    $approval = Approval::first();

    $this->withHeaders($this->headers)->postJson("/api/approvals/{$approval->id}/approve")->assertOk();
    $this->withHeaders($this->headers)->postJson("/api/approvals/{$approval->id}/reject")->assertStatus(422);
});

it('denies approval actions without the manage permission', function () {
    Sanctum::actingAs(makeUserInCompany($this->company, 'viewer'));
    makeDraftPo();
    $approval = Approval::first();

    $this->withHeaders($this->headers)->postJson("/api/approvals/{$approval->id}/approve")->assertStatus(403);
});
