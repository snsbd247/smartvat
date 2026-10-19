<?php

use App\Modules\MasterData\Models\Product;
use App\Modules\Purchase\Models\Supplier;
use App\Modules\Sales\Models\Customer;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->company = makeCompany('ACME');
    setCompanyContext($this->company);
    Sanctum::actingAs(makeUserInCompany($this->company, 'admin'));
    $this->headers = ['X-Company-Id' => (string) $this->company->id];
});

it('imports products from CSV, skipping existing codes', function () {
    makeProduct($this->company, 'EXIST'); // pre-existing code

    $csv = "code,name,type,base_uom\nP100,Widget,finished,PCS\nEXIST,Dup,raw,KG\nP101,Gadget,raw,KG";
    $res = $this->withHeaders($this->headers)->postJson('/api/imports/products', ['csv' => $csv])
        ->assertCreated()->json();

    expect($res['created'])->toBe(2);
    expect($res['skipped'])->toBe(1);
    expect(Product::where('code', 'P100')->exists())->toBeTrue();
});

it('imports customers and suppliers', function () {
    $this->withHeaders($this->headers)->postJson('/api/imports/customers', ['csv' => "code,name,phone\nC1,Alpha Ltd,017\nC2,Beta Co,018"])
        ->assertCreated()->assertJsonPath('created', 2);
    expect(Customer::count())->toBe(2);

    $this->withHeaders($this->headers)->postJson('/api/imports/suppliers', ['csv' => "code,name\nS1,Supplier One"])
        ->assertCreated()->assertJsonPath('created', 1);
    expect(Supplier::count())->toBe(1);
});

it('reports row errors without failing the whole file', function () {
    $csv = "code,name\nG1,Good\n,Missing code\nG2,Good2";
    $res = $this->withHeaders($this->headers)->postJson('/api/imports/products', ['csv' => $csv])
        ->assertCreated()->json();

    expect($res['created'])->toBe(2);
    expect($res['errors'])->toHaveCount(1);
    expect($res['errors'][0]['row'])->toBe(3);
});

it('rejects a missing required column', function () {
    $this->withHeaders($this->headers)->postJson('/api/imports/products', ['csv' => "name\nNoCode"])
        ->assertStatus(422);
});

it('returns a template header and denies a viewer', function () {
    $this->withHeaders($this->headers)->getJson('/api/imports/products/template')
        ->assertOk()->assertJsonPath('header', fn ($h) => str_contains($h, 'code'));

    Sanctum::actingAs(makeUserInCompany($this->company, 'viewer'));
    $this->withHeaders($this->headers)->postJson('/api/imports/products', ['csv' => "code,name\nX,Y"])->assertStatus(403);
});
