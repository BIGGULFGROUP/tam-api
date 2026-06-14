<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Video extends Model
{
    use HasUuids;

    protected $table = 'videos';

    protected $fillable = [
        'content_type', 'youtube_id', 'title', 'slug', 'niche',
        'author', 'created_by', 'collaborator_ids', 'description', 'body', 'key_takeaways', 'tags',
        'duration', 'status', 'is_featured', 'is_breaking',
        'featured_image_url', 'thumbnail_url', 'source_channel_id', 'source_channel_name', 'source_channel_slug', 'views',
        'word_count', 'read_time', 'seo_title', 'seo_description',
        'seo_keywords', 'og_image_url', 'reviewed_by', 'reviewed_at',
        'published_at', 'scheduled_at',
    ];

    protected function casts(): array
    {
        return [
            'collaborator_ids' => 'array',
            'key_takeaways' => 'array',
            'tags'         => 'array',
            'seo_keywords' => 'array',
            'is_featured'  => 'boolean',
            'is_breaking'  => 'boolean',
            'views'        => 'integer',
            'word_count'   => 'integer',
            'read_time'    => 'integer',
            'published_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'reviewed_at'  => 'datetime',
        ];
    }

    public function tagRelations(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'content_tags', 'content_id', 'tag_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(AdminProfile::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(AdminProfile::class, 'reviewed_by');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ContentRevision::class, 'content_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ContentSubmission::class, 'content_id');
    }
}
