<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsletterPopupEvent;
use App\Models\NewsletterPopupTemplate;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NewsletterPopupController extends Controller
{
    public function index(): JsonResponse
    {
        $templates = NewsletterPopupTemplate::orderByDesc('created_at')->get();

        $usage  = NewsletterSubscriber::whereNotNull('popup_type')
            ->selectRaw('popup_type, count(*) as cnt')
            ->groupBy('popup_type')
            ->pluck('cnt', 'popup_type');

        $events = NewsletterPopupEvent::get(['template_key', 'category_slug', 'event_type']);
        $evMap  = [];
        foreach ($events as $e) {
            $k = $e->template_key ?? 'unknown';
            $evMap[$k] ??= ['impressions' => 0, 'closes' => 0, 'submits' => 0, 'byCategory' => []];
            if ($e->event_type === 'impression') $evMap[$k]['impressions']++;
            if ($e->event_type === 'close')      $evMap[$k]['closes']++;
            if ($e->event_type === 'submit')     $evMap[$k]['submits']++;
            $cat = $e->category_slug ?? 'global';
            $evMap[$k]['byCategory'][$cat] = ($evMap[$k]['byCategory'][$cat] ?? 0) + 1;
        }

        $result = $templates->map(fn ($t) => array_merge($t->toArray(), [
            'usageCount'   => $usage[$t->template_key] ?? 0,
            'eventSummary' => $evMap[$t->template_key] ?? ['impressions' => 0, 'closes' => 0, 'submits' => 0, 'byCategory' => []],
        ]));

        return response()->json(['templates' => $result]);
    }

    public function upsert(Request $request): JsonResponse
    {
        $data = $request->validate([
            'template_key'   => ['required', 'string'],
            'name'           => ['required', 'string'],
            'title'          => ['required', 'string'],
            'body'           => ['required', 'string'],
            'interval_hours' => ['sometimes', 'integer', 'min:1'],
            'categories'     => ['sometimes', 'array'],
            'is_active'      => ['sometimes', 'boolean'],
        ]);

        $template = NewsletterPopupTemplate::updateOrCreate(
            ['template_key' => $data['template_key']],
            array_merge($data, ['id' => $request->input('id', Str::uuid())])
        );

        return response()->json(['template' => $template]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $id = $request->query('id');
        NewsletterPopupTemplate::findOrFail($id)->delete();
        return response()->json(['success' => true]);
    }
}
