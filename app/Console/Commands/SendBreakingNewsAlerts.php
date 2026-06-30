<?php

namespace App\Console\Commands;

use App\Models\NewsletterSubscriber;
use App\Models\Video;
use App\Services\BrevoService;
use Illuminate\Console\Command;

class SendBreakingNewsAlerts extends Command
{
    protected $signature = 'newsletter:send-breaking {--dry-run : Preview without sending}';

    protected $description = 'Send breaking news email alerts for recently published breaking content via Brevo';

    public function handle(BrevoService $brevo): int
    {
        $dryRun = $this->option('dry-run');

        $breakingItems = Video::where('status', 'published')
            ->where('is_breaking', true)
            ->where('published_at', '>=', now()->subHours(2))
            ->orderByDesc('published_at')
            ->get();

        if ($breakingItems->isEmpty()) {
            $this->info('No new breaking content in the last 2 hours.');
            return 0;
        }

        $totalSent = 0;

        foreach ($breakingItems as $item) {
            $subscribers = NewsletterSubscriber::where('is_active', true)
                ->where(function ($q) use ($item) {
                    $q->whereJsonContains('niches', $item->niche)
                        ->orWhereNull('niches')
                        ->orWhereJsonLength('niches', 0);
                })
                ->get();

            if ($subscribers->isEmpty()) {
                continue;
            }

            if ($dryRun) {
                $this->info("[DRY RUN] '{$item->title}': would alert " . $subscribers->count() . " subscribers via " . \App\Support\PublicUrl::to("/{$item->niche}/{$item->slug}"));
                continue;
            }

            $sent = $brevo->sendBreakingNewsAlert([
                'title' => $item->title,
                'body' => \Illuminate\Support\Str::limit(strip_tags($item->description ?? ''), 200),
                'niche' => $item->niche,
                'slug' => $item->slug,
            ], $subscribers->map(fn ($s) => ['email' => $s->email, 'name' => 'Reader'])->all());

            $totalSent += $sent;
            $this->info("'{$item->title}': alerted {$sent} of " . $subscribers->count() . " subscribers.");
        }

        if ($dryRun) {
            $this->info('Dry run complete. No emails sent.');
        } else {
            $this->info("Breaking news run complete. Total sent: {$totalSent}.");
        }

        return 0;
    }
}
