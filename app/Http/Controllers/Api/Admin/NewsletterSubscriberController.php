<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsletterSubscriberController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = min(500, max(1, (int) $request->query('limit', 100)));
        $page = max(1, (int) $request->query('page', 1));
        $query = NewsletterSubscriber::query()
            ->where('is_active', true)
            ->orderByDesc('subscribed_at');

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where('email', 'like', "%{$search}%");
        }

        $result = $query->paginate($limit, ['*'], 'page', $page);

        return response()->json(array_merge($result->toArray(), [
            'active_count' => NewsletterSubscriber::where('is_active', true)->count(),
        ]));
    }

    public function export(): JsonResponse
    {
        return response()->json(
            NewsletterSubscriber::where('is_active', true)
                ->orderByDesc('subscribed_at')
                ->get([
                    'id',
                    'email',
                    'name',
                    'niches',
                    'source',
                    'popup_type',
                    'subscription_context',
                    'confirmed_at',
                    'is_active',
                    'subscribed_at',
                ])
        );
    }
}
