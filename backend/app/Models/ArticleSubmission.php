<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ArticleSubmission extends Model
{
    use HasUuids;
    protected $fillable = ['title', 'subtitle', 'excerpt', 'content', 'cover_image_url', 'cover_image_credit', 'category_slug', 'tags', 'format', 'status', 'feedback', 'author_id', 'published_slug', 'wordpress_post_id', 'submitted_at'];
    protected function casts(): array { return ['tags' => 'array', 'submitted_at' => 'datetime']; }
    public function author() { return $this->belongsTo(User::class, 'author_id'); }
    public function reviews() { return $this->hasMany(EditorialReview::class, 'submission_id')->latest(); }
}
