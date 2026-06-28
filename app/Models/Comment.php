<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comment extends Model
{
    use HasUuids;

    protected $fillable = [
        'content_type', 'content_id', 'parent_id',
        'author_name', 'author_email', 'author_user_id',
        'body', 'is_approved', 'is_spam', 'moderation_status',
        'ip_address', 'upvotes', 'like_count',
    ];

    protected function casts(): array
    {
        return [
            'is_approved' => 'boolean',
            'is_spam'     => 'boolean',
            'upvotes'     => 'integer',
            'like_count'  => 'integer',
        ];
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Video::class, 'content_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function replies(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }
}
