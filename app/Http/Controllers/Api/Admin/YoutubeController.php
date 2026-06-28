<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminProfile;
use App\Support\YoutubeAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class YoutubeController extends Controller
{
    public function __construct(
        private readonly YoutubeAdminService $youtube,
    ) {
    }

    public function status(Request $request): JsonResponse
    {
        $this->assertCanConfigure($request);

        $status = $this->youtube->getApiKeyStatus();

        return response()->json([
            'hasApiKey' => (bool) $status['key'],
            'source' => $status['source'],
            'metadataMode' => $status['key'] ? 'full' : 'basic_fallback',
            'descriptionSupport' => (bool) $status['key'],
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        $this->assertCanConfigure($request);

        $result = $this->youtube->verifyChannel((string) $request->query('channelId', ''));

        return response()->json(
            $result,
            (int) ($result['status'] ?? 200)
        );
    }

    public function preview(Request $request): JsonResponse
    {
        $result = $this->youtube->preview((string) $request->query('id', ''));

        return response()->json(
            $result,
            (int) ($result['status'] ?? 200)
        );
    }

    public function categoryPreview(Request $request): JsonResponse
    {
        $this->assertCanConfigure($request);

        $result = $this->youtube->fetchPreviewForCategory((string) $request->query('category', ''));

        return response()->json(
            $result,
            (int) ($result['status'] ?? 200)
        );
    }

    public function fetch(Request $request): JsonResponse
    {
        $this->assertCanConfigure($request);

        $data = $request->validate([
            'categorySlug' => ['required', 'string'],
            'triggeredByAdmin' => ['sometimes', 'nullable', 'string', 'uuid'],
            'selectedAuthorId' => ['sometimes', 'nullable', 'string', 'uuid'],
            'maxResults' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $result = $this->youtube->fetchCategoryVideos(
            $data['categorySlug'],
            (int) ($data['maxResults'] ?? 10),
            $data['selectedAuthorId'] ?? null,
            $data['triggeredByAdmin'] ?? optional($request->user())->id,
            'manual'
        );

        return response()->json(
            $result,
            (int) ($result['status'] ?? 200)
        );
    }

    public function autofetchStatus(Request $request): JsonResponse
    {
        if (! $this->isCronRequest($request)) {
            $this->assertCanConfigure($request);
        }

        return response()->json($this->youtube->getAutofetchStatus());
    }

    public function autofetch(Request $request): JsonResponse
    {
        $isCron = $this->isCronRequest($request);
        if (! $isCron) {
            $this->assertCanConfigure($request);
        }

        $data = $request->validate([
            'categorySlug' => ['sometimes', 'nullable', 'string'],
            'maxResults' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:50'],
            'adminId' => ['sometimes', 'nullable', 'string', 'uuid'],
        ]);

        $result = $this->youtube->runAutofetch(
            $data['categorySlug'] ?? null,
            (int) ($data['maxResults'] ?? 5),
            $data['adminId'] ?? optional($request->user())->id,
            $isCron ? 'auto' : 'manual'
        );

        return response()->json($result);
    }

    private function assertCanConfigure(Request $request): void
    {
        /** @var AdminProfile|null $admin */
        $admin = $request->user();

        abort_unless($admin && $admin->hasPermission('manage_settings'), 403, 'Forbidden');
    }

    private function isCronRequest(Request $request): bool
    {
        $secret = (string) env('CRON_SECRET', '');

        return $secret !== '' && hash_equals($secret, (string) $request->header('x-cron-secret', ''));
    }

    public function autoLinkShorts(Request $request): JsonResponse
    {
        $this->assertCanConfigure($request);
        $result = $this->youtube->autoLinkShorts();
        return response()->json($result);
    }

    public function syncShortsAsClips(Request $request): JsonResponse
    {
        $this->assertCanConfigure($request);
        $maxResults = (int) $request->input('max_results', 20);
        $result = $this->youtube->fetchShortsAsClips(min($maxResults, 50));
        return response()->json($result, (int) ($result['status'] ?? 200));
    }
}
