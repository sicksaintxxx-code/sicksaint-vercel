<?php

namespace Tests\Support;

use App\Services\FirebaseIdTokenVerifier;

class FakeFirebaseIdTokenVerifier extends FirebaseIdTokenVerifier
{
    public array $tokens = [];

    public function verify(?string $token, bool $requireVerifiedEmail = true): ?array
    {
        $claims = $token ? ($this->tokens[$token] ?? null) : null;
        if (!$claims || ($requireVerifiedEmail && ($claims['email_verified'] ?? false) !== true)) return null;
        return $claims;
    }
}
