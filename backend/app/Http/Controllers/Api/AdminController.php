<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FirestoreUser;
use App\Services\FirestoreConflictException;
use App\Services\FirestoreStore;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function __construct(private FirestoreStore $store) {}
    public function users(Request $request)
    {
        $all = $this->store->query('users', [], 'createdAt', true, 1000); $perPage = min(100, max(1, $request->integer('per_page', 50))); $page = max(1, $request->integer('page', 1)); $total = count($all);
        $users = array_map(function ($u) { return (new FirestoreUser($u))->publicProfile() + ['createdAt' => $u['createdAt'] ?? null]; }, array_slice($all, ($page - 1) * $perPage, $perPage));
        return response()->json(['users' => $users, 'pagination' => ['page' => $page, 'perPage' => $perPage, 'total' => $total]]);
    }
    public function updateUser(Request $request, string $user)
    {
        $data = $request->validate(['role' => ['required',Rule::in(['READER','CONTRIBUTOR','EDITOR','ADMIN'])]]); $target = $this->store->get('users', $user); abort_unless($target, 404);
        abort_if($request->user()->id === $user && $data['role'] !== 'ADMIN', 422, 'You cannot demote your own administrator account.');
        $saved = $this->store->put('users', $user, ['role' => $data['role'], 'updatedAt' => now()->toIso8601String()]); return response()->json(['user' => (new FirestoreUser($saved))->publicProfile()]);
    }
    public function stats()
    {
        $submissions = $this->store->query('submissions', [], 'updatedAt', true, 1000); $users = $this->store->query('users', [], null, false, 1000);
        $recent = array_map(function ($s) { $author = $this->store->get('users', $s['authorId']); return ['id' => $s['id'], 'title' => $s['title'], 'status' => $s['status'], 'format' => $s['format'] ?? null, 'categorySlug' => $s['categorySlug'] ?? null, 'updatedAt' => $s['updatedAt'] ?? null, 'author' => ['name' => $author['name'] ?? null, 'username' => $author['username'] ?? null]]; }, array_slice($submissions, 0, 5));
        return response()->json(['totalUsers' => count($users), 'totalSubmissions' => count($submissions), 'pendingSubmissions' => count(array_filter($submissions, fn ($s) => in_array($s['status'], ['SUBMITTED','UNDER_REVIEW'], true))), 'publishedSubmissions' => count(array_filter($submissions, fn ($s) => $s['status'] === 'PUBLISHED')), 'draftSubmissions' => count(array_filter($submissions, fn ($s) => $s['status'] === 'DRAFT')), 'recentSubmissions' => $recent]);
    }
    public function notifications(Request $request)
    {
        $items = $this->store->query('notifications', [['userId','EQUAL',$request->user()->id]], 'createdAt', true, 50);
        return response()->json(['notifications' => $items, 'unreadCount' => count(array_filter($items, fn ($item) => empty($item['read'])))]);
    }
    public function readNotifications(Request $request)
    {
        $data = $request->validate(['ids' => ['required']]); abort_unless($data['ids'] === 'all' || is_array($data['ids']), 422);
        $items = $this->store->query('notifications', [['userId','EQUAL',$request->user()->id],['read','EQUAL',false]], null, false, 1000);
        foreach ($items as $item) if ($data['ids'] === 'all' || in_array($item['id'], $data['ids'], true)) $this->store->put('notifications', $item['id'], ['read' => true]);
        return response()->json(['success' => true]);
    }
    public function subscribe(Request $request)
    {
        $data = $request->validate(['email' => ['required','email:rfc','max:255']]); $email = mb_strtolower(trim($data['email'])); $id = hash('sha256', $email);
        if (!$this->store->get('newsletterSubscribers', $id)) {
            try { $this->store->create('newsletterSubscribers', $id, ['email' => $email, 'frequency' => 'WEEKLY', 'isActive' => true, 'subscribedAt' => now()->toIso8601String()]); }
            catch (FirestoreConflictException) { /* A simultaneous request already created this subscription. */ }
        }
        return response()->json(['success' => true, 'message' => 'Subscription confirmed. SICKSAINT weekly dispatches will arrive noise-free.']);
    }
    public function newsletterExport()
    {
        $all = $this->store->query('newsletterSubscribers', [['isActive','EQUAL',true]], 'subscribedAt', true, 1000);
        return response()->json(['success' => true, 'data' => array_slice($all, 0, 100), 'total' => count($all)]);
    }
}
