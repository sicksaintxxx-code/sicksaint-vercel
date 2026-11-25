<?php

namespace App\Services;

use Google\Auth\ApplicationDefaultCredentials;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

/** Small server-only Firestore REST adapter. No browser credential is used. */
class FirestoreStore
{
    private function base(): string
    {
        $project = config('services.firestore.project_id');
        if (!$project) throw new RuntimeException('Firestore project is not configured.');
        $host = rtrim((string) config('services.firestore.emulator_host'), '/');
        return ($host ? 'http://' . $host : 'https://firestore.googleapis.com') . '/v1/projects/' . rawurlencode($project) . '/databases/(default)/documents';
    }

    private function client()
    {
        $http = Http::acceptJson()->timeout(15)->connectTimeout(5);
        if (config('services.firestore.emulator_host')) return $http;
        $json = config('services.firestore.credentials_json');
        $accessToken = Cache::remember('sicksaint.firestore.access_token', 3300, function () use ($json) {
            $scopes = ['https://www.googleapis.com/auth/datastore'];
            if ($json) {
                $credentials = json_decode($json, true);
                if (!is_array($credentials)) throw new RuntimeException('Firestore service credentials are invalid JSON.');
                $auth = new ServiceAccountCredentials($scopes, $credentials);
            } else {
                $auth = ApplicationDefaultCredentials::getCredentials($scopes);
            }
            $token = $auth->fetchAuthToken();
            if (empty($token['access_token'])) throw new RuntimeException('Unable to authenticate to Firestore.');
            return $token['access_token'];
        });
        return $http->withToken($accessToken);
    }

    public function get(string $collection, string $id): ?array
    {
        $response = $this->client()->get($this->base() . '/' . $collection . '/' . rawurlencode($id));
        if ($response->status() === 404) return null;
        $response->throw();
        return $this->decodeDocument($response->json());
    }

    public function put(string $collection, string $id, array $data): array
    {
        $mask = implode('&', array_map(fn ($field) => 'updateMask.fieldPaths=' . rawurlencode((string) $field), array_keys($data)));
        $response = $this->client()->patch($this->base() . '/' . $collection . '/' . rawurlencode($id) . ($mask ? '?' . $mask : ''), ['fields' => $this->encodeMap($data)]);
        $response->throw();
        return $this->decodeDocument($response->json());
    }

    public function create(string $collection, string $id, array $data): array
    {
        $response = $this->client()->post($this->base() . '/' . $collection . '?documentId=' . rawurlencode($id), ['fields' => $this->encodeMap($data)]);
        if ($response->status() === 409) throw new FirestoreConflictException('Firestore record already exists.');
        $response->throw();
        return $this->decodeDocument($response->json());
    }

    public function delete(string $collection, string $id): void
    {
        $response = $this->client()->delete($this->base() . '/' . $collection . '/' . rawurlencode($id));
        if ($response->status() !== 404) $response->throw();
    }

    /** @return list<array> */
    public function query(string $collection, array $filters = [], ?string $orderBy = null, bool $descending = false, int $limit = 500): array
    {
        $query = ['from' => [['collectionId' => $collection]], 'limit' => min(1000, max(1, $limit))];
        if ($filters) {
            $parts = [];
            foreach ($filters as [$field, $op, $value]) $parts[] = ['fieldFilter' => ['field' => ['fieldPath' => $field], 'op' => $op, 'value' => $this->encodeValue($value)]];
            $query['where'] = count($parts) === 1 ? $parts[0] : ['compositeFilter' => ['op' => 'AND', 'filters' => $parts]];
        }
        if ($orderBy) $query['orderBy'] = [['field' => ['fieldPath' => $orderBy], 'direction' => $descending ? 'DESCENDING' : 'ASCENDING']];
        $response = $this->client()->post($this->base() . ':runQuery', ['structuredQuery' => $query]);
        $response->throw();
        $records = [];
        foreach ($response->json() as $row) if (isset($row['document'])) $records[] = $this->decodeDocument($row['document']);
        return $records;
    }

    public static function id(): string { return (string) Str::uuid(); }

    private function decodeDocument(array $doc): array
    {
        $id = basename(str_replace('\\', '/', $doc['name'] ?? ''));
        $values = [];
        foreach (($doc['fields'] ?? []) as $key => $value) $values[$key] = $this->decodeValue($value);
        return ['id' => $id] + $values;
    }

    private function encodeMap(array $values): array
    {
        $fields = [];
        foreach ($values as $key => $value) $fields[$key] = $this->encodeValue($value);
        return $fields;
    }

    private function encodeValue(mixed $value): array
    {
        if ($value === null) return ['nullValue' => null];
        if ($value instanceof \DateTimeInterface) return ['timestampValue' => $value->format(DATE_RFC3339_EXTENDED)];
        if (is_bool($value)) return ['booleanValue' => $value];
        if (is_int($value)) return ['integerValue' => (string) $value];
        if (is_float($value)) return ['doubleValue' => $value];
        if (is_array($value)) {
            if (array_is_list($value)) return ['arrayValue' => ['values' => array_map(fn ($item) => $this->encodeValue($item), $value)]];
            return ['mapValue' => ['fields' => $this->encodeMap($value)]];
        }
        return ['stringValue' => (string) $value];
    }

    private function decodeValue(array $value): mixed
    {
        foreach ($value as $type => $data) return match ($type) {
            'stringValue' => $data, 'integerValue' => (int) $data, 'doubleValue' => (float) $data, 'booleanValue' => (bool) $data,
            'timestampValue' => $data, 'nullValue' => null,
            'arrayValue' => array_map(fn ($item) => $this->decodeValue($item), $data['values'] ?? []),
            'mapValue' => array_map(fn ($item) => $this->decodeValue($item), $data['fields'] ?? []), default => null,
        };
        return null;
    }
}
