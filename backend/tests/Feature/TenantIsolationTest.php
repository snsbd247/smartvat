<?php

use App\Modules\Organization\Models\Factory;
use App\Shared\Tenancy\CurrentCompany;
use Laravel\Sanctum\Sanctum;

/** Create a factory directly within a given company's tenant context. */
function factoryFor(App\Modules\Organization\Models\Company $company, string $code): Factory
{
    $prev = app(CurrentCompany::class);
    $prev->set($company);
    $f = Factory::create(['code' => $code, 'name' => "Factory $code"]);
    $prev->clear();

    return $f;
}

it('lists only the current company factories', function () {
    $acme = makeCompany('ACME');
    $beta = makeCompany('BETA');
    factoryFor($acme, 'A1');
    factoryFor($acme, 'A2');
    factoryFor($beta, 'B1');

    $user = makeUserInCompany($acme, 'admin');
    Sanctum::actingAs($user);

    $response = $this->withHeader('X-Company-Id', (string) $acme->id)
        ->getJson('/api/factories');

    $response->assertOk()->assertJsonCount(2, 'data');
    $codes = collect($response->json('data'))->pluck('code')->all();
    expect($codes)->toContain('A1', 'A2')->not->toContain('B1');
});

it('cannot read another company factory by id (404 via tenant scope)', function () {
    $acme = makeCompany('ACME');
    $beta = makeCompany('BETA');
    $betaFactory = factoryFor($beta, 'B1');

    $user = makeUserInCompany($acme, 'admin');
    Sanctum::actingAs($user);

    $this->withHeader('X-Company-Id', (string) $acme->id)
        ->getJson("/api/factories/{$betaFactory->id}")
        ->assertNotFound();
});

it('rejects a company the user does not belong to (403)', function () {
    $acme = makeCompany('ACME');
    $beta = makeCompany('BETA');

    $user = makeUserInCompany($acme, 'admin');
    Sanctum::actingAs($user);

    $this->withHeader('X-Company-Id', (string) $beta->id)
        ->getJson('/api/factories')
        ->assertForbidden();
});

it('stamps the current company on created factories', function () {
    $acme = makeCompany('ACME');
    $user = makeUserInCompany($acme, 'admin');
    Sanctum::actingAs($user);

    $this->withHeader('X-Company-Id', (string) $acme->id)
        ->postJson('/api/factories', ['code' => 'NEW', 'name' => 'New Factory'])
        ->assertCreated()
        ->assertJsonPath('data.company_id', $acme->id);
});
