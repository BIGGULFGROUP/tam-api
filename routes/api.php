<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\AuthController;
use App\Http\Controllers\Api\Admin\AdminProfileController;
use App\Http\Controllers\Api\Admin\VideoController;
use App\Http\Controllers\Api\Admin\CategoryController;
use App\Http\Controllers\Api\Admin\TagController;
use App\Http\Controllers\Api\Admin\CommentController;
use App\Http\Controllers\Api\Admin\MediaController;
use App\Http\Controllers\Api\Admin\ContentSubmissionController;
use App\Http\Controllers\Api\Admin\SiteSettingController;
use App\Http\Controllers\Api\Admin\AnalyticsController;
use App\Http\Controllers\Api\Admin\AccountController;
use App\Http\Controllers\Api\Admin\ActivityLogController;
use App\Http\Controllers\Api\Admin\YoutubeController;
use App\Http\Controllers\Api\Admin\SponsoredContentController;
use App\Http\Controllers\Api\Admin\AffiliateLinkController;
use App\Http\Controllers\Api\Admin\PushNotificationController;
use App\Http\Controllers\Api\Admin\SocialClipController;
use App\Http\Controllers\Api\ContributorApplicationController;
use App\Http\Controllers\Api\PublicSiteController;
use App\Http\Controllers\Api\CommentController as PublicCommentController;
use App\Http\Controllers\Api\PublicUserController;

Route::post('apply', [ContributorApplicationController::class, 'apply']);

Route::prefix('public')->group(function () {
    Route::get('categories', [PublicSiteController::class, 'categories']);
    Route::get('content', [PublicSiteController::class, 'content']);
    Route::get('content/latest', [PublicSiteController::class, 'latestContent']);
    Route::get('content/nav-menu', [PublicSiteController::class, 'navMenu']);
    Route::get('content/shorts', [PublicSiteController::class, 'shorts']);
    Route::get('search/suggestions', [PublicSiteController::class, 'searchSuggestions']);
    Route::get('tags', [PublicSiteController::class, 'tags']);
    Route::get('tags/{slug}', [PublicSiteController::class, 'tagBySlug']);
    Route::get('tags/{slug}/related', [PublicSiteController::class, 'relatedTags']);
    Route::get('authors/slug/{slug}', [PublicSiteController::class, 'authorBySlug']);
    Route::get('authors/name/{name}', [PublicSiteController::class, 'authorByName']);
    Route::get('authors/{id}', [PublicSiteController::class, 'authorById']);
    Route::get('site/settings', [PublicSiteController::class, 'siteSettings']);
    Route::get('comments', [PublicSiteController::class, 'comments']);
    // Rate-limited POST endpoints
    // Comments (threaded + upvotable)
    Route::get('comments/threaded', [PublicCommentController::class, 'threaded']);
    Route::middleware('throttle:comments')->group(function () {
        Route::post('comments', [PublicCommentController::class, 'submit']);
        Route::post('comments/{id}/upvote', [PublicCommentController::class, 'upvote']);
    });

    Route::middleware('throttle:content-view')->group(function () {
        Route::post('content/view', [PublicSiteController::class, 'recordView']);
        Route::post('analytics/pageview', [PublicSiteController::class, 'recordPageView']);
    });

    // Web push subscription
    Route::middleware('throttle:newsletter')->group(function () {
        Route::post('push/subscribe', [PublicUserController::class, 'subscribeWebPush']);
    });

    // User profiles (public)
    Route::get('profile/{username}', [PublicUserController::class, 'profile']);

    // Authenticated profile management
    Route::middleware('auth:sanctum')->group(function () {
        Route::put('profile', [PublicUserController::class, 'updateProfile']);
        Route::post('profile/avatar', [PublicUserController::class, 'uploadAvatar']);
        Route::get('notifications', [PublicUserController::class, 'notifications']);
        Route::post('notifications/read', [PublicUserController::class, 'markNotificationsRead']);
        Route::delete('account', [PublicUserController::class, 'deleteAccount']);
        Route::get('favorites', [PublicUserController::class, 'favorites']);
        Route::post('favorites/{contentId}', [PublicUserController::class, 'toggleFavorite']);
        Route::delete('favorites/{contentId}', [PublicUserController::class, 'toggleFavorite']);
        Route::get('history', [PublicUserController::class, 'history']);
        Route::post('history', [PublicUserController::class, 'recordView']);
        Route::put('notification-preferences', [PublicUserController::class, 'updateNotificationPrefs']);
        Route::post('push/register', [PublicUserController::class, 'registerPushToken']);
        Route::delete('push/register', [PublicUserController::class, 'unregisterPushToken']);
    });

    // Affiliate redirect
    Route::get('affiliate/{slug}', [AffiliateLinkController::class, 'recordClick']);

    // Reader registration
    Route::post('register', [PublicUserController::class, 'register']);

    // Recommendations
    Route::get('recommendations/related', [PublicSiteController::class, 'relatedContent']);
    Route::get('recommendations/trending', [PublicSiteController::class, 'trending']);
    Route::middleware('auth:sanctum')->get('recommendations/for-you', [PublicSiteController::class, 'recommendationsForUser']);

    // Sponsored content (public)
    Route::get('sponsored', [SponsoredContentController::class, 'index']);

    // Social clips (public)
    Route::get('clips', [PublicSiteController::class, 'clips']);
    Route::get('clips/{id}', [PublicSiteController::class, 'clipById']);

    // Comments for clips
    Route::get('clips/{id}/comments', [PublicCommentController::class, 'threaded']);
    Route::middleware('throttle:comments')->group(function () {
        Route::post('clips/{id}/comments', [PublicCommentController::class, 'submitClip']);
    });
});

$frontendDomain = config('admin-access.frontend_domain');
$backendDomain = config('admin-access.backend_domain');
$frontendPrefix = config('admin-access.frontend_prefix', 'frontend-admin');
$backendPrefix = config('admin-access.backend_prefix', 'backend-admin');

$registerDomainGroup = function (?string $domain, callable $callback): void {
    $registrar = $domain ? Route::domain($domain) : Route::middleware([]);
    $registrar->group($callback);
};

$registerDomainGroup($frontendDomain, function () use ($frontendPrefix) {
    Route::prefix($frontendPrefix)->group(function () {
        Route::prefix('auth')->group(function () {
            Route::post('login', [AuthController::class, 'loginFrontend']);
            Route::get('status', [AuthController::class, 'status']);
        });

        Route::middleware(['auth:sanctum', 'admin.panel:frontend'])->group(function () {
            Route::get('auth/me', [AuthController::class, 'me']);
            Route::patch('auth/profile', [AuthController::class, 'updateProfile']);
            Route::patch('auth/password', [AuthController::class, 'updatePassword']);
            Route::post('auth/logout', [AuthController::class, 'logout']);

            Route::get('authors', [AdminProfileController::class, 'authors']);
            Route::post('authors/link-orphans', [AdminProfileController::class, 'linkOrphans']);

            Route::get('content', [VideoController::class, 'index']);
            Route::post('content', [VideoController::class, 'store']);
            Route::get('content/{id}', [VideoController::class, 'show']);
            Route::patch('content/{id}', [VideoController::class, 'update']);
            Route::delete('content/{id}', [VideoController::class, 'destroy']);
            Route::post('content/{id}/duplicate', [VideoController::class, 'duplicate']);
            Route::get('content/{id}/revisions', [VideoController::class, 'revisions']);
            Route::post('content/{id}/revisions', [VideoController::class, 'saveRevision']);
            Route::post('content/bulk', [VideoController::class, 'bulk']);

            Route::get('videos', [VideoController::class, 'index']);
            Route::post('videos', [VideoController::class, 'store']);
            Route::get('videos/{id}', [VideoController::class, 'show']);
            Route::patch('videos/{id}', [VideoController::class, 'update']);
            Route::delete('videos/{id}', [VideoController::class, 'destroy']);

            Route::get('categories', [CategoryController::class, 'index']);
            Route::post('categories', [CategoryController::class, 'store']);
            Route::get('categories/{slug}', [CategoryController::class, 'show']);
            Route::patch('categories/{slug}', [CategoryController::class, 'update']);

            Route::get('tags', [TagController::class, 'index']);
            Route::post('tags', [TagController::class, 'store']);
            Route::patch('tags/{id}', [TagController::class, 'update']);
            Route::delete('tags/{id}', [TagController::class, 'destroy']);

            Route::get('comments', [CommentController::class, 'index']);
            Route::patch('comments/{id}', [CommentController::class, 'update']);
            Route::delete('comments/{id}', [CommentController::class, 'destroy']);
            Route::post('comments/bulk', [CommentController::class, 'bulkAction']);

            Route::get('media', [MediaController::class, 'index']);
            Route::post('media/upload', [MediaController::class, 'upload']);
            Route::patch('media/{id}', [MediaController::class, 'update']);
            Route::delete('media/{id}', [MediaController::class, 'destroy']);

            Route::get('submissions', [ContentSubmissionController::class, 'index']);
            Route::post('submissions', [ContentSubmissionController::class, 'store']);
            Route::patch('submissions/{id}', [ContentSubmissionController::class, 'update']);

            Route::get('analytics/my-content', [AnalyticsController::class, 'myContent']);
            Route::get('youtube/preview', [YoutubeController::class, 'preview']);

            Route::apiResource('sponsored', SponsoredContentController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::apiResource('affiliates', AffiliateLinkController::class)->only(['index', 'store', 'update', 'destroy']);

            Route::get('applications', [ContributorApplicationController::class, 'index']);
            Route::post('applications/{id}/approve', [ContributorApplicationController::class, 'approve']);
            Route::post('applications/{id}/reject', [ContributorApplicationController::class, 'reject']);

            Route::prefix('account')->group(function () {
                Route::get('notifications', [AccountController::class, 'notifications']);
                Route::post('notifications/read', [AccountController::class, 'markNotificationsRead']);
                Route::post('activity/read', [AccountController::class, 'recordActivityRead']);
                Route::get('readership/weekly', [AccountController::class, 'weeklyReadership']);
                Route::match(['get', 'put'], 'preferences/notifications', [AccountController::class, 'notificationPreferences']);
                Route::post('profile/avatar', [AccountController::class, 'updateAvatar']);
            });
        });
    });
});

$registerDomainGroup($backendDomain, function () use ($backendPrefix) {
    Route::prefix($backendPrefix)->group(function () {
        Route::prefix('auth')->group(function () {
            Route::post('login', [AuthController::class, 'loginBackend']);
        });

        Route::middleware(['auth:sanctum', 'admin.panel:backend'])->group(function () {
            Route::get('auth/me', [AuthController::class, 'me']);
            Route::patch('auth/profile', [AuthController::class, 'updateProfile']);
            Route::patch('auth/password', [AuthController::class, 'updatePassword']);
            Route::post('auth/logout', [AuthController::class, 'logout']);

            Route::get('users', [AdminProfileController::class, 'index']);
            Route::post('users', [AdminProfileController::class, 'store']);
            Route::get('users/{id}', [AdminProfileController::class, 'show']);
            Route::patch('users/{id}', [AdminProfileController::class, 'update']);
            Route::delete('users/{id}', [AdminProfileController::class, 'destroy']);
            Route::patch('users/{id}/password', [AdminProfileController::class, 'updatePassword']);
            Route::get('users/{id}/content', [AdminProfileController::class, 'content']);

            Route::get('settings', [SiteSettingController::class, 'show']);
            Route::post('settings', [SiteSettingController::class, 'upsert']);

            Route::get('analytics', [AnalyticsController::class, 'index']);
            Route::get('activity-log', [ActivityLogController::class, 'index']);
            Route::get('youtube/status', [YoutubeController::class, 'status']);
            Route::get('youtube/verify', [YoutubeController::class, 'verify']);
            Route::get('youtube/preview', [YoutubeController::class, 'preview']);
            Route::get('youtube/fetch', [YoutubeController::class, 'categoryPreview']);
            Route::post('youtube/fetch', [YoutubeController::class, 'fetch']);
            Route::get('youtube/autofetch', [YoutubeController::class, 'autofetchStatus']);
            Route::post('youtube/autofetch', [YoutubeController::class, 'autofetch']);

            Route::get('push/status', [PushNotificationController::class, 'status']);
            Route::post('push/send', [PushNotificationController::class, 'send']);

            // Social clips management
            Route::get('social-clips', [SocialClipController::class, 'index']);
            Route::get('social-clips/stats', [SocialClipController::class, 'stats']);
            Route::get('social-clips/search-videos', [SocialClipController::class, 'searchVideos']);
            Route::get('social-clips/{id}', [SocialClipController::class, 'show']);
            Route::post('social-clips/{id}/link', [SocialClipController::class, 'link']);
            Route::post('social-clips/{id}/unlink', [SocialClipController::class, 'unlink']);
            Route::post('social-clips/{id}/confirm', [SocialClipController::class, 'confirmAutoMap']);
            Route::post('social-clips/bulk', [SocialClipController::class, 'bulkAction']);

            // Facebook Reels fetch
            Route::get('social/facebook/status', [SocialClipController::class, 'facebookStatus']);
            Route::post('social/facebook/fetch', [SocialClipController::class, 'facebookFetch']);
            Route::post('social/facebook/refresh-token', [SocialClipController::class, 'facebookRefreshToken']);

            // TikTok fetch
            Route::get('social/tiktok/status', [SocialClipController::class, 'tiktokStatus']);
            Route::post('social/tiktok/fetch', [SocialClipController::class, 'tiktokFetch']);

            // YouTube Shorts extended
            Route::post('youtube/shorts/auto-link', [YoutubeController::class, 'autoLinkShorts']);
            Route::post('youtube/shorts/sync-clips', [YoutubeController::class, 'syncShortsAsClips']);
        });
    });
});
