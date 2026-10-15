<?php

namespace App\Modules\Backup\Storage;

use App\Modules\Backup\Contracts\BackupStorage;
use RuntimeException;

/**
 * Google Drive backup driver using a least-privilege service account and a
 * dedicated folder. Authentication is a self-contained OAuth2 service-account
 * JWT (RS256) exchanged for an access token — no google/apiclient dependency.
 *
 * Config:
 *   BACKUP_DRIVER=google
 *   GOOGLE_DRIVE_FOLDER_ID=<shared folder id>
 *   GOOGLE_DRIVE_CREDENTIALS=<path to service-account JSON, or the raw JSON>
 *
 * The service account needs the drive.file scope and the target folder must be
 * shared with the service account's email. Returns the Drive file id as the ref.
 */
class GoogleDriveStorage implements BackupStorage
{
    private const SCOPE = 'https://www.googleapis.com/auth/drive';

    private ?string $accessToken = null;

    public function __construct(private ?string $folderId, private ?string $credentials) {}

    public function put(string $localPath, string $name): string
    {
        $this->guard();
        $token = $this->accessToken();

        $boundary = 'bk'.bin2hex(random_bytes(8));
        $metadata = json_encode(['name' => $name, 'parents' => [$this->folderId]]);
        $media = file_get_contents($localPath);
        if ($media === false) {
            throw new RuntimeException("Cannot read backup artifact: {$localPath}");
        }

        $body = "--{$boundary}\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n{$metadata}\r\n"
            ."--{$boundary}\r\nContent-Type: application/octet-stream\r\n\r\n{$media}\r\n--{$boundary}--";

        $res = $this->http(
            'POST',
            'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id',
            $body,
            [
                'Authorization: Bearer '.$token,
                'Content-Type: multipart/related; boundary='.$boundary,
            ],
        );

        $id = json_decode($res, true)['id'] ?? null;
        if (! $id) {
            throw new RuntimeException('Google Drive upload failed: '.$res);
        }

        return $id;
    }

    public function get(string $ref): string
    {
        $this->guard();

        return $this->http(
            'GET',
            "https://www.googleapis.com/drive/v3/files/{$ref}?alt=media",
            null,
            ['Authorization: Bearer '.$this->accessToken()],
        );
    }

    public function delete(string $ref): void
    {
        $this->guard();
        $this->http(
            'DELETE',
            "https://www.googleapis.com/drive/v3/files/{$ref}",
            null,
            ['Authorization: Bearer '.$this->accessToken()],
        );
    }

    private function guard(): void
    {
        if (empty($this->folderId) || empty($this->credentials)) {
            throw new RuntimeException('Google Drive backup is not configured (folder id + service-account credentials required).');
        }
    }

    /** Read the service-account JSON (from a file path or raw string). */
    private function serviceAccount(): array
    {
        $raw = is_file($this->credentials) ? file_get_contents($this->credentials) : $this->credentials;
        $json = json_decode((string) $raw, true);
        if (! is_array($json) || empty($json['client_email']) || empty($json['private_key'])) {
            throw new RuntimeException('Invalid Google service-account credentials.');
        }

        return $json;
    }

    /** Exchange a signed JWT for an OAuth2 access token (cached per request). */
    private function accessToken(): string
    {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        $sa = $this->serviceAccount();
        $now = time();
        $tokenUri = $sa['token_uri'] ?? 'https://oauth2.googleapis.com/token';

        $header = $this->b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claims = $this->b64(json_encode([
            'iss' => $sa['client_email'],
            'scope' => self::SCOPE,
            'aud' => $tokenUri,
            'iat' => $now,
            'exp' => $now + 3600,
        ]));
        $signingInput = $header.'.'.$claims;

        $signature = '';
        if (! openssl_sign($signingInput, $signature, $sa['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Failed to sign the Google service-account JWT.');
        }
        $jwt = $signingInput.'.'.$this->b64($signature);

        $res = $this->http('POST', $tokenUri, http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]), ['Content-Type: application/x-www-form-urlencoded']);

        $token = json_decode($res, true)['access_token'] ?? null;
        if (! $token) {
            throw new RuntimeException('Google token exchange failed: '.$res);
        }

        return $this->accessToken = $token;
    }

    private function b64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function http(string $method, string $url, ?string $body, array $headers): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 120,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($res === false) {
            throw new RuntimeException("Google Drive request failed: {$err}");
        }
        if ($code >= 400) {
            throw new RuntimeException("Google Drive API error ({$code}): {$res}");
        }

        return (string) $res;
    }
}
