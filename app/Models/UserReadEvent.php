<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserReadEvent extends Model
{
    protected $fillable = [
        'user_id',
        'content_id',
        'slug',
        'niche',
        'viewed_at',
        'ip_address',
        'country_code',
        'country_name',
    ];

    protected function casts(): array
    {
        return [
            'viewed_at' => 'datetime',
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
