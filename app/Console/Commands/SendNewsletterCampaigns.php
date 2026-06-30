<?php

namespace App\Console\Commands;

use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use App\Models\Video;
use App\Services\BrevoService;
use App\Support\PublicUrl;
use Illuminate\Console\Command;

class SendNewsletterCampaigns extends Command
{
    protected $signature = 'newsletter:send-campaigns
                            {--limit=5 : Number of stories to include per campaign}
                            {--dry-run : Preview without sending}';

    protected $description = 'Send active newsletter campaigns to their targeted subscribers via Brevo, respecting each campaign\'s fetch interval';

    public function handle(BrevoService $brevo): int
    {
        $limit = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');

        $campaigns = NewsletterCampaign::where('is_active', true)->get();

        if ($campaigns->isEmpty()) {
            $this->info('No active campaigns found.');
            return 0;
        }

        $totalSent = 0;

        foreach ($campaigns as $campaign) {
            $lastTouched = $campaign->updated_at;
            $dueAt = $lastTouched?->copy()->addHours($campaign->fetch_interval_hours);

            if ($dueAt && $dueAt->isFuture()) {
                $this->line("Campaign '{$campaign->title}': not due until {$dueAt}.");
                continue;
            }

            $categories = $campaign->categories ?? [];

            $query = NewsletterSubscriber::where('is_active', true);
            if (!empty($categories)) {
                $query->where(function ($q) use ($categories) {
                    foreach ($categories as $cat) {
                        $q->orWhereJsonContains('niches', $cat);
                    }
                    $q->orWhereNull('niches')->orWhereJsonLength('niches', 0);
                });
            }
            $subscribers = $query->get();

            if ($subscribers->isEmpty()) {
                $this->line("Campaign '{$campaign->title}': no matching subscribers.");
                continue;
            }

            $storiesQuery = Video::where('status', 'published')->orderByDesc('published_at');
            if (!empty($categories)) {
                $storiesQuery->whereIn('niche', $categories);
            }
            $stories = $storiesQuery->limit($limit)
                ->get()
                ->map(fn ($v) => [
                    'title' => $v->title,
                    'url' => PublicUrl::to("/{$v->niche}/{$v->slug}"),
                    'thumbnail' => $v->thumbnail_url ?? $v->featured_image_url,
                ])
                ->values()
                ->all();

            if ($dryRun) {
                $this->info("[DRY RUN] Campaign '{$campaign->title}': would send to " . $subscribers->count() . " subscribers. Sample link: " . ($stories[0]['url'] ?? PublicUrl::to('/')));
                continue;
            }

            $sent = $brevo->sendCampaign(
                $stories,
                $subscribers->map(fn ($s) => ['email' => $s->email, 'name' => 'Reader'])->all(),
                $campaign->title,
                $campaign->body,
                $campaign->banner_url
            );

            $campaign->touch(); // throttle next run via fetch_interval_hours without changing other fields
            $totalSent += $sent;
            $this->info("Campaign '{$campaign->title}': sent to {$sent} of " . $subscribers->count() . " subscribers.");
        }

        if ($dryRun) {
            $this->info('Dry run complete. No emails sent.');
        } else {
            $this->info("Campaign run complete. Total sent: {$totalSent}.");
        }

        return 0;
    }
}
