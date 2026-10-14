<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('logs in with email and returns a token and companies', function () {
    $company = makeCompany('ACME');
    $user = User::factory()->create([
        'email' => 'jane@example.test',
        'username' => 'jane',
        'password' => Hash::make('secret123'),
        'status' => 'active',
    ]);
    $company->users()->attach($user->id, ['is_default' => true]);

    $response = $this->postJson('/api/auth/login', [
        'login' => 'jane@example.test',
        'password' => 'secret123',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['token', 'user' => ['id', 'email'], 'companies'])
        ->assertJsonPath('companies.0.code', 'ACME');
});

it('logs in with username', function () {
    $user = User::factory()->create([
        'username' => 'johnny',
        'password' => Hash::make('secret123'),
        'status' => 'active',
    ]);

    $this->postJson('/api/auth/login', [
        'login' => 'johnny',
        'password' => 'secret123',
    ])->assertOk();
});

it('rejects wrong credentials', function () {
    User::factory()->create([
        'email' => 'jane@example.test',
        'password' => Hash::make('secret123'),
        'status' => 'active',
    ]);

    $this->postJson('/api/auth/login', [
        'login' => 'jane@example.test',
        'password' => 'wrong',
    ])->assertStatus(422);
});

it('rejects inactive accounts', function () {
    User::factory()->create([
        'email' => 'inactive@example.test',
        'password' => Hash::make('secret123'),
        'status' => 'inactive',
    ]);

    $this->postJson('/api/auth/login', [
        'login' => 'inactive@example.test',
        'password' => 'secret123',
    ])->assertStatus(422);
});
