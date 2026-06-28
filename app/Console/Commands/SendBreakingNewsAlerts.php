<?php

namespace App\Console\Commands;

use App\Models\NewsletterSubscriber;
use App\Models\Video;
use App\Services\BrevoService;
use Illuminate\Console\Command;

class SendBreakingNewsAlerts extends Command
{
    protected $signature = 'newsletter:send-breaking
                            {--dry-run : Preview without sending}';

    protected $description = 'Send breaking news alerts to subscribers who have breaking content';

    public function handle(BrevoService $brevo): int
    {
        $dryRun = $this->option('dry-run');

        // Find breaking content published in the last hour
        $breakingContent = Video::where('status', 'published')
            ->where('is_breaking', true)
            ->where('published_at', '>=', now()->subHours(2))
            ->orderByDesc('published_at')
            ->get();

        if ($breakingContent->isEmpty()) {
            $this->info('No recent breaking content found.');
            return 0;
        }

        $totalSent = 0;

        foreach ($breakingContent as $content) {
            $niche = $content->niche;

            // Get subscribers interested in this niche
            $subscribers = NewsletterSubscriber::where('is_active', true)
                ->where(function ($q) use ($niche) {
                    $q->whereJsonContains('niches', $niche)
                      ->orWhereNull('niches')
                      ->orWhere('niches', '[]');
                })
                ->get();

            if ($subscribers->isEmpty()) {
                $this->line("Breaking '{$content->title}': no matching subscribers.");
                continue;
            }

            if ($dryRun) {
                $this->info("[DRY RUN] Breaking '{$content->title}': would send to {$subscribers->count()} subscribers.");
                continue;
            }

            $recipients = $subscribers->map(fn ($s) => [
                'email' => $s->email,
                'name' => $s->name ?? 'Reader',
            ])->all();

            $sent = $brevo->sendBreakingNewsAlert([
                'title' => $content->title,
                'body' => \Illuminate\Support\Str::limit(strip_tags($content->description ?? ''), 200),
                'niche' => $content->niche,
                'slug' => $content->slug,
            ], $recipients);

            $totalSent += $sent;
            $this->info("Breaking '{$content->title}': sent to {$sent} subscribers.");
        }

        if ($dryRun) {
            $this->info('Dry run complete. No emails sent.');
        } else {
            $this->info("Breaking alerts complete. Total sent: {$totalSent}.");
        }

        return 0;
    }
}
