<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;

/** Plain authenticated user record loaded by the Firestore token middleware. */
class FirestoreUser implements AuthenticatableContract
{
    use Authenticatable;

    public function __construct(private array $attributes, private array $firebaseClaims = []) {}
    public function __get(string $key): mixed { return $this->attributes[$key] ?? null; }
    public function __set(string $key, mixed $value): void { $this->attributes[$key] = $value; }
    public function getKey(): string { return (string) $this->attributes['id']; }
    public function hasVerifiedEmail(): bool { return ($this->firebaseClaims['email_verified'] ?? false) === true || !empty($this->attributes['emailVerifiedAt']); }
    public function publicProfile(): array
    {
        return ['id' => $this->getKey(), 'email' => $this->email, 'username' => $this->username, 'name' => $this->name,
            'role' => $this->role, 'avatarUrl' => $this->avatarUrl, 'bio' => $this->bio, 'title' => $this->title,
            'location' => $this->location, 'website' => $this->website, 'twitter' => $this->twitter,
            'linkedin' => $this->linkedin, 'emailVerified' => $this->hasVerifiedEmail()];
    }
}
