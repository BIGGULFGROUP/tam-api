<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class YoutubeFetchLog extends Model
{
    use HasUuids;

    protected $table = 'youtube_fetch_log';

    protected $fillable = [
        'category_slug',
        'videos_found',
        'videos_imported',
        'videos_skipped',
        'status',
        'error_message',
        'triggered_by',
        'triggered_by_admin',
    ];

    protected function casts(): array
    {
        return [
            'videos_found' => 'integer',
            'videos_imported' => 'integer',
            'videos_skipped' => 'integer',
        ];
    }
}
