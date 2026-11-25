<?php

namespace Tests\Feature;

use App\Services\FirestoreStore;
use Illuminate\Support\Str;
use Tests\Support\FakeFirebaseIdTokenVerifier;
use Tests\Support\InMemoryFirestoreStore;
use Tests\TestCase;

class AuthAndAuthorizationTest extends TestCase
{
    private InMemoryFirestoreStore $store;
    private FakeFirebaseIdTokenVerifier $firebaseTokens;
    protected function setUp(): void { parent::setUp(); $this->store = new InMemoryFirestoreStore(); $this->firebaseTokens = new FakeFirebaseIdTokenVerifier(); $this->app->instance(FirestoreStore::class, $this->store); $this->app->instance(\App\Services\FirebaseIdTokenVerifier::class, $this->firebaseTokens); }

    private function account(string $role): array
    {
        $email = Str::lower(Str::random(8)).'@example.test'; $id = hash('sha256', $email);
        return $this->store->create('users', $id, ['name' => ucfirst(strtolower($role)), 'username' => strtolower($role).'_'.Str::lower(Str::random(5)), 'email' => $email, 'passwordHash' => bcrypt('Passphrase1234'), 'role' => $role, 'emailVerifiedAt' => now()->toIso8601String()]);
    }

    private function actingAsRecord(array $user): void
    {
        $plain = Str::random(64);
        $this->firebaseTokens->tokens[$plain] = ['sub' => 'firebase-uid-'.$user['id'], 'email' => $user['email'], 'email_verified' => true, 'iat' => time() - 60, 'exp' => time() + 3600, 'auth_time' => time() - 60, 'role' => 'ADMIN'];
        $this->withToken($plain);
    }

    public function test_firebase_authenticated_registration_creates_reader_and_login_returns_server_profile(): void
    {
        $plain = Str::random(64);
        $this->firebaseTokens->tokens[$plain] = ['sub' => 'firebase-reader-uid', 'email' => 'reader@example.test', 'email_verified' => true, 'iat' => time() - 60, 'exp' => time() + 3600, 'auth_time' => time() - 60];
        $this->withToken($plain)->postJson('/api/auth/register', ['name' => 'New Reader', 'email' => 'evil@example.test', 'username' => 'new_reader', 'role' => 'ADMIN'])
            ->assertCreated()->assertJsonPath('user.role', 'READER')->assertJsonPath('user.email', 'reader@example.test');
        $this->withToken($plain)->postJson('/api/auth/login')->assertOk()->assertJsonPath('user.role', 'READER');
    }

    public function test_verified_firebase_identity_links_existing_profile_without_changing_role(): void
    {
        $existing = $this->account('ADMIN');
        $plain = Str::random(64);
        $this->firebaseTokens->tokens[$plain] = ['sub' => 'linked-uid', 'email' => $existing['email'], 'email_verified' => true, 'iat' => time() - 60, 'exp' => time() + 3600, 'auth_time' => time() - 60];
        $this->withToken($plain)->getJson('/api/auth/me')->assertOk()->assertJsonPath('user.role', 'ADMIN');
        $linked = $this->store->get('users', $existing['id']);
        $this->assertSame('linked-uid', $linked['firebaseUid']);
        $this->assertSame('ADMIN', $linked['role']);

        $other = Str::random(64);
        $this->firebaseTokens->tokens[$other] = ['sub' => 'different-uid', 'email' => $existing['email'], 'email_verified' => true, 'iat' => time() - 60, 'exp' => time() + 3600, 'auth_time' => time() - 60];
        $this->withToken($other)->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_unverified_firebase_user_cannot_link_an_existing_profile_or_become_admin(): void
    {
        $existing = $this->account('ADMIN');
        $plain = Str::random(64);
        $this->firebaseTokens->tokens[$plain] = ['sub' => 'new-uid', 'email' => $existing['email'], 'email_verified' => false, 'iat' => time() - 60, 'exp' => time() + 3600, 'auth_time' => time() - 60];
        $this->withToken($plain)->postJson('/api/auth/register', ['name' => 'Attempt', 'username' => 'attempt_user'])->assertForbidden();
        $this->assertSame('ADMIN', $this->store->get('users', $existing['id'])['role']);
    }

    public function test_reader_cannot_create_submission_or_read_admin_user_data(): void
    {
        $this->actingAsRecord($this->account('READER'));
        $this->postJson('/api/submissions', ['title' => 'Draft', 'content' => 'Body'])->assertForbidden();
        $this->getJson('/api/admin/users')->assertForbidden();
    }

    public function test_contributor_can_create_and_only_read_owned_submissions(): void
    {
        $author = $this->account('CONTRIBUTOR'); $this->actingAsRecord($author);
        $response = $this->postJson('/api/submissions', ['title' => 'Draft title', 'content' => 'Draft body']);
        $response->assertCreated()->assertJsonPath('submission.status', 'DRAFT'); $ownId = $response->json('submission.id'); $this->getJson('/api/submissions/'.$ownId)->assertOk();
        $other = $this->account('CONTRIBUTOR'); $otherDraft = $this->store->create('submissions', Str::uuid()->toString(), ['title' => 'Private draft', 'content' => 'Secret', 'status' => 'DRAFT', 'authorId' => $other['id'], 'createdAt' => now()->toIso8601String(), 'updatedAt' => now()->toIso8601String()]);
        $this->getJson('/api/submissions/'.$otherDraft['id'])->assertForbidden();
    }

    public function test_contributor_cannot_review_and_editor_cannot_manage_admin_roles(): void
    {
        $author = $this->account('CONTRIBUTOR'); $submission = $this->store->create('submissions', Str::uuid()->toString(), ['title' => 'Submitted', 'content' => 'Body', 'status' => 'SUBMITTED', 'authorId' => $author['id'], 'createdAt' => now()->toIso8601String(), 'updatedAt' => now()->toIso8601String()]);
        $this->actingAsRecord($author); $this->postJson('/api/submissions/'.$submission['id'].'/review', ['action' => 'APPROVE'])->assertForbidden();
        $editor = $this->account('EDITOR'); $this->actingAsRecord($editor); $this->getJson('/api/admin/stats')->assertOk(); $this->getJson('/api/admin/users')->assertForbidden();
        $this->postJson('/api/submissions/'.$submission['id'].'/review', ['action' => 'REQUEST_CHANGES', 'feedback' => 'Please revise sourcing.'])->assertOk();
        $this->assertSame('CHANGES_REQUESTED', $this->store->get('submissions', $submission['id'])['status']);
    }

    public function test_only_admin_can_change_roles_and_cannot_demote_self(): void
    {
        $admin = $this->account('ADMIN'); $reader = $this->account('READER'); $this->actingAsRecord($admin);
        $this->patchJson('/api/admin/users/'.$reader['id'], ['role' => 'CONTRIBUTOR'])->assertOk()->assertJsonPath('user.role', 'CONTRIBUTOR');
        $this->patchJson('/api/admin/users/'.$admin['id'], ['role' => 'READER'])->assertUnprocessable();
    }

    public function test_editor_cannot_change_an_already_published_submission(): void
    {
        $editor = $this->account('EDITOR'); $author = $this->account('CONTRIBUTOR');
        $submission = $this->store->create('submissions', Str::uuid()->toString(), ['title' => 'Published article', 'content' => 'Body', 'status' => 'PUBLISHED', 'authorId' => $author['id'], 'createdAt' => now()->toIso8601String(), 'updatedAt' => now()->toIso8601String()]);
        $this->actingAsRecord($editor);
        $this->postJson('/api/submissions/'.$submission['id'].'/review', ['action' => 'REJECT'])->assertStatus(409);
        $this->assertSame('PUBLISHED', $this->store->get('submissions', $submission['id'])['status']);
    }

    public function test_repeated_newsletter_signup_is_idempotent(): void
    {
        $payload = ['email' => 'dispatch@example.test'];
        $this->postJson('/api/newsletter', $payload)->assertOk();
        $this->postJson('/api/newsletter', $payload)->assertOk();
        $this->assertCount(1, $this->store->query('newsletterSubscribers'));
    }
}
