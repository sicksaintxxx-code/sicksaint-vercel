<?php

namespace App\Services;

use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/** Verifies Firebase Auth ID-token signatures and standard claims against Google's rotating JWK set. */
class FirebaseIdTokenVerifier
{
    private const JWK_URL = 'https://www.googleapis.com/service_accounts/v1/jwk/securetoken@system.gserviceaccount.com';

    /** @return array<string, mixed>|null */
    public function verify(?string $token, bool $requireVerifiedEmail = true): ?array
    {
        if (!$token || strlen($token) < 100 || strlen($token) > 8192) return null;
        $projectId = config('services.firebase.project_id');
        if (!is_string($projectId) || $projectId === '') throw new FirebaseVerifierUnavailable('Firebase project is not configured.');

        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;

        try {
            $header = json_decode(JWT::urlsafeB64Decode($parts[0]), true, flags: JSON_THROW_ON_ERROR);
            if (!is_array($header) || ($header['alg'] ?? null) !== 'RS256' || !is_string($header['kid'] ?? null)) return null;
        } catch (Throwable) {
            return null;
        }

        try {
            $keySet = Cache::remember('firebase.id_token.jwks.'.$projectId, now()->addMinutes(50), function (): array {
                $response = Http::acceptJson()->timeout(8)->connectTimeout(3)->get(self::JWK_URL);
                $response->throw();
                $body = $response->json();
                if (!is_array($body) || !is_array($body['keys'] ?? null) || $body['keys'] === []) {
                    throw new FirebaseVerifierUnavailable('Firebase signing key response was invalid.');
                }
                return $body;
            });
        } catch (Throwable $error) {
            throw new FirebaseVerifierUnavailable('Firebase signing keys could not be retrieved.', 0, $error);
        }

        try {
            $keys = JWK::parseKeySet($keySet, 'RS256');
            $claims = JWT::decode($token, $keys);
        } catch (SignatureInvalidException|ExpiredException|BeforeValidException) {
            return null;
        } catch (Throwable) {
            // Malformed claims, unknown key IDs and unsupported JWTs are
            // authentication failures, not signing-key service outages.
            return null;
        }

        if (!is_object($claims)) return null;
        $claims = (array) $claims;
        if (($claims['aud'] ?? null) !== $projectId || ($claims['iss'] ?? null) !== 'https://securetoken.google.com/'.$projectId) return null;
        if (!isset($claims['sub'], $claims['email'], $claims['email_verified'], $claims['iat'], $claims['exp'], $claims['auth_time'])) return null;
        if (!is_string($claims['sub']) || $claims['sub'] === '' || strlen($claims['sub']) > 128) return null;
        if (!is_string($claims['email']) || !filter_var($claims['email'], FILTER_VALIDATE_EMAIL)) return null;
        if (!is_bool($claims['email_verified']) || ($requireVerifiedEmail && !$claims['email_verified'])) return null;
        $now = time();
        if (!is_numeric($claims['iat']) || (int) $claims['iat'] > $now + 60) return null;
        if (!is_numeric($claims['exp']) || (int) $claims['exp'] <= $now) return null;
        if (!is_numeric($claims['auth_time']) || (int) $claims['auth_time'] > $now + 60) return null;

        return $claims;
    }
}
