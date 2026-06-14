<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comment extends Model
{
    use HasUuids;

    protected $fillable = [
        'content_id', 'author_name', 'author_email',
        'body', 'is_approved', 'is_spam', 'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'is_approved' => 'boolean',
            'is_spam'     => 'boolean',
        ];
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Video::class, 'content_id');
    }
}
