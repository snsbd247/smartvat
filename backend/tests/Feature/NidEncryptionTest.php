<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

it('encrypts NID at rest and never stores plaintext', function () {
    $user = User::factory()->create();
    $user->nid = '1990123456789';
    $user->save();

    $raw = DB::table('users')->where('id', $user->id)->first();

    // Stored column must not equal the plaintext.
    expect($raw->nid_encrypted)->not->toBe('1990123456789');
    expect($raw->nid_encrypted)->not->toBeNull();

    // Decryption via the model returns the original value.
    expect($user->fresh()->nid)->toBe('1990123456789');
});

it('finds a user by NID via the blind index without decrypting', function () {
    $user = User::factory()->create();
    $user->nid = '1990123456789';
    $user->save();

    $found = User::query()->whereNid('1990123456789')->first();

    expect($found)->not->toBeNull();
    expect($found->id)->toBe($user->id);
});

it('hides NID columns from serialization', function () {
    $user = User::factory()->create();
    $user->nid = '1990123456789';
    $user->save();

    $array = $user->fresh()->toArray();

    expect($array)->not->toHaveKey('nid_encrypted');
    expect($array)->not->toHaveKey('nid_index');
});
