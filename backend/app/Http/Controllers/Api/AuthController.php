<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FirestoreUser;
use App\Services\FirestoreStore;
use App\Services\FirestoreConflictException;
use App\Services\FirebaseIdTokenVerifier;
use App\Services\FirebaseVerifierUnavailable;
use Illuminate\Http\Request;
use Throwable;

class AuthController extends Controller
{
    public function __construct(private FirestoreStore $store, private FirebaseIdTokenVerifier $firebaseTokens) {}
    private function emailId(string $email): string { return hash('sha256', mb_strtolower(trim($email))); }

    public function register(Request $request)
    {
        try { $claims = $this->firebaseTokens->verify($request->bearerToken(), false); }
        catch (FirebaseVerifierUnavailable) { abort(503, 'Firebase token verification is temporarily unavailable.'); }
        catch (Throwable) { abort(401, 'A valid Firebase ID token is required.'); }
        abort_unless($claims, 401, 'A valid Firebase ID token is required.');
        abort_unless(isset($claims['email']) && filter_var($claims['email'], FILTER_VALIDATE_EMAIL), 422, 'A Firebase email address is required.');

        $request->merge(['username' => mb_strtolower(trim((string) $request->input('username')))]);
        $data = $request->validate(['name' => ['required','string','max:120'], 'username' => ['required','alpha_dash:ascii','min:3','max:24']]);
        $email = mb_strtolower(trim($claims['email']));
        $emailId = $this->emailId($email);
        $existing = $this->store->get('users', $emailId);
        if ($existing) {
            abort_unless(($claims['email_verified'] ?? false) === true, 403, 'Verify your Firebase email before linking this existing SICKSAINT profile.');
            abort_if(!empty($existing['firebaseUid']) && !hash_equals((string) $existing['firebaseUid'], (string) $claims['sub']), 403, 'This profile is linked to a different Firebase account.');
            if (empty($existing['firebaseUid'])) $existing = $this->store->put('users', $emailId, ['firebaseUid' => $claims['sub'], 'authProvider' => 'firebase', 'updatedAt' => now()->toIso8601String()]);
            return response()->json(['user' => (new FirestoreUser($existing, $claims))->publicProfile()]);
        }

        $usernameId = hash('sha256', $data['username']);
        try { $this->store->create('usernameIndex', $usernameId, ['userId' => $emailId]); }
        catch (FirestoreConflictException) { abort(422, 'That username is already taken.'); }
        try {
            $verifiedAt = ($claims['email_verified'] ?? false) === true ? now()->toIso8601String() : null;
            $user = $this->store->create('users', $emailId, ['email' => $email, 'username' => $data['username'], 'name' => trim($data['name']), 'firebaseUid' => $claims['sub'], 'authProvider' => 'firebase', 'role' => 'READER', 'emailVerifiedAt' => $verifiedAt, 'createdAt' => now()->toIso8601String(), 'updatedAt' => now()->toIso8601String()]);
        } catch (FirestoreConflictException) { $this->store->delete('usernameIndex', $usernameId); abort(422, 'That email address is already registered.'); }
        return response()->json(['user' => (new FirestoreUser($user, $claims))->publicProfile()], 201);
    }

    public function login(Request $request) { return response()->json(['user' => $request->user()->publicProfile()]); }
    public function logout(Request $request) { return response()->json(['success' => true]); }
    public function me(Request $request) { return response()->json(['user' => $request->user()->publicProfile()]); }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        if ($request->exists('username')) $request->merge(['username' => mb_strtolower(trim((string) $request->input('username')))]);
        $data = $request->validate(['name' => ['sometimes','required','string','max:120'], 'username' => ['sometimes','required','alpha_dash:ascii','min:3','max:24'], 'bio' => ['sometimes','nullable','string','max:1000'], 'avatarUrl' => ['sometimes','nullable','url:https','max:2048'], 'title' => ['sometimes','nullable','string','max:160'], 'location' => ['sometimes','nullable','string','max:160'], 'website' => ['sometimes','nullable','url:https','max:2048'], 'twitter' => ['sometimes','nullable','string','max:80'], 'linkedin' => ['sometimes','nullable','url:https','max:2048']]);
        $newUsernameIndex = null;
        if (isset($data['username']) && $data['username'] !== $user->username) {
            $newUsernameIndex = hash('sha256', $data['username']); $indexed = $this->store->get('usernameIndex', $newUsernameIndex);
            abort_if($indexed && $indexed['userId'] !== $user->id, 422, 'That username is already taken.');
            if (!$indexed) try { $this->store->create('usernameIndex', $newUsernameIndex, ['userId' => $user->id]); } catch (FirestoreConflictException) { abort(422, 'That username is already taken.'); }
        }
        $map = ['avatarUrl' => 'avatarUrl']; $updates = [];
        foreach ($data as $key => $value) $updates[$map[$key] ?? $key] = is_string($value) ? trim($value) : $value;
        $updates['updatedAt'] = now()->toIso8601String();
        $saved = $this->store->put('users', $user->id, $updates);
        if ($newUsernameIndex) $this->store->delete('usernameIndex', hash('sha256', $user->username));
        return response()->json(['user' => (new FirestoreUser($saved))->publicProfile()]);
    }

    public function forgotPassword(Request $request) { return response()->json(['error' => 'Use Firebase Authentication password recovery.'], 410); }
    public function resetPassword(Request $request) { return response()->json(['error' => 'Use Firebase Authentication password recovery.'], 410); }

    public function sendVerification(Request $request)
    {
        return response()->json(['error' => 'Use Firebase Authentication email verification.'], 410);
    }

    public function verifyEmail(string $id, string $hash)
    {
        return response()->json(['error' => 'Use Firebase Authentication email verification.'], 410);
    }
}
