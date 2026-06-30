<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class NewsletterPopupTemplate extends Model
{
    use HasUuids;

    protected $table = 'newsletter_popup_templates';

    protected $fillable = [
        'template_key', 'name', 'title', 'body', 'interval_hours', 'categories', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'categories' => 'array',
            'interval_hours' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
