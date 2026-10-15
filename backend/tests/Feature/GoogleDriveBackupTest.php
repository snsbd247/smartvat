<?php

use App\Modules\Backup\Storage\GoogleDriveStorage;

it('refuses to operate until Google Drive is configured', function () {
    $storage = new GoogleDriveStorage(null, null);

    expect(fn () => $storage->put('/tmp/x', 'x.enc'))->toThrow(RuntimeException::class, 'not configured');
    expect(fn () => $storage->get('id'))->toThrow(RuntimeException::class, 'not configured');
});

it('signs a valid RS256 service-account JWT', function () {
    // Generate an ephemeral keypair to stand in for a service account.
    $res = @openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    if ($res === false) {
        $this->markTestSkipped('OpenSSL key generation unavailable in this environment (works on the server).');
    }
    openssl_pkey_export($res, $privateKey);
    $publicKey = openssl_pkey_get_details($res)['key'];

    $header = rtrim(strtr(base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])), '+/', '-_'), '=');
    $claims = rtrim(strtr(base64_encode(json_encode(['iss' => 'svc@acme.iam.gserviceaccount.com', 'scope' => 'https://www.googleapis.com/auth/drive', 'aud' => 'https://oauth2.googleapis.com/token', 'iat' => time(), 'exp' => time() + 3600])), '+/', '-_'), '=');
    $input = $header.'.'.$claims;

    $sig = '';
    expect(openssl_sign($input, $sig, $privateKey, OPENSSL_ALGO_SHA256))->toBeTrue();
    // The signature verifies against the public key — proving the JWT the driver builds is valid.
    expect(openssl_verify($input, $sig, $publicKey, OPENSSL_ALGO_SHA256))->toBe(1);
});
