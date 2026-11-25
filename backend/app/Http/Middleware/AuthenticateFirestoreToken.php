<?php

namespace App\Http\Middleware;

use App\Models\FirestoreUser;
use App\Services\FirebaseIdTokenVerifier;
use App\Services\FirebaseVerifierUnavailable;
use App\Services\FirestoreStore;
use Closure;
use Illuminate\Http\Request;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateFirestoreToken
{
    public function handle(Request $request, Closure $next, ...$guards): Response
    {
        $bearer = $request->bearerToken();
        abort_unless($bearer, 401, 'A Firebase ID token is required.');

        try {
            $claims = app(FirebaseIdTokenVerifier::class)->verify($bearer);
        } catch (FirebaseVerifierUnavailable) {
            abort(503, 'Firebase token verification is temporarily unavailable.');
        } catch (Throwable) {
            abort(401, 'Unauthenticated.');
        }
        abort_unless($claims, 401, 'Unauthenticated.');

        $email = mb_strtolower(trim($claims['email']));
        $profileId = hash('sha256', $email);
        $profile = app(FirestoreStore::class)->get('users', $profileId);
        abort_unless($profile && mb_strtolower(trim((string) ($profile['email'] ?? ''))) === $email, 401, 'No SICKSAINT profile is linked to this Firebase account.');

        // Link legacy verified-email profiles once, without changing their role
        // or other data. All subsequent requests require this stable Firebase UID.
        if (!empty($profile['firebaseUid'])) {
            abort_unless(hash_equals((string) $profile['firebaseUid'], (string) $claims['sub']), 401, 'This profile is linked to a different Firebase account.');
        } else {
            $profile = app(FirestoreStore::class)->put('users', $profileId, [
                'firebaseUid' => $claims['sub'],
                'authProvider' => 'firebase',
                'updatedAt' => now()->toIso8601String(),
            ]);
        }

        // The ID token identifies the caller. Roles are loaded from the private
        // Firestore profile and never taken from custom/client claims.
        $request->setUserResolver(fn () => new FirestoreUser($profile, $claims));
        return $next($request);
    }
}
