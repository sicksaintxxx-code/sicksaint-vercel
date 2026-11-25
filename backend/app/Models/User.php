<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;

class User extends Authenticatable implements MustVerifyEmailContract
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, MustVerifyEmailTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role', 'avatar_url', 'bio', 'title', 'location', 'website', 'twitter', 'linkedin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function submissions() { return $this->hasMany(ArticleSubmission::class, 'author_id'); }

    public function publicProfile(): array
    {
        return ['id' => (string) $this->id, 'email' => $this->email, 'username' => $this->username,
            'name' => $this->name, 'role' => $this->role, 'avatarUrl' => $this->avatar_url,
            'bio' => $this->bio, 'title' => $this->title, 'location' => $this->location,
            'website' => $this->website, 'twitter' => $this->twitter, 'linkedin' => $this->linkedin,
            'emailVerified' => $this->email_verified_at !== null];
    }
}
