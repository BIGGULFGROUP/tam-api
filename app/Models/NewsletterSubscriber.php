<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class NewsletterSubscriber extends Model
{
    use HasUuids;

    protected $table = 'newsletter_subscribers';

    protected $fillable = [
        'email', 'niches', 'source', 'popup_type', 'is_active', 'subscribed_at',
    ];

    protected function casts(): array
    {
        return [
            'niches' => 'array',
            'is_active' => 'boolean',
            'subscribed_at' => 'datetime',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForNiche($query, string $niche)
    {
        return $query->where(function ($q) use ($niche) {
            $q->whereJsonContains('niches', $niche)
                ->orWhereNull('niches')
                ->orWhereJsonLength('niches', 0);
        });
    }
}
