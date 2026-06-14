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
        $limit = min(200, max(1, (int) $request->query('limit', 50)));
        return response()->json($query->paginate($limit));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $comment = Comment::findOrFail($id);
        $patch   = array_filter([
            'is_approved' => isset($request->is_approved) ? (bool) $request->is_approved : null,
            'is_spam'     => isset($request->is_spam)     ? (bool) $request->is_spam     : null,
        ], fn ($v) => $v !== null);
        $comment->update($patch);
        return response()->json($comment->fresh());
    }

    public function destroy(string $id): JsonResponse
    {
        Comment::findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
