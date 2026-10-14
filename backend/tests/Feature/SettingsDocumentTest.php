<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->company = makeCompany('ACME');
    setCompanyContext($this->company);
    $this->headers = ['X-Company-Id' => (string) $this->company->id];
});

it('stores and reads a settings group', function () {
    Sanctum::actingAs(makeUserInCompany($this->company, 'admin'));

    $this->withHeaders($this->headers)
        ->putJson('/api/settings/general', ['payload' => ['phone' => '017xx', 'bin' => '12345']])
        ->assertOk()
        ->assertJsonPath('payload.phone', '017xx');

    $this->withHeaders($this->headers)
        ->getJson('/api/settings/general')
        ->assertOk()
        ->assertJsonPath('payload.bin', '12345');
});

it('rejects an unknown settings group', function () {
    Sanctum::actingAs(makeUserInCompany($this->company, 'admin'));

    $this->withHeaders($this->headers)->getJson('/api/settings/bogus')->assertNotFound();
});

it('denies settings writes without configure-company', function () {
    Sanctum::actingAs(makeUserInCompany($this->company, 'viewer'));

    $this->withHeaders($this->headers)
        ->putJson('/api/settings/general', ['payload' => ['x' => 1]])
        ->assertStatus(403);
});

it('uploads, lists, downloads and deletes a document', function () {
    Storage::fake();
    Sanctum::actingAs(makeUserInCompany($this->company, 'admin'));

    $res = $this->withHeaders($this->headers)->postJson('/api/documents', [
        'file' => UploadedFile::fake()->create('license.pdf', 10, 'application/pdf'),
        'title' => 'Trade License',
        'category' => 'License',
    ])->assertCreated();

    $id = $res->json('id');

    $this->withHeaders($this->headers)->getJson('/api/documents')
        ->assertOk()
        ->assertJsonPath('0.title', 'Trade License')
        ->assertJsonPath('0.category', 'License');

    $this->withHeaders($this->headers)->get("/api/documents/{$id}")->assertOk();

    $this->withHeaders($this->headers)->deleteJson("/api/documents/{$id}")->assertOk();
    $this->withHeaders($this->headers)->getJson('/api/documents')->assertOk()->assertJsonCount(0);
});

it('changes the password after verifying the current one', function () {
    $user = makeUserInCompany($this->company, 'admin');
    $user->forceFill(['password' => bcrypt('old-password')])->save();
    Sanctum::actingAs($user);

    $this->withHeaders($this->headers)->postJson('/api/auth/change-password', [
        'current_password' => 'wrong',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertStatus(422);

    $this->withHeaders($this->headers)->postJson('/api/auth/change-password', [
        'current_password' => 'old-password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertOk();

    expect(\Illuminate\Support\Facades\Hash::check('new-password', $user->fresh()->password))->toBeTrue();
});
