<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotificationRead extends Model
{
    protected $fillable = [
        'user_id',
        'content_id',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(AdminProfile::class, 'user_id');
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Video::class, 'content_id');
    }
}
