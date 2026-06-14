<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class NewsletterCampaign extends Model
{
    use HasUuids;

    protected $table = 'newsletter_campaigns';

    protected $fillable = [
        'newsletter_key', 'normalized_title', 'title', 'body',
        'banner_url', 'categories', 'fetch_interval_hours', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'categories'           => 'array',
            'is_active'            => 'boolean',
            'fetch_interval_hours' => 'integer',
        ];
    }
}
