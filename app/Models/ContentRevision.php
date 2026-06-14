<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ContentRevision extends Model
{
    use HasUuids;

    protected $table = 'content_revisions';

    protected $fillable = [
        'content_id', 'title', 'body', 'word_count', 'saved_by',
    ];

    protected function casts(): array
    {
        return [
            'word_count' => 'integer',
        ];
    }
}
