<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CommentController extends Controller
{
    /**
     * Get threaded comments for a content item.
     */
    public function threaded(Request $request): JsonResponse
    {
        $data = $request->validate([
            'content_id' => ['required', 'uuid', 'exists:videos,id'],
        ]);

        $comments = DB::table('comments')
            ->where('content_id', $data['content_id'])
            ->where('is_approved', true)
            ->orderBy('upvotes', 'desc')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'parent_id' => $c->parent_id,
                'author_name' => $c->author_name,
                'body' => $c->body,
                'upvotes' => $c->upvotes,
                'created_at' => $c->created_at,
            ]);

        // Build threaded structure
        $threaded = [];
        $children = [];

        foreach ($comments as $comment) {
            if (!$comment['parent_id']) {
                $threaded[] = $comment;
            } else {
                $children[$comment['parent_id']][] = $comment;
            }
        }

        // Attach children to parents
        foreach ($threaded as &$parent) {
            $parent['replies'] = $children[$parent['id']] ?? [];
        }

        return response()->json(['comments' => $threaded, 'total' => count($comments)]);
    }

    /**
     * Submit a comment with optional parent for replies.
     */
    public function submit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'content_id' => ['required', 'uuid', 'exists:videos,id'],
            'author_name' => ['required', 'string', 'max:120'],
            'author_email' => ['nullable', 'email', 'max:255'],
            'body' => ['required', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'uuid', 'exists:comments,id'],
        ]);

        $id = Str::uuid();

        DB::table('comments')->insert([
            'id' => $id,
            'content_id' => $data['content_id'],
            'parent_id' => $data['parent_id'] ?? null,
            'author_name' => $data['author_name'],
            'author_email' => $data['author_email'] ?? null,
            'body' => strip_tags($data['body']),
            'is_approved' => true,
            'is_spam' => false,
            'ip_address' => $request->ip(),
            'upvotes' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Notify parent comment author about reply
        if (!empty($data['parent_id'])) {
            $parent = DB::table('comments')->find($data['parent_id']);
            if ($parent && $parent->author_email) {
                // Queue notification — handled by Brevo notification
                DB::table('user_notifications')->insert([
                    'id' => Str::uuid(),
                    'user_id' => $parent->author_email,
                    'type' => 'comment_reply',
                    'message' => "{$data['author_name']} replied to your comment",
                    'link' => "/article?comment={$id}",
                    'is_read' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'comment' => [
                'id' => $id,
                'parent_id' => $data['parent_id'] ?? null,
                'author_name' => $data['author_name'],
                'body' => strip_tags($data['body']),
                'upvotes' => 0,
                'created_at' => now()->toISOString(),
            ],
        ], 201);
    }

    /**
     * Upvote a comment.
     */
    public function upvote(Request $request, string $id): JsonResponse
    {
        $ip = $request->ip();

        $exists = DB::table('comment_upvotes')
            ->where('comment_id', $id)
            ->where('voter_ip', $ip)
            ->exists();

        if ($exists) {
            DB::table('comment_upvotes')
                ->where('comment_id', $id)
                ->where('voter_ip', $ip)
                ->delete();
            DB::table('comments')->where('id', $id)->decrement('upvotes');
            return response()->json(['upvoted' => false, 'upvotes' => DB::table('comments')->where('id', $id)->value('upvotes')]);
        }

        DB::table('comment_upvotes')->insert([
            'id' => Str::uuid(),
            'comment_id' => $id,
            'voter_ip' => $ip,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('comments')->where('id', $id)->increment('upvotes');

        return response()->json(['upvoted' => true, 'upvotes' => DB::table('comments')->where('id', $id)->value('upvotes')]);
    }
}
