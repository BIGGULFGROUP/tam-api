<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterPopupEvent extends Model
{
    protected $table = 'newsletter_popup_events';

    protected $fillable = [
        'template_key', 'event_type', 'category_slug', 'page_path', 'session_id',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
