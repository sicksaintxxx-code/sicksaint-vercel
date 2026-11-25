<?php

namespace Tests\Feature;

use App\Services\FirestoreStore;
use Illuminate\Support\Str;
use Tests\Support\FakeFirebaseIdTokenVerifier;
use Tests\Support\InMemoryFirestoreStore;
use Tests\TestCase;

class PublishingWorkflowAndPublicArticleTest extends TestCase
{
    private InMemoryFirestoreStore $store;
    private FakeFirebaseIdTokenVerifier $firebaseTokens;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = new InMemoryFirestoreStore();
        $this->firebaseTokens = new FakeFirebaseIdTokenVerifier();
        $this->app->instance(FirestoreStore::class, $this->store);
        $this->app->instance(\App\Services\FirebaseIdTokenVerifier::class, $this->firebaseTokens);
    }

    private function createAccount(string $role): array
    {
        $email = Str::lower(Str::random(8)) . '@sicksaint.test';
        $id = hash('sha256', $email);
        return $this->store->create('users', $id, [
            'name' => ucfirst(strtolower($role)) . ' User',
            'username' => strtolower($role) . '_' . Str::lower(Str::random(5)),
            'email' => $email,
            'passwordHash' => bcrypt('SecurePassword123!'),
            'role' => $role,
            'emailVerifiedAt' => now()->toIso8601String(),
            'bio' => 'Editorial journalist and investigator.',
            'title' => 'Senior Editor',
        ]);
    }

    private function authenticateAs(array $user): void
    {
        $plainToken = Str::random(64);
        $this->firebaseTokens->tokens[$plainToken] = [
            'sub' => 'firebase-uid-' . $user['id'],
            'email' => $user['email'],
            'email_verified' => true,
            'iat' => time() - 60,
            'exp' => time() + 3600,
            'auth_time' => time() - 60,
        ];
        $this->withToken($plainToken);
    }

    public function test_complete_contributor_submission_and_admin_publishing_workflow(): void
    {
        $contributor = $this->createAccount('CONTRIBUTOR');
        $editor = $this->createAccount('EDITOR');
        $admin = $this->createAccount('ADMIN');

        // 1. Contributor creates draft
        $this->authenticateAs($contributor);
        $createRes = $this->postJson('/api/submissions', [
            'title' => 'Sovereign Compute Enclaves in Reykjavik',
            'content' => '<h2>Subterranean Data Arrays</h2><p>Glacial run-off cooling.</p>',
            'excerpt' => 'An investigative report into cold-climate computing.',
            'categorySlug' => 'technology',
            'tags' => ['compute', 'iceland', 'infrastructure'],
            'action' => 'draft',
        ]);
        $createRes->assertCreated();
        $submissionId = $createRes->json('submission.id');
        $this->assertSame('DRAFT', $createRes->json('submission.status'));

        // 2. Contributor updates draft and submits for review
        $updateRes = $this->putJson('/api/submissions/' . $submissionId, [
            'title' => 'The Sovereign Compute Enclaves in Reykjavik',
            'action' => 'submit',
        ]);
        $updateRes->assertOk();
        $this->assertSame('SUBMITTED', $updateRes->json('submission.status'));

        // 3. Contributor cannot approve or publish
        $this->postJson('/api/submissions/' . $submissionId . '/review', ['action' => 'APPROVE'])->assertForbidden();
        $this->postJson('/api/submissions/' . $submissionId . '/review', ['action' => 'PUBLISH'])->assertForbidden();

        // 4. Editor approves submission
        $this->authenticateAs($editor);
        $approveRes = $this->postJson('/api/submissions/' . $submissionId . '/review', [
            'action' => 'APPROVE',
            'feedback' => 'Fact-check verified. Clear for publication.',
        ]);
        $approveRes->assertOk();
        $this->assertSame('APPROVED', $approveRes->json('submission.status'));

        // 5. Editor attempts to publish (ONLY ADMIN can publish)
        $this->postJson('/api/submissions/' . $submissionId . '/review', ['action' => 'PUBLISH'])->assertForbidden();

        // 6. Admin publishes the article
        $this->authenticateAs($admin);
        $publishRes = $this->postJson('/api/submissions/' . $submissionId . '/review', ['action' => 'PUBLISH']);
        $publishRes->assertOk();
        $this->assertSame('PUBLISHED', $publishRes->json('submission.status'));
        $slug = $publishRes->json('submission.slug');
        $this->assertNotEmpty($slug);
        $this->assertNotNull($publishRes->json('submission.publishedAt'));

        // 7. Public API verifies article is live
        $publicListRes = $this->getJson('/api/articles');
        $publicListRes->assertOk();
        $this->assertSame(1, $publicListRes->json('pagination.total'));
        $this->assertSame('The Sovereign Compute Enclaves in Reykjavik', $publicListRes->json('data.0.title'));
        // Verify author private info (email) is NOT leaked
        $this->assertArrayNotHasKey('email', $publicListRes->json('data.0.author'));
        $this->assertSame('Senior Editor', $publicListRes->json('data.0.author.title'));

        // 8. Public detail by slug
        $publicDetailRes = $this->getJson('/api/articles/' . $slug);
        $publicDetailRes->assertOk();
        $this->assertSame('The Sovereign Compute Enclaves in Reykjavik', $publicDetailRes->json('data.title'));
        $this->assertStringContainsString('Subterranean Data Arrays', $publicDetailRes->json('data.content'));

        // 9. Categories and tags endpoints
        $categoriesRes = $this->getJson('/api/categories');
        $categoriesRes->assertOk();
        $this->assertTrue(collect($categoriesRes->json('data'))->contains('slug', 'technology'));

        $tagsRes = $this->getJson('/api/tags');
        $tagsRes->assertOk();
        $this->assertTrue(collect($tagsRes->json('data'))->contains('slug', 'compute'));

        // 10. Admin can unpublish the article
        $unpublishRes = $this->postJson('/api/submissions/' . $submissionId . '/review', ['action' => 'UNPUBLISH']);
        $unpublishRes->assertOk();
        $this->assertSame('APPROVED', $unpublishRes->json('submission.status'));

        // 11. Article is immediately removed from public listing
        $emptyListRes = $this->getJson('/api/articles');
        $emptyListRes->assertOk();
        $this->assertSame(0, $emptyListRes->json('pagination.total'));

        // 12. Article detail returns 404 when unpublished
        $this->getJson('/api/articles/' . $slug)->assertNotFound();
    }

    public function test_drafts_and_rejected_articles_never_leak_to_public_api(): void
    {
        $contributor = $this->createAccount('CONTRIBUTOR');
        $this->authenticateAs($contributor);

        // Create a draft
        $draft = $this->store->create('submissions', Str::uuid()->toString(), [
            'title' => 'Top Secret Classified Investigation',
            'slug' => 'top-secret-investigation',
            'content' => 'Classified draft content.',
            'status' => 'DRAFT',
            'authorId' => $contributor['id'],
            'createdAt' => now()->toIso8601String(),
            'updatedAt' => now()->toIso8601String(),
        ]);

        // Create a rejected submission
        $rejected = $this->store->create('submissions', Str::uuid()->toString(), [
            'title' => 'Defective Story Pitch',
            'slug' => 'defective-story-pitch',
            'content' => 'Unsubstantiated claims.',
            'status' => 'REJECTED',
            'authorId' => $contributor['id'],
            'createdAt' => now()->toIso8601String(),
            'updatedAt' => now()->toIso8601String(),
        ]);

        // Public list should return 0 items
        $res = $this->getJson('/api/articles');
        $res->assertOk();
        $this->assertSame(0, $res->json('pagination.total'));

        // Neither slug should be accessible publicly
        $this->getJson('/api/articles/top-secret-investigation')->assertNotFound();
        $this->getJson('/api/articles/defective-story-pitch')->assertNotFound();
    }
}
