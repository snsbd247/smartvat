<?php

use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->company = makeCompany('ACME');
    setCompanyContext($this->company);
    $this->headers = ['X-Company-Id' => (string) $this->company->id];
});

it('returns the roles-permissions matrix', function () {
    Sanctum::actingAs(makeUserInCompany($this->company, 'admin'));

    $res = $this->withHeaders($this->headers)->getJson('/api/security/roles-matrix')->assertOk();
    expect($res->json('roles'))->not->toBeEmpty();
    expect($res->json('permissions'))->toContain('sales.manage');
});

it('lets a super-admin edit a role and create a role', function () {
    Sanctum::actingAs(makeUserInCompany($this->company, 'super-admin'));

    $this->withHeaders($this->headers)->putJson('/api/security/roles/viewer/permissions', [
        'permissions' => ['sales.view', 'purchase.view'],
    ])->assertOk();

    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    $viewer = Role::findByName('viewer', 'web');
    expect($viewer->permissions->pluck('name')->sort()->values()->all())->toBe(['purchase.view', 'sales.view']);

    $this->withHeaders($this->headers)->postJson('/api/security/roles', ['name' => 'auditor'])->assertCreated();
    expect(Role::where('name', 'auditor')->exists())->toBeTrue();
});

it('refuses to edit the super-admin role', function () {
    Sanctum::actingAs(makeUserInCompany($this->company, 'super-admin'));
    $this->withHeaders($this->headers)->putJson('/api/security/roles/super-admin/permissions', ['permissions' => []])
        ->assertStatus(422);
});

it('denies role editing to a non super-admin', function () {
    Sanctum::actingAs(makeUserInCompany($this->company, 'admin'));
    $this->withHeaders($this->headers)->putJson('/api/security/roles/viewer/permissions', ['permissions' => []])
        ->assertStatus(403);
    $this->withHeaders($this->headers)->postJson('/api/security/roles', ['name' => 'x'])->assertStatus(403);
});
