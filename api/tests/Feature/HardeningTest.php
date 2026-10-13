<?php

it('adds security headers to responses', function () {
    $res = $this->get('/api/health');
    $res->assertOk();
    expect($res->headers->get('X-Content-Type-Options'))->toBe('nosniff');
    expect($res->headers->get('X-Frame-Options'))->toBe('DENY');
    expect($res->headers->get('Referrer-Policy'))->not->toBeNull();
});

it('rate-limits repeated login attempts', function () {
    $payload = ['login' => 'nobody@example.test', 'password' => 'wrong'];

    // 10 attempts are allowed (each fails validation 422), the 11th is throttled (429).
    for ($i = 0; $i < 10; $i++) {
        $this->postJson('/api/auth/login', $payload);
    }

    $this->postJson('/api/auth/login', $payload)->assertStatus(429);
});
