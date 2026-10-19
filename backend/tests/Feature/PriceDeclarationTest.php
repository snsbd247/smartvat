<?php

use App\Modules\Vat\Services\PriceDeclarationService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->company = makeCompany('ACME');
    setCompanyContext($this->company);
    $this->product = makeProduct($this->company, 'FG1');
    $this->headers = ['X-Company-Id' => (string) $this->company->id];
});

it('creates, approves and resolves the current declared price', function () {
    $svc = app(PriceDeclarationService::class);

    // Older approved declaration.
    $old = $svc->create(['product_id' => $this->product->id, 'declared_price' => 100, 'effective_from' => '2026-01-01']);
    $svc->approve($old);
    // Newer approved declaration supersedes it from its effective date.
    $new = $svc->create(['product_id' => $this->product->id, 'declared_price' => 130, 'effective_from' => '2026-06-01']);
    $svc->approve($new);
    // A draft (unapproved) must never be returned as current.
    $svc->create(['product_id' => $this->product->id, 'declared_price' => 999, 'effective_from' => '2026-01-01']);

    expect((float) $svc->currentFor($this->product->id, '2026-03-01')->declared_price)->toBe(100.0);
    expect((float) $svc->currentFor($this->product->id, '2026-07-01')->declared_price)->toBe(130.0);
    expect($svc->currentFor($this->product->id, '2025-12-01'))->toBeNull(); // before any effective date
});

it('exposes price declarations over the API', function () {
    Sanctum::actingAs(makeUserInCompany($this->company, 'admin'));

    $pd = $this->withHeaders($this->headers)->postJson('/api/vat/price-declarations', [
        'product_id' => $this->product->id, 'declared_price' => 250, 'purchase_value' => 200, 'effective_from' => '2026-05-01',
    ])->assertCreated()->json();
    expect($pd['decl_no'])->toStartWith('PD-');
    expect($pd['status'])->toBe('draft');

    $this->withHeaders($this->headers)->patchJson("/api/vat/price-declarations/{$pd['id']}/approve")
        ->assertOk()->assertJsonPath('status', 'approved');

    $this->withHeaders($this->headers)->getJson("/api/vat/price-declarations/current/{$this->product->id}")
        ->assertOk()->assertJsonPath('declared_price', fn ($v) => (float) $v === 250.0);

    $this->withHeaders($this->headers)->getJson('/api/vat/price-declarations')->assertOk()->assertJsonCount(1);
});

it('denies creating a declaration to a viewer', function () {
    Sanctum::actingAs(makeUserInCompany($this->company, 'viewer'));
    $this->withHeaders($this->headers)->postJson('/api/vat/price-declarations', [
        'product_id' => $this->product->id, 'declared_price' => 10,
    ])->assertStatus(403);
});
