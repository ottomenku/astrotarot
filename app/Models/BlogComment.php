<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogComment extends Model
{
    protected $fillable = [
        'blog_post_id',
        'user_id',
        'body',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(BlogPost::class, 'blog_post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function canDelete(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return $user->isAdmin() || $user->id === $this->user_id;
    }
}
