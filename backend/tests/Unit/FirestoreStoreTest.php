<?php

namespace Tests\Unit;

use App\Services\FirestoreStore;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FirestoreStoreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.firestore.project_id' => 'sicksaint-test', 'services.firestore.emulator_host' => '127.0.0.1:8080']);
    }

    public function test_firestore_rest_document_values_are_decoded_and_written(): void
    {
        Http::fake(function (HttpRequest $request) {
            if ($request->method() === 'GET') return Http::response(['name' => 'projects/sicksaint-test/databases/(default)/documents/users/u1', 'fields' => ['name' => ['stringValue' => 'Ada'], 'role' => ['stringValue' => 'READER'], 'active' => ['booleanValue' => true], 'tags' => ['arrayValue' => ['values' => [['stringValue' => 'style']]]]]]);
            return Http::response(['name' => 'projects/sicksaint-test/databases/(default)/documents/users/u1', 'fields' => $request->data()['fields'] ?? []]);
        });
        $store = new FirestoreStore();
        $user = $store->get('users', 'u1');
        $this->assertSame(['id' => 'u1', 'name' => 'Ada', 'role' => 'READER', 'active' => true, 'tags' => ['style']], $user);
        $store->put('users', 'u1', ['active' => false, 'bio' => null, 'tags' => ['music']]);
        Http::assertSent(fn (HttpRequest $request) => $request->method() === 'PATCH' && $request->data()['fields']['active']['booleanValue'] === false && array_key_exists('bio', $request->data()['fields']) && $request->data()['fields']['tags']['arrayValue']['values'][0]['stringValue'] === 'music');
    }

    public function test_firestore_query_posts_structured_query_and_decodes_results(): void
    {
        Http::fake(fn () => Http::response([['document' => ['name' => 'projects/sicksaint-test/databases/(default)/documents/users/u2', 'fields' => ['username' => ['stringValue' => 'editor']]]]]));
        $rows = (new FirestoreStore())->query('users', [['username','EQUAL','editor']], 'createdAt', true, 10);
        $this->assertSame('u2', $rows[0]['id']);
        Http::assertSent(fn (HttpRequest $request) => str_ends_with($request->url(), ':runQuery') && $request->data()['structuredQuery']['where']['fieldFilter']['field']['fieldPath'] === 'username');
    }
}
