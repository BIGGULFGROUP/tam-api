<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Admin\AuthController;
use App\Http\Controllers\Api\Admin\AdminProfileController;
use App\Http\Controllers\Api\Admin\VideoController;
use App\Http\Controllers\Api\Admin\CategoryController;
use App\Http\Controllers\Api\Admin\TagController;
use App\Http\Controllers\Api\Admin\CommentController;
use App\Http\Controllers\Api\Admin\MediaController;
use App\Http\Controllers\Api\Admin\NewsletterSubscriberController;
use App\Http\Controllers\Api\Admin\NewsletterCampaignController;
use App\Http\Controllers\Api\Admin\NewsletterPopupController;
use App\Http\Controllers\Api\Admin\ContentSubmissionController;
use App\Http\Controllers\Api\Admin\SiteSettingController;
use App\Http\Controllers\Api\Admin\AnalyticsController;
use App\Http\Controllers\Api\Admin\ActivityLogController;
use App\Http\Controllers\Api\Admin\YoutubeController;
use App\Http\Controllers\Api\PublicSiteController;

Route::prefix('public')->group(function () {
    Route::get('content/latest', [PublicSiteController::class, 'latestContent']);
    Route::get('content/nav-menu', [PublicSiteController::class, 'navMenu']);
    Route::get('content/shorts', [PublicSiteController::class, 'shorts']);
    Route::post('content/view', [PublicSiteController::class, 'recordView']);
    Route::get('site/settings', [PublicSiteController::class, 'siteSettings']);
    Route::get('comments', [PublicSiteController::class, 'comments']);
    Route::post('comments', [PublicSiteController::class, 'submitComment']);
    Route::post('newsletter', [PublicSiteController::class, 'subscribeNewsletter']);
    Route::get('newsletter/popup-config', [PublicSiteController::class, 'popupConfig']);
    Route::post('newsletter/popup-event', [PublicSiteController::class, 'popupEvent']);
    Route::post('newsletter/banner-event', [PublicSiteController::class, 'popupEvent']);
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

            Route::get('media', [MediaController::class, 'index']);
            Route::post('media/upload', [MediaController::class, 'upload']);
            Route::patch('media/{id}', [MediaController::class, 'update']);
            Route::delete('media/{id}', [MediaController::class, 'destroy']);

            Route::get('submissions', [ContentSubmissionController::class, 'index']);
            Route::post('submissions', [ContentSubmissionController::class, 'store']);
            Route::patch('submissions/{id}', [ContentSubmissionController::class, 'update']);

            Route::get('analytics/my-content', [AnalyticsController::class, 'myContent']);
            Route::get('youtube/preview', [YoutubeController::class, 'preview']);
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

            Route::get('subscribers', [NewsletterSubscriberController::class, 'index']);
            Route::get('subscribers/export', [NewsletterSubscriberController::class, 'export']);

            Route::get('newsletters', [NewsletterCampaignController::class, 'index']);
            Route::post('newsletters', [NewsletterCampaignController::class, 'store']);
            Route::patch('newsletters', [NewsletterCampaignController::class, 'update']);
            Route::delete('newsletters', [NewsletterCampaignController::class, 'destroy']);

            Route::get('newsletter/popups', [NewsletterPopupController::class, 'index']);
            Route::post('newsletter/popups', [NewsletterPopupController::class, 'upsert']);
            Route::delete('newsletter/popups', [NewsletterPopupController::class, 'destroy']);

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
        });
    });
});
