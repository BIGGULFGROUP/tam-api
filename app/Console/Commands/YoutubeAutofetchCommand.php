<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Support\YoutubeAdminService;
use Illuminate\Support\Facades\Log;

class YoutubeAutofetchCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tam:youtube-autofetch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically fetches new videos from YouTube based on configured categories';

    /**
     * Execute the console command.
     */
    public function handle(YoutubeAdminService $youtubeService)
    {
        $this->info('Starting YouTube Autofetch...');
        Log::info('[YoutubeAutofetch] Command started');

        try {
            $result = $youtubeService->runAutofetch(null, 5, null, 'auto');
            
            if (isset($result['message'])) {
                $this->info($result['message']);
                Log::info('[YoutubeAutofetch] ' . $result['message']);
            } else {
                $processed = $result['processed'] ?? [];
                $this->info(sprintf('Successfully processed %d categories.', count($processed)));
                
                $headers = ['Category', 'Found', 'Imported', 'Updated', 'Skipped'];
                $rows = [];
                
                foreach ($processed as $category) {
                    $rows[] = [
                        $category['label'] ?? $category['slug'],
                        $category['videosFound'] ?? 0,
                        $category['videosImported'] ?? 0,
                        $category['videosUpdated'] ?? 0,
                        $category['videosSkipped'] ?? 0,
                    ];
                }
                
                if (count($rows) > 0) {
                    $this->table($headers, $rows);
                }
                
                Log::info('[YoutubeAutofetch] Processed categories.', ['count' => count($processed)]);
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('YouTube Autofetch failed: ' . $e->getMessage());
            Log::error('[YoutubeAutofetch] Failed: ' . $e->getMessage(), ['exception' => $e]);
            
            return self::FAILURE;
        }
    }
}
