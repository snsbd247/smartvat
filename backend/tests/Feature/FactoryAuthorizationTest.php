<?php

use Laravel\Sanctum\Sanctum;

it('forbids a viewer from creating a factory', function () {
    $acme = makeCompany('ACME');
    $viewer = makeUserInCompany($acme, 'viewer');
    Sanctum::actingAs($viewer);

    $this->withHeader('X-Company-Id', (string) $acme->id)
        ->postJson('/api/factories', ['code' => 'X1', 'name' => 'X'])
        ->assertForbidden();
});

it('allows a viewer to list factories', function () {
    $acme = makeCompany('ACME');
    $viewer = makeUserInCompany($acme, 'viewer');
    Sanctum::actingAs($viewer);

    $this->withHeader('X-Company-Id', (string) $acme->id)
        ->getJson('/api/factories')
        ->assertOk();
});

it('allows an admin to create a factory', function () {
    $acme = makeCompany('ACME');
    $admin = makeUserInCompany($acme, 'admin');
    Sanctum::actingAs($admin);

    $this->withHeader('X-Company-Id', (string) $acme->id)
        ->postJson('/api/factories', ['code' => 'OK', 'name' => 'Okay'])
        ->assertCreated();
});

it('enforces per-company unique factory code but allows the same code across companies', function () {
    $acme = makeCompany('ACME');
    $beta = makeCompany('BETA');
    $acmeAdmin = makeUserInCompany($acme, 'admin');
    $betaAdmin = makeUserInCompany($beta, 'admin');

    Sanctum::actingAs($acmeAdmin);
    $this->withHeader('X-Company-Id', (string) $acme->id)
        ->postJson('/api/factories', ['code' => 'DUP', 'name' => 'First'])
        ->assertCreated();

    // Same code again in ACME → rejected.
    $this->withHeader('X-Company-Id', (string) $acme->id)
        ->postJson('/api/factories', ['code' => 'DUP', 'name' => 'Second'])
        ->assertStatus(422);

    // Same code in BETA → allowed (separate tenant).
    Sanctum::actingAs($betaAdmin);
    $this->withHeader('X-Company-Id', (string) $beta->id)
        ->postJson('/api/factories', ['code' => 'DUP', 'name' => 'Beta One'])
        ->assertCreated();
});
