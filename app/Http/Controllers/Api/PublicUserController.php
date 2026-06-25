<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class PublicUserController extends Controller
{
    // ─── Registration ──────────────────────────────────────────

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
        ]);

        $existing = User::where('email', $data['email'])->first();

        if ($existing) {
            return response()->json([
                'message' => 'A user with this email already exists.',
                'errors' => ['email' => ['Email already registered.']],
            ], 422);
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $token = $user->createToken('public-register')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'token' => $token,
        ], 201);
    }

    // ─── Profiles ───────────────────────────────────────────────

    public function profile(string $username): JsonResponse
    {
        $profile = DB::table('public_user_profiles')
            ->where('username', $username)
            ->where('is_public', true)
            ->first();

        if (!$profile) {
            return response()->json(['message' => 'Profile not found'], 404);
        }

        $userId = $profile->user_id;

        $articleCount = DB::table('videos')
            ->where('created_by', $userId)
            ->where('status', 'published')
            ->whereIn('content_type', ['article', 'video'])
            ->count();

        $shortCount = DB::table('videos')
            ->where('created_by', $userId)
            ->where('status', 'published')
            ->where('content_type', 'short')
            ->count();

        return response()->json([
            'id' => $profile->id,
            'username' => $profile->username,
            'displayName' => $profile->display_name,
            'bio' => $profile->bio,
            'avatarUrl' => $profile->avatar_url,
            'websiteUrl' => $profile->website_url,
            'twitterUrl' => $profile->twitter_url,
            'instagramUrl' => $profile->instagram_url,
            'linkedinUrl' => $profile->linkedin_url,
            'location' => $profile->location,
            'articleCount' => $articleCount,
            'shortCount' => $shortCount,
            'joinedAt' => $profile->created_at,
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $data = $request->validate([
            'displayName' => ['required', 'string', 'max:120'],
            'username' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_-]+$/'],
            'bio' => ['nullable', 'string', 'max:500'],
            'websiteUrl' => ['nullable', 'url', 'max:500'],
            'twitterUrl' => ['nullable', 'url', 'max:500'],
            'instagramUrl' => ['nullable', 'url', 'max:500'],
            'linkedinUrl' => ['nullable', 'url', 'max:500'],
            'location' => ['nullable', 'string', 'max:120'],
            'isPublic' => ['sometimes', 'boolean'],
        ]);

        $username = Str::lower(trim($data['username']));

        // Check username availability
        $existing = DB::table('public_user_profiles')
            ->where('username', $username)
            ->where('user_id', '!=', $user->id)
            ->exists();

        if ($existing) {
            return response()->json(['errors' => ['username' => ['Username already taken']]], 422);
        }

        DB::table('public_user_profiles')->updateOrInsert(
            ['user_id' => $user->id],
            [
                'id' => Str::uuid(),
                'display_name' => trim($data['displayName']),
                'username' => $username,
                'bio' => $data['bio'] ?? null,
                'website_url' => $data['websiteUrl'] ?? null,
                'twitter_url' => $data['twitterUrl'] ?? null,
                'instagram_url' => $data['instagramUrl'] ?? null,
                'linkedin_url' => $data['linkedinUrl'] ?? null,
                'location' => $data['location'] ?? null,
                'is_public' => $data['isPublic'] ?? true,
                'updated_at' => now(),
            ]
        );

        return response()->json(['success' => true, 'message' => 'Profile updated']);
    }

    // ─── Favorites ──────────────────────────────────────────────

    public function favorites(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;
        if (!$userId) {
            return response()->json(['items' => []]);
        }

        $items = DB::table('user_favorites')
            ->join('videos', 'user_favorites.content_id', '=', 'videos.id')
            ->where('user_favorites.user_id', $userId)
            ->orderByDesc('user_favorites.created_at')
            ->limit(100)
            ->select(
                'videos.id', 'videos.slug', 'videos.title', 'videos.thumbnail_url',
                'videos.niche', 'videos.author', 'videos.published_at',
                'videos.duration', 'videos.views', 'videos.content_type'
            )
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'slug' => $row->slug,
                'title' => $row->title,
                'thumbnailUrl' => $row->thumbnail_url,
                'niche' => $row->niche,
                'author' => $row->author,
                'publishedAt' => $row->published_at,
                'duration' => $row->duration,
                'views' => $row->views,
                'contentType' => $row->content_type ?? 'video',
            ]);

        return response()->json(['items' => $items]);
    }

    public function toggleFavorite(Request $request, string $contentId): JsonResponse
    {
        $userId = $request->user()?->id;
        if (!$userId) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $exists = DB::table('user_favorites')
            ->where('user_id', $userId)
            ->where('content_id', $contentId)
            ->exists();

        if ($exists) {
            DB::table('user_favorites')
                ->where('user_id', $userId)
                ->where('content_id', $contentId)
                ->delete();
            return response()->json(['saved' => false]);
        }

        DB::table('user_favorites')->insert([
            'id' => Str::uuid(),
            'user_id' => $userId,
            'content_id' => $contentId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['saved' => true]);
    }

    // ─── History ────────────────────────────────────────────────

    public function history(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;
        if (!$userId) {
            return response()->json(['items' => []]);
        }

        $items = DB::table('user_view_history')
            ->where('user_id', $userId)
            ->orderByDesc('viewed_at')
            ->limit(50)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'contentId' => $row->content_id,
                'niche' => $row->niche,
                'viewedAt' => $row->viewed_at,
            ]);

        return response()->json(['items' => $items]);
    }

    public function recordView(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;
        if (!$userId) return response()->json(['recorded' => false]);

        $data = $request->validate([
            'contentId' => ['nullable', 'uuid'],
            'niche' => ['nullable', 'string', 'max:60'],
        ]);

        DB::table('user_view_history')->insert([
            'id' => Str::uuid(),
            'user_id' => $userId,
            'content_id' => $data['contentId'] ?? null,
            'niche' => $data['niche'] ?? null,
            'viewed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['recorded' => true]);
    }

    // ─── Notification Preferences ───────────────────────────────

    public function updateNotificationPrefs(Request $request): JsonResponse
    {
        $userId = $request->user()?->id;
        if (!$userId) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $data = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.*' => ['boolean'],
        ]);

        DB::table('public_user_profiles')->updateOrInsert(
            ['user_id' => $userId],
            [
                'id' => Str::uuid(),
                'display_name' => $request->user()->name ?? '',
                'username' => Str::lower(Str::slug($request->user()->name ?? 'user')) . '-' . Str::random(6),
                'notification_preferences' => json_encode($data['preferences']),
                'updated_at' => now(),
            ]
        );

        return response()->json(['success' => true]);
    }

    // ─── Push Tokens ───────────────────────────────────────────

    public function registerPushToken(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'platform' => ['nullable', 'string', 'in:expo,fcm,apns'],
            'preferences' => ['nullable', 'array'],
        ]);

        DB::table('push_tokens')->updateOrInsert(
            ['token' => $data['token']],
            [
                'id' => Str::uuid(),
                'user_id' => $request->user()?->id,
                'platform' => $data['platform'] ?? 'expo',
                'is_active' => true,
                'preferences' => isset($data['preferences']) ? json_encode($data['preferences']) : null,
                'updated_at' => now(),
            ]
        );

        return response()->json(['registered' => true]);
    }

    public function unregisterPushToken(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
        ]);

        DB::table('push_tokens')->where('token', $data['token'])->delete();

        return response()->json(['unregistered' => true]);
    }

    // ─── Web Push ──────────────────────────────────────────────

    public function subscribeWebPush(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string'],
            'p256dh' => ['required', 'string'],
            'auth' => ['required', 'string'],
        ]);

        DB::table('web_push_subscriptions')->updateOrInsert(
            ['endpoint' => $data['endpoint']],
            [
                'id' => Str::uuid(),
                'endpoint' => $data['endpoint'],
                'p256dh' => $data['p256dh'],
                'auth' => $data['auth'],
                'user_id' => $request->user()?->id,
                'is_active' => true,
                'updated_at' => now(),
            ]
        );

        return response()->json(['subscribed' => true]);
    }

    // ─── Avatar Upload ───────────────────────────────────────

    public function uploadAvatar(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);

        $request->validate([
            'avatar' => ['required', 'image', 'max:2048', 'mimes:jpg,jpeg,png,webp'],
        ]);

        $file = $request->file('avatar');
        $filename = 'avatars/' . $user->id . '-' . time() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('public', $filename);

        $url = asset('storage/' . $filename);

        DB::table('admin_profiles')->where('id', $user->id)->update([
            'avatar_url' => $url,
            'updated_at' => now(),
        ]);

        return response()->json(['avatarUrl' => $url]);
    }

    // ─── Notifications ───────────────────────────────────────

    public function notifications(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) return response()->json(['items' => []]);

        $limit = min(20, max(1, (int) $request->query('limit', 10)));
        $unread = DB::table('user_notifications')
            ->where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        $items = DB::table('user_notifications')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'type' => $n->type,
                'message' => $n->message,
                'link' => $n->link,
                'isRead' => (bool) $n->is_read,
                'createdAt' => $n->created_at,
            ]);

        return response()->json(['items' => $items, 'unread' => $unread]);
    }

    public function markNotificationsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);

        $ids = $request->input('ids', []);
        if (!empty($ids)) {
            DB::table('user_notifications')
                ->where('user_id', $user->id)
                ->whereIn('id', $ids)
                ->update(['is_read' => true, 'updated_at' => now()]);
        } else {
            DB::table('user_notifications')
                ->where('user_id', $user->id)
                ->update(['is_read' => true, 'updated_at' => now()]);
        }

        return response()->json(['success' => true]);
    }

    // ─── Account Deletion ────────────────────────────────────

    public function deleteAccount(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);

        DB::transaction(function () use ($user) {
            // Anonymize comments
            DB::table('comments')->where('author_email', $user->email)->update([
                'author_name' => 'Deleted User',
                'author_email' => null,
            ]);

            // Remove push tokens
            DB::table('push_tokens')->where('user_id', $user->id)->delete();
            DB::table('web_push_subscriptions')->where('user_id', $user->id)->delete();

            // Remove favorites
            DB::table('user_favorites')->where('user_id', $user->id)->delete();

            // Remove notifications
            DB::table('user_notifications')->where('user_id', $user->id)->delete();

            // Remove public profile
            DB::table('public_user_profiles')->where('user_id', $user->id)->delete();

            // Remove tokens
            DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->delete();

            // Delete admin profile (main user record)
            DB::table('admin_profiles')->where('id', $user->id)->delete();
        });

        return response()->json(['deleted' => true]);
    }

}