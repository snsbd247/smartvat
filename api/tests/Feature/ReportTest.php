<?php

use App\Modules\Accounting\Services\PostingService;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->company = makeCompany('ACME');
    setCompanyContext($this->company);
    $this->cash = makeAccount($this->company, '1000', 'asset');
    $this->capital = makeAccount($this->company, '3000', 'equity');
    app(PostingService::class)->post(['date' => '2026-10-13', 'lines' => [
        ['account_id' => $this->cash->id, 'debit' => 1000],
        ['account_id' => $this->capital->id, 'credit' => 1000],
    ]]);
    $this->headers = ['X-Company-Id' => (string) $this->company->id];
});

it('lists available reports', function () {
    Sanctum::actingAs(makeUserInCompany($this->company, 'admin'));

    $this->withHeaders($this->headers)->getJson('/api/reports')
        ->assertOk()
        ->assertJsonFragment(['trial-balance']);
});

it('returns a trial balance as JSON', function () {
    Sanctum::actingAs(makeUserInCompany($this->company, 'admin'));

    $this->withHeaders($this->headers)->getJson('/api/reports/trial-balance?format=json')
        ->assertOk()
        ->assertJsonPath('title', 'Trial Balance');
});

it('exports a report as XLSX', function () {
    Sanctum::actingAs(makeUserInCompany($this->company, 'admin'));

    $res = $this->withHeaders($this->headers)->get('/api/reports/trial-balance?format=xlsx');
    $res->assertOk();
    expect($res->headers->get('content-type'))->toContain('spreadsheetml');
});

it('renders a report as PDF', function () {
    Sanctum::actingAs(makeUserInCompany($this->company, 'admin'));

    $res = $this->withHeaders($this->headers)->get('/api/reports/trial-balance?format=pdf');
    $res->assertOk();
    expect($res->headers->get('content-type'))->toContain('application/pdf');
});

it('forbids a user without reports.view', function () {
    Sanctum::actingAs(makeUserInCompany($this->company)); // no role, no permissions

    $this->withHeaders($this->headers)->getJson('/api/reports/trial-balance')->assertForbidden();
});
