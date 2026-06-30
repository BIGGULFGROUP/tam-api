<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterPopupEvent extends Model
{
    protected $table = 'newsletter_popup_events';

    protected $fillable = [
        'template_key', 'event_type', 'category_slug', 'session_id',
    ];
}
