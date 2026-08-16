<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use JsonException;
use RuntimeException;

class FirebaseAccessTokenProvider
{
    public function token(): string
    {
        $credentials = $this->credentials();
        $cacheKey = 'mobile_push:fcm_access_token:'.hash('sha256', $credentials['client_email']);
        $cachedToken = Cache::get($cacheKey);

        if (is_string($cachedToken) && $cachedToken !== '') {
            return $cachedToken;
        }

        $tokenUri = (string) ($credentials['token_uri'] ?? config('mobile_push.fcm.token_uri'));
        $assertion = $this->serviceAccountAssertion($credentials, $tokenUri);

        $response = Http::asForm()
            ->acceptJson()
            ->timeout(10)
            ->post($tokenUri, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                sprintf('Unable to obtain Firebase access token (HTTP %d).', $response->status()),
            );
        }

        $accessToken = $response->json('access_token');

        if (! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('Firebase access token response did not contain an access token.');
        }

        $expiresIn = max(60, (int) $response->json('expires_in', 3600));
        Cache::put($cacheKey, $accessToken, now()->addSeconds(max(60, $expiresIn - 120)));

        return $accessToken;
    }

    /**
     * @return array{client_email: string, private_key: string, token_uri?: string}
     */
    private function credentials(): array
    {
        $configuredPath = trim((string) config('mobile_push.fcm.credentials'));

        if ($configuredPath === '') {
            throw new RuntimeException('FIREBASE_CREDENTIALS must point to a Firebase service-account JSON file.');
        }

        $path = $this->absolutePath($configuredPath);

        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('Firebase service-account credentials file is not readable.');
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('Firebase service-account credentials file could not be read.');
        }

        try {
            $credentials = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Firebase service-account credentials contain invalid JSON.', 0, $exception);
        }

        if (! is_array($credentials)
            || ! is_string($credentials['client_email'] ?? null)
            || ($credentials['client_email'] ?? '') === ''
            || ! is_string($credentials['private_key'] ?? null)
            || ($credentials['private_key'] ?? '') === '') {
            throw new RuntimeException('Firebase service-account credentials are missing required fields.');
        }

        return $credentials;
    }

    /**
     * @param  array{client_email: string, private_key: string, token_uri?: string}  $credentials
     */
    private function serviceAccountAssertion(array $credentials, string $tokenUri): string
    {
        $issuedAt = time();
        $header = $this->base64UrlEncode(json_encode([
            'alg' => 'RS256',
            'typ' => 'JWT',
        ], JSON_THROW_ON_ERROR));
        $claims = $this->base64UrlEncode(json_encode([
            'iss' => $credentials['client_email'],
            'scope' => (string) config('mobile_push.fcm.scope'),
            'aud' => $tokenUri,
            'iat' => $issuedAt,
            'exp' => $issuedAt + 3600,
        ], JSON_THROW_ON_ERROR));
        $unsignedToken = $header.'.'.$claims;
        $privateKey = openssl_pkey_get_private($credentials['private_key']);

        if ($privateKey === false) {
            throw new RuntimeException('Firebase service-account private key is invalid.');
        }

        $signature = '';

        if (! openssl_sign($unsignedToken, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Firebase service-account assertion could not be signed.');
        }

        return $unsignedToken.'.'.$this->base64UrlEncode($signature);
    }

    private function absolutePath(string $path): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1) {
            return $path;
        }

        return base_path($path);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
