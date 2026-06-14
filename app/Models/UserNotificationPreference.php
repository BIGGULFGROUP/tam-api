<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotificationPreference extends Model
{
    protected $primaryKey = 'user_id';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'new_publications_enabled',
        'subscribed_niches_only',
        'weekly_digest_enabled',
    ];

    protected function casts(): array
    {
        return [
            'new_publications_enabled' => 'boolean',
            'subscribed_niches_only' => 'boolean',
            'weekly_digest_enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(AdminProfile::class, 'user_id');
    }
}
