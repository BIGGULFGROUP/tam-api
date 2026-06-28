<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialClip extends Model
{
    use HasUuids;

    protected $table = 'social_clips';

    protected $fillable = [
        'platform', 'external_clip_id', 'title', 'caption',
        'thumbnail_url', 'clip_url', 'embed_url', 'duration_seconds',
        'fetched_at', 'published_at', 'platform_metadata',
        'linked_video_id', 'mapping_status', 'match_confidence',
        'mapped_by', 'mapped_at',
    ];

    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'fetched_at' => 'datetime',
            'published_at' => 'datetime',
            'platform_metadata' => 'array',
            'match_confidence' => 'decimal:2',
            'mapped_at' => 'datetime',
        ];
    }

    public function linkedVideo(): BelongsTo
    {
        return $this->belongsTo(Video::class, 'linked_video_id');
    }

    public function mappedBy(): BelongsTo
    {
        return $this->belongsTo(AdminProfile::class, 'mapped_by');
    }

    // Scope helpers
    public function scopeByPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    public function scopeByMappingStatus($query, string $status)
    {
        return $query->where('mapping_status', $status);
    }

    public function scopeUnlinked($query)
    {
        return $query->where('mapping_status', 'unlinked');
    }

    public function scopeAutoMapped($query)
    {
        return $query->where('mapping_status', 'auto_mapped');
    }

    public function scopeNeedsReview($query)
    {
        return $query->whereIn('mapping_status', ['unlinked', 'auto_mapped']);
    }
}
