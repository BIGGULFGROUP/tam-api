<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\NewsletterPopupTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsletterPopupController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(NewsletterPopupTemplate::orderByDesc('created_at')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'template_key' => ['required', 'string', 'max:100', 'unique:newsletter_popup_templates,template_key'],
            'name' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'interval_hours' => ['nullable', 'integer', 'min:1', 'max:168'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $template = NewsletterPopupTemplate::create([
            'template_key' => $data['template_key'],
            'name' => $data['name'],
            'title' => $data['title'],
            'body' => $data['body'],
            'interval_hours' => $data['interval_hours'] ?? 24,
            'categories' => $data['categories'] ?? [],
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json($template, 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $id = $request->query('id');
        NewsletterPopupTemplate::findOrFail($id)->delete();

        return response()->json(['ok' => true]);
    }
}
