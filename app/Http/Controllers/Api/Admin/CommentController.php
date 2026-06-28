<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Comment::query()
            ->with('content:id,title,slug,niche')
            ->orderByDesc('created_at');

        if ($request->query('is_approved') !== null) {
            $query->where('is_approved', filter_var($request->query('is_approved'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($ct = $request->query('content_type')) {
            $query->where('content_type', $ct);
        }

        if ($ms = $request->query('moderation_status')) {
            $query->where('moderation_status', $ms);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('author_name', 'like', "%{$search}%")
                  ->orWhere('body', 'like', "%{$search}%");
            });
        }

        $limit = min(200, max(1, (int) $request->query('limit', 50)));
        return response()->json($query->paginate($limit));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $comment = Comment::findOrFail($id);
        $patch   = array_filter([
            'is_approved' => isset($request->is_approved) ? (bool) $request->is_approved : null,
            'is_spam'     => isset($request->is_spam)     ? (bool) $request->is_spam     : null,
            'moderation_status' => $request->moderation_status ?? null,
        ], fn ($v) => $v !== null);
        $comment->update($patch);
        return response()->json($comment->fresh());
    }

    public function bulkAction(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['uuid', 'exists:comments,id'],
            'action' => ['required', 'in:approve,hide,flag,delete'],
        ]);

        $count = count($data['ids']);

        match ($data['action']) {
            'approve' => Comment::whereIn('id', $data['ids'])->update([
                'is_approved' => true,
                'is_spam' => false,
                'moderation_status' => 'visible',
            ]),
            'hide' => Comment::whereIn('id', $data['ids'])->update([
                'moderation_status' => 'hidden',
            ]),
            'flag' => Comment::whereIn('id', $data['ids'])->update([
                'moderation_status' => 'flagged',
            ]),
            'delete' => Comment::whereIn('id', $data['ids'])->delete(),
        };

        return response()->json(['affected' => $count]);
    }

    public function destroy(string $id): JsonResponse
    {
        Comment::findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
