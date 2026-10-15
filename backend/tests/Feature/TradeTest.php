<?php

use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->company = makeCompany('ACME');
    setCompanyContext($this->company);
    Sanctum::actingAs(makeUserInCompany($this->company, 'admin'));
    $this->headers = ['X-Company-Id' => (string) $this->company->id];
});

it('creates and advances an LC', function () {
    $lc = $this->withHeaders($this->headers)->postJson('/api/trade/finance', [
        'kind' => 'lc', 'party' => 'China Supplier', 'bank' => 'ABC Bank',
        'ref_number' => 'LC-778', 'currency' => 'USD', 'amount' => 50000,
    ])->assertCreated()->json();

    expect($lc['tf_no'])->toStartWith('LC-');
    expect($lc['status'])->toBe('open');

    $this->withHeaders($this->headers)->patchJson("/api/trade/finance/{$lc['id']}/status", ['status' => 'settled'])
        ->assertOk()->assertJsonPath('status', 'settled');

    $this->withHeaders($this->headers)->getJson('/api/trade/finance')->assertOk()->assertJsonCount(1);
});

it('creates a shipment and tracks its status', function () {
    $s = $this->withHeaders($this->headers)->postJson('/api/trade/shipments', [
        'direction' => 'import', 'transport_ref' => 'MBL123', 'carrier' => 'Maersk', 'origin' => 'Shanghai', 'destination' => 'Chattogram',
    ])->assertCreated()->json();

    expect($s['shipment_no'])->toStartWith('SHP-');
    $this->withHeaders($this->headers)->patchJson("/api/trade/shipments/{$s['id']}/status", ['status' => 'in_transit'])
        ->assertOk()->assertJsonPath('status', 'in_transit');
});

it('creates an export EXP document', function () {
    $d = $this->withHeaders($this->headers)->postJson('/api/trade/export-documents', [
        'doc_type' => 'exp', 'party' => 'EU Buyer', 'reference' => 'INV-900',
    ])->assertCreated()->json();

    expect($d['doc_no'])->toStartWith('EXP-');
    $this->withHeaders($this->headers)->patchJson("/api/trade/export-documents/{$d['id']}/status", ['status' => 'issued'])
        ->assertOk()->assertJsonPath('status', 'issued');
});

it('denies trade management to a viewer', function () {
    Sanctum::actingAs(makeUserInCompany($this->company, 'viewer'));
    $this->withHeaders($this->headers)->postJson('/api/trade/finance', ['kind' => 'tt', 'amount' => 100])->assertStatus(403);
});
