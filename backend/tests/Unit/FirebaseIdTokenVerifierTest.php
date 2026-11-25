<?php

namespace Tests\Unit;

use App\Services\FirebaseIdTokenVerifier;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class FirebaseIdTokenVerifierTest extends TestCase
{
    private \OpenSSLAsymmetricKey $privateKey;
    private string $keyId = 'test-key-1';
    private FirebaseIdTokenVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.firebase.project_id' => 'sicksaint-test']);
        Cache::forget('firebase.id_token.jwks.sicksaint-test');
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        if ($key === false) {
            // PHP/OpenSSL on some Windows distributions cannot generate keys;
            // Node's standard crypto module generates an ephemeral test key.
            // Signing and verification still use the real OpenSSL/JWK path.
            $script = "const {generateKeyPairSync}=require('node:crypto'); const p=generateKeyPairSync('rsa',{modulusLength:2048}); console.log(JSON.stringify({privateKey:p.privateKey.export({type:'pkcs8',format:'pem'})}));";
            $process = new Process(['node', '-e', $script]);
            $process->run();
            $pair = json_decode($process->getOutput(), true);
            $key = is_array($pair) && isset($pair['privateKey']) ? openssl_pkey_get_private($pair['privateKey']) : false;
        }
        $this->assertNotFalse($key, 'Could not create/import an ephemeral RSA test key through PHP or Node crypto.');
        $details = openssl_pkey_get_details($key);
        $this->assertIsArray($details);
        $this->privateKey = $key;
        $jwk = [
            'kty' => 'RSA',
            'alg' => 'RS256',
            'use' => 'sig',
            'kid' => $this->keyId,
            'n' => $this->base64Url($details['rsa']['n']),
            'e' => $this->base64Url($details['rsa']['e']),
        ];
        Http::fake(['https://www.googleapis.com/service_accounts/v1/jwk/*' => Http::response(['keys' => [$jwk]], 200)]);
        Http::preventStrayRequests();
        $this->verifier = new FirebaseIdTokenVerifier();
    }

    public function test_valid_firebase_token_signature_project_and_email_claims_are_accepted(): void
    {
        $claims = $this->baseClaims();
        $verified = $this->verifier->verify($this->token($claims));
        $this->assertIsArray($verified);
        $this->assertSame('firebase-user-1', $verified['sub']);
        $this->assertSame('ADMIN', $verified['role']); // The authorization middleware must ignore this untrusted claim.
    }

    public function test_wrong_project_audience_is_rejected(): void
    {
        $claims = $this->baseClaims();
        $claims['aud'] = 'another-project';
        $this->assertNull($this->verifier->verify($this->token($claims)));
    }

    public function test_invalid_rsa_signature_is_rejected(): void
    {
        $parts = explode('.', $this->token($this->baseClaims()));
        $signature = base64_decode(strtr($parts[2], '-_', '+/'));
        $signature[0] = chr(ord($signature[0]) ^ 1);
        $parts[2] = $this->base64Url($signature);
        $this->assertNull($this->verifier->verify(implode('.', $parts)));
    }

    public function test_unverified_email_is_rejected_for_regular_api_auth_but_can_be_used_for_registration(): void
    {
        $claims = $this->baseClaims();
        $claims['email_verified'] = false;
        $token = $this->token($claims);
        $this->assertNull($this->verifier->verify($token));
        $this->assertSame(false, $this->verifier->verify($token, false)['email_verified']);
    }

    public function test_expired_token_is_rejected(): void
    {
        $claims = $this->baseClaims();
        $claims['exp'] = time() - 10;
        $this->assertNull($this->verifier->verify($this->token($claims)));
    }

    private function baseClaims(): array
    {
        $now = time();
        return [
            'iss' => 'https://securetoken.google.com/sicksaint-test',
            'aud' => 'sicksaint-test',
            'sub' => 'firebase-user-1',
            'email' => 'reader@example.test',
            'email_verified' => true,
            'iat' => $now - 60,
            'exp' => $now + 3600,
            'auth_time' => $now - 60,
            'role' => 'ADMIN',
        ];
    }

    private function token(array $claims): string
    {
        return JWT::encode($claims, $this->privateKey, 'RS256', $this->keyId);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
