<?php

use App\Shared\Security\Totp;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->company = makeCompany('ACME');
    setCompanyContext($this->company);
    $this->user = makeUserInCompany($this->company, 'admin');
    $this->user->forceFill(['email' => 'mfa@irlbd.com', 'password' => Hash::make('secret123'), 'status' => 'active'])->save();
});

it('generates and verifies a TOTP code', function () {
    $secret = Totp::generateSecret();
    $counter = (int) floor(time() / 30);
    $code = Totp::at($secret, $counter);

    expect(strlen($code))->toBe(6);
    expect(Totp::verify($secret, $code))->toBeTrue();
    expect(Totp::verify($secret, '000000'))->toBeFalse();
});

it('enables, confirms and enforces two-factor on login', function () {
    Sanctum::actingAs($this->user);

    $enable = $this->postJson('/api/auth/2fa/enable')->assertOk()->json();
    expect($enable['secret'])->not->toBeEmpty();
    expect($enable['otpauth_uri'])->toContain('otpauth://totp/');

    $secret = $this->user->fresh()->two_factor_secret;
    $code = Totp::at($secret, (int) floor(time() / 30));

    $this->postJson('/api/auth/2fa/confirm', ['code' => $code])->assertOk();
    expect($this->user->fresh()->two_factor_enabled)->toBeTrue();
});

it('challenges login when 2FA is enabled and accepts a valid code', function () {
    $secret = Totp::generateSecret();
    $this->user->forceFill(['two_factor_secret' => $secret, 'two_factor_enabled' => true])->save();

    // No code -> challenge, no token.
    $this->postJson('/api/auth/login', ['login' => 'mfa@irlbd.com', 'password' => 'secret123'])
        ->assertOk()->assertJsonPath('two_factor_required', true)->assertJsonMissing(['token' => true]);

    // Wrong code -> 422.
    $this->postJson('/api/auth/login', ['login' => 'mfa@irlbd.com', 'password' => 'secret123', 'code' => '000000'])
        ->assertStatus(422);

    // Correct code -> token issued.
    $code = Totp::at($this->user->fresh()->two_factor_secret, (int) floor(time() / 30));
    $this->postJson('/api/auth/login', ['login' => 'mfa@irlbd.com', 'password' => 'secret123', 'code' => $code])
        ->assertOk()->assertJsonStructure(['token']);
});

it('disables two-factor with the correct password', function () {
    $this->user->forceFill(['two_factor_secret' => Totp::generateSecret(), 'two_factor_enabled' => true])->save();
    Sanctum::actingAs($this->user);

    $this->postJson('/api/auth/2fa/disable', ['password' => 'wrong'])->assertStatus(422);
    $this->postJson('/api/auth/2fa/disable', ['password' => 'secret123'])->assertOk();
    expect($this->user->fresh()->two_factor_enabled)->toBeFalse();
});
