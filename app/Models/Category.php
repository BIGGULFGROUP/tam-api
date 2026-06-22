<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = [
        'slug', 'label', 'short_label', 'description', 'about',
        'accent_color', 'cover_image_url', 'icon',
        'youtube_channel_id', 'youtube_channel_username', 'youtube_channel_name', 'youtube_playlist_id',
        'auto_fetch_enabled', 'fetch_interval_hours',
        'seo_title', 'seo_description',
        'featured_publication_slug', 'spotlight_title',
        'subscribe_title', 'subscribe_body',
        'newsletter_title', 'newsletter_body',
        'sort_order', 'is_active', 'content_count', 'last_fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'auto_fetch_enabled' => 'boolean',
            'is_active'          => 'boolean',
            'content_count'      => 'integer',
            'sort_order'         => 'integer',
            'last_fetched_at'    => 'datetime',
        ];
    }
}
