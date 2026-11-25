<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FirestoreStore;
use App\Services\HtmlSanitizer;
use App\Services\WordPressPublisher;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SubmissionController extends Controller
{
    public function __construct(
        private FirestoreStore $store,
        private HtmlSanitizer $sanitizer,
        private WordPressPublisher $wpPublisher
    ) {}

    private function find(string $id): array
    {
        return $this->store->get('submissions', $id) ?? abort(404, 'Submission not found.');
    }

    private function shape(array $s): array
    {
        $author = $this->store->get('users', $s['authorId']) ?? [];
        $reviews = $this->store->query('reviews', [['submissionId', 'EQUAL', $s['id']]], 'createdAt', true, 100);

        return [
            'id' => $s['id'],
            'title' => $s['title'],
            'slug' => $s['slug'] ?? null,
            'subtitle' => $s['subtitle'] ?? null,
            'excerpt' => $s['excerpt'] ?? null,
            'content' => $s['content'],
            'coverImageUrl' => $s['coverImageUrl'] ?? null,
            'coverImageCredit' => $s['coverImageCredit'] ?? null,
            'categorySlug' => $s['categorySlug'] ?? null,
            'tags' => $s['tags'] ?? [],
            'format' => $s['format'] ?? 'DISPATCH',
            'status' => $s['status'],
            'seoTitle' => $s['seoTitle'] ?? null,
            'seoDescription' => $s['seoDescription'] ?? null,
            'publishedSlug' => $s['publishedSlug'] ?? $s['slug'] ?? null,
            'publishedAt' => $s['publishedAt'] ?? null,
            'submittedAt' => $s['submittedAt'] ?? null,
            'createdAt' => $s['createdAt'] ?? null,
            'updatedAt' => $s['updatedAt'] ?? null,
            'feedback' => $s['feedback'] ?? null,
            'author' => [
                'id' => $s['authorId'],
                'name' => $author['name'] ?? '',
                'username' => $author['username'] ?? '',
                'avatarUrl' => $author['avatarUrl'] ?? null,
                'bio' => $author['bio'] ?? null,
                'title' => $author['title'] ?? null,
            ],
            'reviews' => array_map(function ($r) {
                $reviewer = $this->store->get('users', $r['reviewerId'] ?? '');
                return [
                    'id' => $r['id'],
                    'action' => $r['action'],
                    'feedback' => $r['feedback'] ?? null,
                    'createdAt' => $r['createdAt'],
                    'reviewer' => ['name' => $reviewer['name'] ?? null],
                ];
            }, $reviews),
        ];
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $all = $this->store->query('submissions', [], 'updatedAt', true, 1000);

        if (!in_array($user->role, ['EDITOR', 'ADMIN'], true)) {
            $all = array_values(array_filter($all, fn ($s) => $s['authorId'] === $user->id));
        } elseif ($request->filled('status')) {
            $all = array_values(array_filter($all, fn ($s) => $s['status'] === $request->string('status')->toString()));
        }

        $perPage = min(100, max(1, $request->integer('per_page', 20)));
        $page = max(1, $request->integer('page', 1));
        $total = count($all);

        return response()->json([
            'submissions' => array_map(fn ($s) => $this->shape($s), array_slice($all, ($page - 1) * $perPage, $perPage)),
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => (int) ceil($total / $perPage),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:200', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'content' => ['required', 'string', 'max:100000'],
            'subtitle' => ['nullable', 'string', 'max:300'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'coverImageUrl' => ['nullable', 'url:https', 'max:2048'],
            'coverImageCredit' => ['nullable', 'string', 'max:500'],
            'categorySlug' => ['nullable', 'string', 'max:120'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:80'],
            'format' => ['nullable', Rule::in(['DISPATCH', 'DOSSIER', 'DIALOGUE', 'ESSAY', 'VISUAL_STORY'])],
            'seoTitle' => ['nullable', 'string', 'max:200'],
            'seoDescription' => ['nullable', 'string', 'max:500'],
            'action' => ['nullable', Rule::in(['draft', 'submit'])],
        ]);

        $submit = ($data['action'] ?? null) === 'submit';
        abort_if($submit && !$request->user()->hasVerifiedEmail(), 403, 'Verify your email before submitting work.');

        $now = now()->toIso8601String();
        $id = FirestoreStore::id();
        $cleanContent = $this->sanitizer->clean($data['content']);

        // Generate or ensure slug
        $slug = !empty($data['slug']) ? Str::lower(trim($data['slug'])) : Str::slug($data['title']);
        $slug = $this->ensureUniqueSlug($slug, $id);

        $record = $this->store->create('submissions', $id, [
            'title' => trim($data['title']),
            'slug' => $slug,
            'content' => $cleanContent,
            'subtitle' => $data['subtitle'] ?? null,
            'excerpt' => $data['excerpt'] ?? null,
            'coverImageUrl' => $data['coverImageUrl'] ?? null,
            'coverImageCredit' => $data['coverImageCredit'] ?? null,
            'categorySlug' => $data['categorySlug'] ?? 'culture',
            'tags' => $data['tags'] ?? [],
            'format' => $data['format'] ?? 'DISPATCH',
            'status' => $submit ? 'SUBMITTED' : 'DRAFT',
            'authorId' => $request->user()->id,
            'seoTitle' => $data['seoTitle'] ?? null,
            'seoDescription' => $data['seoDescription'] ?? null,
            'submittedAt' => $submit ? $now : null,
            'createdAt' => $now,
            'updatedAt' => $now,
        ]);

        if ($submit) {
            $this->notify($request->user()->id, 'SUBMISSION_STATUS', 'Submission received', 'Your article has been submitted for editorial review.');
        }

        return response()->json(['submission' => $this->shape($record)], 201);
    }

    public function show(Request $request, string $submission)
    {
        $record = $this->find($submission);
        abort_unless(
            $record['authorId'] === $request->user()->id || in_array($request->user()->role, ['EDITOR', 'ADMIN'], true),
            403,
            'You do not have permission to view this submission.'
        );

        return response()->json(['submission' => $this->shape($record)]);
    }

    public function update(Request $request, string $submission)
    {
        $record = $this->find($submission);
        $user = $request->user();
        $isStaff = in_array($user->role, ['EDITOR', 'ADMIN'], true);

        // Permissions check: staff can edit any submission; contributor can only edit their own draft or changes_requested
        if (!$isStaff) {
            abort_unless($record['authorId'] === $user->id, 403, 'You do not own this submission.');
            abort_unless(in_array($record['status'], ['DRAFT', 'CHANGES_REQUESTED'], true), 409, 'This submission can no longer be edited by contributor.');
        }

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:200', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'content' => ['sometimes', 'required', 'string', 'max:100000'],
            'subtitle' => ['sometimes', 'nullable', 'string', 'max:300'],
            'excerpt' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'coverImageUrl' => ['sometimes', 'nullable', 'url:https', 'max:2048'],
            'coverImageCredit' => ['sometimes', 'nullable', 'string', 'max:500'],
            'categorySlug' => ['sometimes', 'nullable', 'string', 'max:120'],
            'tags' => ['sometimes', 'nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:80'],
            'format' => ['sometimes', Rule::in(['DISPATCH', 'DOSSIER', 'DIALOGUE', 'ESSAY', 'VISUAL_STORY'])],
            'seoTitle' => ['sometimes', 'nullable', 'string', 'max:200'],
            'seoDescription' => ['sometimes', 'nullable', 'string', 'max:500'],
            'action' => ['sometimes', Rule::in(['draft', 'submit'])],
        ]);

        $submit = ($data['action'] ?? null) === 'submit';
        unset($data['action']);

        if (isset($data['content'])) {
            $data['content'] = $this->sanitizer->clean($data['content']);
        }

        if (!empty($data['slug'])) {
            $data['slug'] = $this->ensureUniqueSlug(Str::lower(trim($data['slug'])), $submission);
        } elseif (isset($data['title']) && empty($record['slug'])) {
            $data['slug'] = $this->ensureUniqueSlug(Str::slug($data['title']), $submission);
        }

        foreach ($data as $key => $value) {
            $record[$key] = $value;
        }

        if ($submit && !$isStaff) {
            abort_unless($user->hasVerifiedEmail(), 403, 'Verify your email before submitting work.');
            $record['status'] = 'SUBMITTED';
            $record['submittedAt'] = now()->toIso8601String();
            $record['feedback'] = null;
        }

        $record['updatedAt'] = now()->toIso8601String();
        unset($record['id']);

        $saved = $this->store->put('submissions', $submission, $record);
        return response()->json(['submission' => $this->shape($saved)]);
    }

    public function destroy(Request $request, string $submission)
    {
        $record = $this->find($submission);
        $user = $request->user();
        $isStaff = in_array($user->role, ['EDITOR', 'ADMIN'], true);

        if (!$isStaff) {
            abort_unless($record['authorId'] === $user->id, 403);
            abort_unless(in_array($record['status'], ['DRAFT', 'REJECTED'], true), 409, 'Only drafts or rejected submissions may be deleted.');
        } else {
            // Staff can delete drafts, rejected, or unpublish first
            abort_if($record['status'] === 'PUBLISHED', 409, 'Unpublish the article before deleting.');
        }

        $this->store->delete('submissions', $submission);
        return response()->json(['success' => true]);
    }

    public function review(Request $request, string $submission)
    {
        $record = $this->find($submission);
        $user = $request->user();

        $data = $request->validate([
            'action' => ['required', Rule::in(['APPROVE', 'REJECT', 'REQUEST_CHANGES', 'PUBLISH', 'UNPUBLISH'])],
            'feedback' => ['nullable', 'string', 'max:5000'],
        ]);

        $action = $data['action'];

        // Enforce permissions: ONLY ADMIN can PUBLISH or UNPUBLISH
        if (in_array($action, ['PUBLISH', 'UNPUBLISH'], true)) {
            abort_unless($user->role === 'ADMIN', 403, 'Only administrators can publish or unpublish articles.');
        }

        if (in_array($action, ['APPROVE', 'REJECT', 'REQUEST_CHANGES'], true)) {
            abort_unless(in_array($record['status'], ['UNDER_REVIEW', 'SUBMITTED'], true), 409, 'This submission is no longer awaiting editorial review.');
        }

        if ($action === 'PUBLISH') {
            abort_unless(in_array($record['status'], ['APPROVED', 'PUBLISHED'], true), 409, 'Only an approved submission can be published.');
        }

        if ($action === 'UNPUBLISH') {
            abort_unless($record['status'] === 'PUBLISHED', 409, 'Only currently published articles can be unpublished.');
        }

        $now = now()->toIso8601String();
        $next = match ($action) {
            'APPROVE' => 'APPROVED',
            'REJECT' => 'REJECTED',
            'REQUEST_CHANGES' => 'CHANGES_REQUESTED',
            'PUBLISH' => 'PUBLISHED',
            'UNPUBLISH' => 'APPROVED',
        };

        if ($action === 'PUBLISH') {
            // Ensure unique published slug
            $slug = !empty($record['slug']) ? $record['slug'] : Str::slug($record['title']);
            $record['slug'] = $this->ensureUniqueSlug($slug, $submission);
            $record['publishedSlug'] = $record['slug'];
            if (empty($record['publishedAt'])) {
                $record['publishedAt'] = $now;
            }
            // Sanitize content before publishing
            $record['content'] = $this->sanitizer->clean($record['content']);

            // Optional non-blocking WP sync if configured
            $this->wpPublisher->publish($record);
        } elseif ($action === 'APPROVE') {
            // Optional non-blocking WP pending sync if configured
            $wpPostId = $this->wpPublisher->createPending($record);
            if ($wpPostId) {
                $record['wordpressPostId'] = $wpPostId;
            }
        }

        $this->store->create('reviews', FirestoreStore::id(), [
            'submissionId' => $submission,
            'reviewerId' => $user->id,
            'action' => $action,
            'feedback' => $data['feedback'] ?? null,
            'createdAt' => $now,
        ]);

        $record['status'] = $next;
        $record['feedback'] = $data['feedback'] ?? null;
        $record['updatedAt'] = $now;
        unset($record['id']);

        $saved = $this->store->put('submissions', $submission, $record);

        $notifMsg = match ($action) {
            'PUBLISH' => 'Your article is now live on SICKSAINT.',
            'UNPUBLISH' => 'Your article has been withdrawn from public dissemination.',
            'APPROVE' => 'Your submission has been approved for publication.',
            'REJECT' => 'Your submission was not accepted for publication.',
            'REQUEST_CHANGES' => $data['feedback'] ?: 'The editorial team has requested revisions to your submission.',
        };
        $this->notify($record['authorId'], 'REVIEW_FEEDBACK', 'Editorial Update', $notifMsg);

        return response()->json(['submission' => $this->shape($saved)]);
    }

    private function ensureUniqueSlug(string $baseSlug, string $currentId): string
    {
        $slug = $baseSlug ?: 'dispatch-' . substr($currentId, 0, 8);
        $existing = $this->store->query('submissions', [['slug', 'EQUAL', $slug]], null, false, 10);
        $collisions = array_filter($existing, fn ($item) => ($item['id'] ?? '') !== $currentId);

        if (empty($collisions)) {
            return $slug;
        }

        return $slug . '-' . substr($currentId, 0, 4);
    }

    private function notify(string $userId, string $type, string $title, string $message): void
    {
        $this->store->create('notifications', FirestoreStore::id(), [
            'userId' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'link' => '/dashboard',
            'read' => false,
            'createdAt' => now()->toIso8601String(),
        ]);
    }
}
