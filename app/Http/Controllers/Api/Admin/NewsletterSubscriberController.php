<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NewsletterSubscriberController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = NewsletterSubscriber::query()->orderByDesc('subscribed_at');

        if ($search = $request->query('search')) {
            $query->where('email', 'like', "%{$search}%");
        }

        $perPage = min((int) $request->query('limit', 25), 200);
        $page = max((int) $request->query('page', 1), 1);

        $subscribers = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json($subscribers);
    }

    public function export(): StreamedResponse
    {
        $filename = 'newsletter-subscribers-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Email', 'Niches', 'Source', 'Active', 'Subscribed At']);

            NewsletterSubscriber::orderByDesc('subscribed_at')
                ->chunk(500, function ($chunk) use ($handle) {
                    foreach ($chunk as $sub) {
                        fputcsv($handle, [
                            $sub->email,
                            implode('|', $sub->niches ?? []),
                            $sub->source,
                            $sub->is_active ? 'yes' : 'no',
                            $sub->subscribed_at?->toDateTimeString(),
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
