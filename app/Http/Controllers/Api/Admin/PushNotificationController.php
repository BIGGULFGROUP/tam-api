<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushNotificationController extends Controller
{
    private PushNotificationService $pushService;

    public function __construct(PushNotificationService $pushService)
    {
        $this->pushService = $pushService;
    }

    /**
     * Send a breaking news push notification.
     * POST /api/backend-admin/push/send
     */
    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'content_id' => ['required', 'string'],
            'title' => ['required', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:500'],
            'niche' => ['nullable', 'string'],
            'slug' => ['nullable', 'string'],
        ]);

        $result = $this->pushService->sendBreakingNews([
            'id' => $data['content_id'],
            'title' => $data['title'],
            'body' => $data['body'] ?? $data['title'],
            'niche' => $data['niche'] ?? '',
            'slug' => $data['slug'] ?? '',
        ]);

        return response()->json([
            'sent' => true,
            'expo_sent' => $result['expo']['sent'] ?? 0,
            'expo_devices' => $result['expo']['tokens'] ?? 0,
            'web_sent' => $result['web']['sent'] ?? 0,
            'web_devices' => $result['web']['subscriptions'] ?? 0,
        ]);
    }

    /**
     * Get push notification status / device count.
     * GET /api/backend-admin/push/status
     */
    public function status(): JsonResponse
    {
        $expoCount = \DB::table('push_tokens')->where('is_active', true)->where('platform', 'expo')->count();
        $webCount = \DB::table('web_push_subscriptions')->where('is_active', true)->count();

        return response()->json([
            'expo_devices' => $expoCount,
            'web_devices' => $webCount,
            'total_devices' => $expoCount + $webCount,
        ]);
    }
}
