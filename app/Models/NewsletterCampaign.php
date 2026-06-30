<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class NewsletterCampaign extends Model
{
    use HasUuids;

    protected $table = 'newsletter_campaigns';

    protected $fillable = [
        'newsletter_key', 'normalized_title', 'title', 'body', 'banner_url',
        'categories', 'fetch_interval_hours', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'categories' => 'array',
            'fetch_interval_hours' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (NewsletterCampaign $campaign) {
            if (!$campaign->newsletter_key) {
                $campaign->newsletter_key = (string) Str::uuid();
            }
            if ($campaign->title) {
                $campaign->normalized_title = static::uniqueSlug($campaign->title);
            }
        });

        static::updating(function (NewsletterCampaign $campaign) {
            if ($campaign->isDirty('title')) {
                $campaign->normalized_title = static::uniqueSlug($campaign->title, $campaign->id);
            }
        });
    }

    protected static function uniqueSlug(string $title, ?string $ignoreId = null): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $i = 1;

        while (
            static::where('normalized_title', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Analytics are derived from newsletter_popup_events (template_key =
     * newsletter_key) rather than stored as columns on the campaign.
     */
    public function analytics(): array
    {
        $events = NewsletterPopupEvent::where('template_key', $this->newsletter_key)
            ->selectRaw('event_type, count(*) as count')
            ->groupBy('event_type')
            ->pluck('count', 'event_type');

        $impressions = (int) ($events['impression'] ?? 0);
        $submits = (int) ($events['submit'] ?? 0);

        return [
            'impressions' => $impressions,
            'submits' => $submits,
            'clicks' => (int) ($events['click'] ?? 0),
            'conversion_rate' => $impressions > 0 ? round(($submits / $impressions) * 100, 2) : 0.0,
        ];
    }
}
