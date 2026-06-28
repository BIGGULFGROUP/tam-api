<?php

namespace App\Console\Commands;

use App\Models\NewsletterSubscriber;
use App\Models\Video;
use App\Services\BrevoService;
use Illuminate\Console\Command;

class SendNewsletterDigest extends Command
{
    protected $signature = 'newsletter:send-digest
                            {--niche= : Specific niche slug to send, or all if omitted}
                            {--limit=5 : Number of top stories per digest}
                            {--dry-run : Preview without sending}';

    protected $description = 'Send daily niche digests to newsletter subscribers via Brevo';

    public function handle(BrevoService $brevo): int
    {
        $niche = $this->option('niche');
        $limit = (int) $this->option('limit');
        $dryRun = $this->option('dry-run');

        // Get active subscribers grouped by niche preference
        $subscribers = NewsletterSubscriber::where('is_active', true)->get();

        if ($subscribers->isEmpty()) {
            $this->info('No active subscribers found.');
            return 0;
        }

        // Group subscribers by their niche preferences
        $byNiche = [];
        $globalSubscribers = [];

        foreach ($subscribers as $sub) {
            $niches = $sub->niches ?? [];
            if (empty($niches)) {
                $globalSubscribers[] = $sub;
            } else {
                foreach ($niches as $n) {
                    $byNiche[$n][] = $sub;
                }
            }
        }

        $nichesToProcess = $niche ? [$niche] : array_keys($byNiche);

        $totalSent = 0;

        foreach ($nichesToProcess as $targetNiche) {
            $nicheSubscribers = $byNiche[$targetNiche] ?? [];

            // Global subscribers also get niche digests
            $allRecipients = array_merge($nicheSubscribers, $globalSubscribers);

            // Deduplicate by email
            $unique = [];
            foreach ($allRecipients as $sub) {
                $unique[$sub->email] = $sub;
            }

            if (empty($unique)) {
                $this->line("Niche '{$targetNiche}': no subscribers.");
                continue;
            }

            // Get top stories for this niche
            $stories = Video::where('status', 'published')
                ->where('niche', $targetNiche)
                ->orderByDesc('published_at')
                ->limit($limit)
                ->get()
                ->map(fn ($v) => [
                    'title' => $v->title,
                    'description' => \Illuminate\Support\Str::limit(strip_tags($v->description ?? ''), 160),
                    'url' => url("/{$v->niche}/{$v->slug}"),
                    'thumbnail' => $v->thumbnail_url ?? $v->featured_image_url,
                ])
                ->values()
                ->all();

            if (empty($stories)) {
                $this->line("Niche '{$targetNiche}': no published content found.");
                continue;
            }

            $recipientList = array_values($unique);

            if ($dryRun) {
                $this->info("[DRY RUN] Niche '{$targetNiche}': would send to " . count($recipientList) . " subscribers with " . count($stories) . " stories.");
                continue;
            }

            $sent = $brevo->sendDailyDigest($stories, array_map(fn ($s) => [
                'email' => $s->email,
                'name' => $s->name ?? 'Reader',
            ], $recipientList), $targetNiche);

            $totalSent += $sent;
            $this->info("Niche '{$targetNiche}': sent to {$sent} of " . count($recipientList) . " subscribers.");
        }

        if ($dryRun) {
            $this->info('Dry run complete. No emails sent.');
        } else {
            $this->info("Digest complete. Total sent: {$totalSent}.");
        }

        return 0;
    }
}
