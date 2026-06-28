<?php

namespace App\Console\Commands;

use App\Models\NewsletterCampaign;
use App\Models\NewsletterSubscriber;
use App\Models\Video;
use App\Services\BrevoService;
use Illuminate\Console\Command;

class SendNewsletterCampaigns extends Command
{
    protected $signature = 'newsletter:send-campaigns
                            {--dry-run : Preview without sending}';

    protected $description = 'Send newsletter campaigns based on their fetch interval schedule';

    public function handle(BrevoService $brevo): int
    {
        $campaigns = NewsletterCampaign::where('is_active', true)->get();

        if ($campaigns->isEmpty()) {
            $this->info('No active newsletter campaigns found.');
            return 0;
        }

        $dryRun = $this->option('dry-run');
        $now = now();

        foreach ($campaigns as $campaign) {
            $intervalHours = $campaign->fetch_interval_hours ?? 24;

            // Check if campaign is due
            $lastSent = $campaign->updated_at;
            $nextDue = $lastSent->addHours($intervalHours);

            if ($nextDue->isAfter($now)) {
                $this->line("Campaign '{$campaign->title}': not due yet (next: {$nextDue->toDateTimeString()}).");
                continue;
            }

            // Get target subscribers
            $query = NewsletterSubscriber::where('is_active', true);

            if (!empty($campaign->categories)) {
                // Subscribers who selected any of the campaign's categories
                $query->where(function ($q) use ($campaign) {
                    foreach ($campaign->categories as $cat) {
                        $q->orWhereJsonContains('niches', $cat);
                    }
                });
            }

            $subscribers = $query->get();

            if ($subscribers->isEmpty()) {
                $this->line("Campaign '{$campaign->title}': no matching subscribers.");
                $campaign->touch(); // Update timestamp so we don't retry immediately
                continue;
            }

            // Gather content for campaign categories
            $categories = $campaign->categories ?? [];
            $stories = collect();

            if (empty($categories)) {
                // Global campaign — get latest from all niches
                $stories = Video::where('status', 'published')
                    ->orderByDesc('published_at')
                    ->limit(5)
                    ->get()
                    ->map(fn ($v) => [
                        'title' => $v->title,
                        'description' => \Illuminate\Support\Str::limit(strip_tags($v->description ?? ''), 160),
                        'url' => url("/{$v->niche}/{$v->slug}"),
                        'thumbnail' => $v->thumbnail_url ?? $v->featured_image_url,
                    ]);
            } else {
                $stories = Video::where('status', 'published')
                    ->whereIn('niche', $categories)
                    ->orderByDesc('published_at')
                    ->limit(5)
                    ->get()
                    ->map(fn ($v) => [
                        'title' => $v->title,
                        'description' => \Illuminate\Support\Str::limit(strip_tags($v->description ?? ''), 160),
                        'url' => url("/{$v->niche}/{$v->slug}"),
                        'thumbnail' => $v->thumbnail_url ?? $v->featured_image_url,
                    ]);
            }

            if ($stories->isEmpty()) {
                $this->line("Campaign '{$campaign->title}': no content for target categories.");
                $campaign->touch();
                continue;
            }

            if ($dryRun) {
                $this->info("[DRY RUN] Campaign '{$campaign->title}': would send to {$subscribers->count()} subscribers with {$stories->count()} stories.");
                continue;
            }

            $recipients = $subscribers->map(fn ($s) => [
                'email' => $s->email,
                'name' => $s->name ?? 'Reader',
            ])->all();

            $sent = $brevo->sendDailyDigest($stories->values()->all(), $recipients, $campaign->title);
            $campaign->touch(); // Update timestamp
            $this->info("Campaign '{$campaign->title}': sent to {$sent} of {$subscribers->count()} subscribers.");
        }

        if ($dryRun) {
            $this->info('Dry run complete. No emails sent.');
        }

        return 0;
    }
}
