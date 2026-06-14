<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class NewsletterSubscriber extends Model
{
    use HasUuids;

    protected $table = 'newsletter_subscribers';

    protected $fillable = [
        'email', 'name', 'niches', 'source', 'popup_type', 'subscription_context', 'confirmed_at', 'is_active', 'subscribed_at',
    ];

    protected function casts(): array
    {
        return [
            'niches'        => 'array',
            'subscription_context' => 'array',
            'is_active'     => 'boolean',
            'confirmed_at'  => 'datetime',
            'subscribed_at' => 'datetime',
        ];
    }
}
