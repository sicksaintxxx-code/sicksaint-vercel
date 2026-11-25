<?php

namespace Tests\Support;

use App\Services\FirestoreStore;
use RuntimeException;

class InMemoryFirestoreStore extends FirestoreStore
{
    public array $records = [];
    public function get(string $collection, string $id): ?array { return $this->records[$collection][$id] ?? null; }
    public function create(string $collection, string $id, array $data): array
    {
        if (isset($this->records[$collection][$id])) throw new RuntimeException('Firestore record already exists.');
        return $this->records[$collection][$id] = ['id' => $id] + $data;
    }
    public function put(string $collection, string $id, array $data): array
    {
        return $this->records[$collection][$id] = array_replace($this->records[$collection][$id] ?? ['id' => $id], $data);
    }
    public function delete(string $collection, string $id): void { unset($this->records[$collection][$id]); }
    public function query(string $collection, array $filters = [], ?string $orderBy = null, bool $descending = false, int $limit = 500): array
    {
        $rows = array_values($this->records[$collection] ?? []);
        foreach ($filters as [$field, $op, $value]) $rows = array_values(array_filter($rows, fn ($row) => match ($op) { 'EQUAL' => ($row[$field] ?? null) === $value, default => false }));
        if ($orderBy) usort($rows, fn ($a, $b) => (($a[$orderBy] ?? '') <=> ($b[$orderBy] ?? '')) * ($descending ? -1 : 1));
        return array_slice($rows, 0, min(1000, $limit));
    }
}
