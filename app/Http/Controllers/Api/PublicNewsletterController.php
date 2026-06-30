<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterPopupEvent;
use App\Models\NewsletterPopupTemplate;
use App\Models\NewsletterSubscriber;
use App\Rules\RecaptchaV3;
use App\Services\BrevoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicNewsletterController extends Controller
{
    public function subscribe(Request $request, BrevoService $brevo): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['string', 'exists:categories,slug'],
            'source' => ['nullable', 'string', 'max:100'],
            'recaptcha_token' => ['nullable', new RecaptchaV3()],
            'popup_type' => ['nullable', 'string', 'max:100'],
        ]);

        $subscriber = NewsletterSubscriber::updateOrCreate(
            ['email' => $data['email']],
            [
                'niches' => $data['categories'] ?? [],
                'source' => $data['source'] ?? 'unknown',
                'popup_type' => $data['popup_type'] ?? null,
                'is_active' => true,
                'subscribed_at' => now(),
            ]
        );

        $brevo->syncContact($subscriber->email, [
            'NICHES' => implode(',', $data['categories'] ?? []),
        ]);

        return response()->json(['ok' => true, 'subscriber' => $subscriber]);
    }

    public function activeCampaigns(): JsonResponse
    {
        return response()->json(
            NewsletterCampaign::where('is_active', true)->orderByDesc('created_at')->get()
        );
    }

    public function popupConfig(Request $request): JsonResponse
    {
        $template = $request->query('template')
            ? NewsletterPopupTemplate::where('template_key', $request->query('template'))->where('is_active', true)->first()
            : NewsletterPopupTemplate::where('is_active', true)->orderByDesc('created_at')->first();

        return response()->json($template);
    }

    public function popupEvent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'eventType' => ['required', 'in:impression,close,submit,click'],
            'templateKey' => ['nullable', 'string', 'max:100'],
            'categorySlug' => ['nullable', 'string', 'max:100'],
            'sessionId' => ['nullable', 'string', 'max:100'],
        ]);

        NewsletterPopupEvent::create([
            'event_type' => $data['eventType'],
            'template_key' => $data['templateKey'] ?? null,
            'category_slug' => $data['categorySlug'] ?? null,
            'session_id' => $data['sessionId'] ?? null,
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Authenticated users manage their newsletter subscription via their
     * account email — newsletter_subscribers has no user_id column, it's
     * purely email-keyed, same as the guest signup flow.
     */
    public function preferences(Request $request): JsonResponse
    {
        $email = $request->user()?->email;
        if (!$email) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if ($request->isMethod('get')) {
            $subscriber = NewsletterSubscriber::where('email', $email)->first();
            return response()->json([
                'subscribed' => (bool) ($subscriber?->is_active),
                'categories' => $subscriber?->niches ?? [],
            ]);
        }

        $data = $request->validate([
            'categories' => ['nullable', 'array'],
            'categories.*' => ['string', 'exists:categories,slug'],
            'isActive' => ['required', 'boolean'],
        ]);

        $subscriber = NewsletterSubscriber::updateOrCreate(
            ['email' => $email],
            [
                'niches' => $data['categories'] ?? [],
                'source' => 'account_settings',
                'is_active' => $data['isActive'],
                'subscribed_at' => now(),
            ]
        );

        return response()->json(['ok' => true, 'subscriber' => $subscriber]);
    }
}
