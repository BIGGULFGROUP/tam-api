<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    use HasUuids;

    protected $fillable = [
        'label', 'slug', 'description', 'seo_title', 'seo_description', 'usage_count',
    ];

    protected function casts(): array
    {
        return [
            'usage_count' => 'integer',
        ];
    }

    public function videos(): BelongsToMany
    {
        return $this->belongsToMany(Video::class, 'content_tags', 'tag_id', 'content_id');
    }
}
