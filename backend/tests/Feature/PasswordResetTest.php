<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Password;

beforeEach(function () {
    $this->company = makeCompany('ACME');
    setCompanyContext($this->company);
    $this->user = makeUserInCompany($this->company, 'admin');
    $this->user->forceFill(['email' => 'reset@irlbd.com', 'password' => Hash::make('old-password')])->save();
});

it('sends a reset link to a known email and stays neutral for unknown', function () {
    Notification::fake();

    $this->postJson('/api/auth/forgot-password', ['email' => 'reset@irlbd.com'])
        ->assertOk()->assertJsonPath('message', 'If that email is registered, a reset link has been sent.');
    Notification::assertSentTo($this->user, ResetPassword::class);

    // Unknown email: same neutral response, no notification.
    $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@nowhere.com'])->assertOk();
});

it('resets the password with a valid token and revokes tokens', function () {
    $token = Password::broker()->createToken($this->user);

    $this->postJson('/api/auth/reset-password', [
        'token' => $token,
        'email' => 'reset@irlbd.com',
        'password' => 'brand-new-pass',
        'password_confirmation' => 'brand-new-pass',
    ])->assertOk();

    expect(Hash::check('brand-new-pass', $this->user->fresh()->password))->toBeTrue();
});

it('rejects a reset with a bad token', function () {
    $this->postJson('/api/auth/reset-password', [
        'token' => 'invalid-token',
        'email' => 'reset@irlbd.com',
        'password' => 'brand-new-pass',
        'password_confirmation' => 'brand-new-pass',
    ])->assertStatus(422);
});
