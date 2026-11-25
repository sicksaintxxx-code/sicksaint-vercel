<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EditorialReview extends Model
{
    protected $fillable = ['submission_id', 'reviewer_id', 'action', 'feedback'];
    public function reviewer() { return $this->belongsTo(User::class, 'reviewer_id'); }
}
