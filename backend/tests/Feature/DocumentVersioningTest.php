<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->company = makeCompany('ACME');
    setCompanyContext($this->company);
    Storage::fake();
    Sanctum::actingAs(makeUserInCompany($this->company, 'admin'));
    $this->headers = ['X-Company-Id' => (string) $this->company->id];
});

it('attaches a document to a record and filters by it', function () {
    $this->withHeaders($this->headers)->postJson('/api/documents', [
        'file' => UploadedFile::fake()->create('po.pdf', 5, 'application/pdf'),
        'attachable_type' => 'purchase_order', 'attachable_id' => 42,
    ])->assertCreated()->assertJsonPath('attachable_type', 'purchase_order')->assertJsonPath('version', 1);

    // Unrelated document.
    $this->withHeaders($this->headers)->postJson('/api/documents', [
        'file' => UploadedFile::fake()->create('other.pdf', 5, 'application/pdf'),
    ])->assertCreated();

    $this->withHeaders($this->headers)->getJson('/api/documents?attachable_type=purchase_order&attachable_id=42')
        ->assertOk()->assertJsonCount(1);
});

it('creates a new version that inherits the group and bumps the version', function () {
    $v1 = $this->withHeaders($this->headers)->postJson('/api/documents', [
        'file' => UploadedFile::fake()->create('contract.pdf', 5, 'application/pdf'),
        'title' => 'Contract', 'attachable_type' => 'customer', 'attachable_id' => 7,
    ])->assertCreated()->json();

    $v2 = $this->withHeaders($this->headers)->postJson('/api/documents', [
        'file' => UploadedFile::fake()->create('contract-v2.pdf', 6, 'application/pdf'),
        'title' => 'Contract (rev)', 'replaces' => $v1['id'],
    ])->assertCreated()->json();

    expect($v2['version'])->toBe(2);
    expect($v2['group_key'])->toBe($v1['group_key']);
    // Inherits the attachment from the previous version.
    expect($v2['attachable_type'])->toBe('customer');

    $this->withHeaders($this->headers)->getJson("/api/documents/{$v2['id']}/versions")
        ->assertOk()->assertJsonCount(2)->assertJsonPath('0.version', 2);
});
