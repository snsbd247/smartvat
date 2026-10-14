<?php

it('reports healthy status with database connectivity', function () {
    $response = $this->getJson('/api/health');

    $response
        ->assertOk()
        ->assertJson([
            'status' => 'ok',
            'checks' => [
                'database' => 'ok',
            ],
        ])
        ->assertJsonStructure([
            'status',
            'app',
            'version',
            'environment',
            'time',
            'checks' => ['database'],
        ]);
});

it('requires authentication for a protected endpoint', function () {
    $this->getJson('/api/companies')->assertUnauthorized();
});

it('returns 401 JSON (not a redirect) even without an Accept header', function () {
    // The ForceJsonResponse middleware guarantees API routes never 500 by
    // redirecting unauthenticated requests to a non-existent login route.
    $this->get('/api/companies')->assertUnauthorized();
});
