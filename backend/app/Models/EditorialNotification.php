<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EditorialNotification extends Model
{
    protected $table = 'notifications';
    protected $fillable = ['user_id', 'type', 'title', 'message', 'link', 'read'];
    protected function casts(): array { return ['read' => 'boolean']; }
}
