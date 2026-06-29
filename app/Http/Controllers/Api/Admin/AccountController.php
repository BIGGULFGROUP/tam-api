<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminProfile;
use App\Models\NewsletterSubscriber;
use App\Models\UserNotificationPreference;
use App\Models\UserNotificationRead;
use App\Models\UserReadEvent;
use App\Models\Video;
use App\Services\IpGeolocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AccountController extends Controller
{
    private const MAX_AVATAR_BYTES = 8 * 1024 * 1024;
    private const ALLOWED_AVATAR_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif'];

    public function notifications(Request $request): JsonResponse
    {
        /** @var AdminProfile $admin */
        $admin = $request->user();
        $limit = min(20, max(1, (int) $request->query('limit', 6)));

        $prefs = UserNotificationPreference::firstOrCreate(
            ['user_id' => $admin->id],
            [
                'new_publications_enabled' => true,
                'subscribed_niches_only' => true,
                'weekly_digest_enabled' => true,
            ]
        );

        if (! $prefs->new_publications_enabled) {
            return response()->json(['unreadCount' => 0, 'items' => []]);
        }

        $query = Video::query()
            ->select(['id', 'slug', 'title', 'niche', 'thumbnail_url', 'published_at'])
            ->where('status', 'published')
            ->orderByDesc('published_at')
            ->limit($limit);

        if ($prefs->subscribed_niches_only !== false && $admin->email) {
            $subscriber = NewsletterSubscriber::query()
                ->where('email', Str::lower($admin->email))
                ->where('is_active', true)
                ->first(['niches']);

            $niches = is_array($subscriber?->niches) ? array_values(array_filter($subscriber->niches)) : [];
            if ($niches !== []) {
                $query->whereIn('niche', $niches);
            }
        }

        $items = $query->get();
        $readIds = UserNotificationRead::query()
            ->whereIn('content_id', $items->pluck('id'))
            ->where('user_id', $admin->id)
            ->pluck('content_id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $normalized = $items->map(function (Video $video) use ($readIds) {
            return [
                'id' => $video->id,
                'slug' => $video->slug,
                'title' => $video->title,
                'niche' => $video->niche,
                'thumbnailUrl' => $video->thumbnail_url,
                'publishedAt' => $video->published_at?->toISOString(),
                'isNew' => ! in_array((string) $video->id, $readIds, true),
            ];
        })->values();

        return response()->json([
            'unreadCount' => $normalized->where('isNew', true)->count(),
            'items' => $normalized,
        ]);
    }

    public function markNotificationsRead(Request $request): JsonResponse
    {
        /** @var AdminProfile $admin */
        $admin = $request->user();
        $contentIds = array_values(array_unique(array_filter(array_map(
            fn ($id) => (string) $id,
            $request->input('contentIds', []) ?? []
        ))));

        if ($contentIds === []) {
            return response()->json(['success' => true]);
        }

        $now = now();
        $rows = array_map(fn ($contentId) => [
            'user_id' => $admin->id,
            'content_id' => $contentId,
            'read_at' => $now,
            'updated_at' => $now,
            'created_at' => $now,
        ], $contentIds);

        UserNotificationRead::upsert($rows, ['user_id', 'content_id'], ['read_at', 'updated_at']);

        return response()->json(['success' => true]);
    }

    public function recordActivityRead(Request $request): JsonResponse
    {
        /** @var AdminProfile $admin */
        $admin = $request->user();
        $data = $request->validate([
            'contentId' => ['required', 'uuid'],
            'slug' => ['nullable', 'string', 'max:255'],
            'niche' => ['nullable', 'string', 'max:80'],
        ]);

        $ip = $request->ip();
        $geo = app(IpGeolocationService::class)->resolve($ip);

        UserReadEvent::query()->create([
            'user_id' => $admin->id,
            'content_id' => $data['contentId'],
            'slug' => $data['slug'] ?? null,
            'niche' => $data['niche'] ?? null,
            'viewed_at' => now(),
            'ip_address' => $ip,
            'country_code' => $geo['code'],
            'country_name' => $geo['name'],
        ]);

        UserNotificationRead::query()->upsert([
            [
                'user_id' => $admin->id,
                'content_id' => $data['contentId'],
                'read_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        ], ['user_id', 'content_id'], ['read_at', 'updated_at']);

        return response()->json(['recorded' => true], 200);
    }

    public function weeklyReadership(Request $request): JsonResponse
    {
        /** @var AdminProfile $admin */
        $admin = $request->user();
        $rows = UserReadEvent::query()
            ->selectRaw('niche, count(*) as views')
            ->where('user_id', $admin->id)
            ->where('viewed_at', '>=', now()->subWeek())
            ->whereNotNull('niche')
            ->groupBy('niche')
            ->orderByDesc('views')
            ->limit(3)
            ->get();

        $total = UserReadEvent::query()
            ->where('user_id', $admin->id)
            ->where('viewed_at', '>=', now()->subWeek())
            ->count();

        return response()->json([
            'total' => $total,
            'topNiches' => $rows->map(fn ($row) => [
                'niche' => $row->niche,
                'views' => (int) $row->views,
            ]),
        ]);
    }

    public function notificationPreferences(Request $request): JsonResponse
    {
        /** @var AdminProfile $admin */
        $admin = $request->user();
        $prefs = UserNotificationPreference::firstOrCreate(
            ['user_id' => $admin->id],
            [
                'new_publications_enabled' => true,
                'subscribed_niches_only' => true,
                'weekly_digest_enabled' => true,
            ]
        );

        if ($request->isMethod('put')) {
            $prefs->update($request->validate([
                'newPublicationsEnabled' => ['sometimes', 'boolean'],
                'subscribedNichesOnly' => ['sometimes', 'boolean'],
                'weeklyDigestEnabled' => ['sometimes', 'boolean'],
            ]));
        }

        return response()->json([
            'newPublicationsEnabled' => $prefs->new_publications_enabled,
            'subscribedNichesOnly' => $prefs->subscribed_niches_only,
            'weeklyDigestEnabled' => $prefs->weekly_digest_enabled,
        ]);
    }

    public function newsletterPreferences(Request $request): JsonResponse
    {
        /** @var AdminProfile $admin */
        $admin = $request->user();
        if (! $admin->email) {
            return response()->json(['error' => 'Email is required.'], 400);
        }

        $email = Str::lower($admin->email);

        if ($request->isMethod('put')) {
            $categories = array_values(array_unique(array_filter(array_map(
                fn ($slug) => (string) $slug,
                $request->input('categories', []) ?? []
            ))));

            NewsletterSubscriber::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $admin->full_name ?: $admin->display_name,
                    'niches' => $categories,
                    'is_active' => (bool) $request->input('isActive', false),
                    'source' => 'account_settings',
                    'popup_type' => null,
                    'subscription_context' => [
                        'origin' => 'account_settings',
                        'user_id' => $admin->id,
                    ],
                ]
            );
        }

        $subscriber = NewsletterSubscriber::query()
            ->where('email', $email)
            ->first(['niches', 'is_active']);

        return response()->json([
            'categories' => is_array($subscriber?->niches) ? $subscriber->niches : [],
            'isActive' => (bool) ($subscriber?->is_active ?? false),
        ]);
    }

    public function updateAvatar(Request $request): JsonResponse
    {
        /** @var AdminProfile $admin */
        $admin = $request->user();
        $file = $request->file('file');
        if (! $file) {
            return response()->json(['error' => 'No file uploaded.'], 400);
        }

        $mime = $file->getMimeType() ?? 'application/octet-stream';
        if (! in_array($mime, self::ALLOWED_AVATAR_MIMES, true)) {
            return response()->json(['error' => 'Unsupported image format.'], 400);
        }
        if ($file->getSize() > self::MAX_AVATAR_BYTES) {
            return response()->json(['error' => 'Image must be less than 8MB.'], 413);
        }

        $disk = config('filesystems.default', 'public');
        $safeName = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME))
            .'-'.$admin->id.'-'.now()->timestamp.'.'.$file->getClientOriginalExtension();
        $path = Storage::disk($disk)->putFileAs('avatars/'.$admin->id, $file, $safeName);
        $avatarUrl = Storage::disk($disk)->url($path);

        $admin->update([
            'avatar_url' => $avatarUrl,
            'updated_at' => now(),
        ]);

        return response()->json(['avatarUrl' => $avatarUrl]);
    }
}
