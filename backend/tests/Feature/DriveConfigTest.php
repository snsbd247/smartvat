<?php

use App\Modules\Settings\Models\SystemSetting;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->company = makeCompany('ACME');
    setCompanyContext($this->company);
    $this->headers = ['X-Company-Id' => (string) $this->company->id];
});

$fakeCreds = fn () => json_encode([
    'type' => 'service_account',
    'client_email' => 'svc@acme.iam.gserviceaccount.com',
    'private_key' => "-----BEGIN PRIVATE KEY-----\nMII...\n-----END PRIVATE KEY-----\n",
    'token_uri' => 'https://oauth2.googleapis.com/token',
]);

it('saves drive config and never returns the raw credentials', function () use ($fakeCreds) {
    Sanctum::actingAs(makeUserInCompany($this->company, 'admin'));

    $this->withHeaders($this->headers)->putJson('/api/backup/drive-config', [
        'enabled' => true,
        'folder_id' => 'FOLDER123',
        'credentials_json' => $fakeCreds(),
    ])->assertOk();

    // Stored encrypted; the config endpoint reports presence, not the secret.
    $res = $this->withHeaders($this->headers)->getJson('/api/backup/drive-config')->assertOk();
    expect($res->json('enabled'))->toBeTrue();
    expect($res->json('folder_id'))->toBe('FOLDER123');
    expect($res->json('credentials_set'))->toBeTrue();
    expect($res->json())->not->toHaveKey('credentials_json');

    // The secret is retrievable only server-side (for the backup driver).
    expect(SystemSetting::get('backup.drive')['credentials_json'])->toContain('client_email');
});

it('rejects enabling drive without folder + credentials', function () {
    Sanctum::actingAs(makeUserInCompany($this->company, 'admin'));

    $this->withHeaders($this->headers)->putJson('/api/backup/drive-config', ['enabled' => true])
        ->assertStatus(422);
});

it('rejects invalid service-account JSON', function () {
    Sanctum::actingAs(makeUserInCompany($this->company, 'admin'));

    $this->withHeaders($this->headers)->putJson('/api/backup/drive-config', [
        'enabled' => false, 'credentials_json' => '{"not":"valid"}',
    ])->assertStatus(422);
});

it('keeps existing credentials when omitted on update', function () use ($fakeCreds) {
    Sanctum::actingAs(makeUserInCompany($this->company, 'admin'));
    SystemSetting::put('backup.drive', ['enabled' => true, 'folder_id' => 'F1', 'credentials_json' => $fakeCreds()]);

    $this->withHeaders($this->headers)->putJson('/api/backup/drive-config', [
        'enabled' => false, 'folder_id' => 'F1',
    ])->assertOk();

    expect(SystemSetting::get('backup.drive')['credentials_json'])->toContain('client_email');
});

it('denies drive config to a viewer', function () {
    Sanctum::actingAs(makeUserInCompany($this->company, 'viewer'));
    $this->withHeaders($this->headers)->getJson('/api/backup/drive-config')->assertStatus(403);
});
