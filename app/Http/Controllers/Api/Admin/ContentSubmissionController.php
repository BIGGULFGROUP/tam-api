<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentSubmission;
use App\Models\SubmissionEvent;
use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContentSubmissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $contentTypes = collect($request->query('content_types', []));
        if ($contentTypes->isEmpty() && $request->filled('content_type')) {
            $contentTypes = collect([$request->query('content_type')]);
        }

        if ($contentTypes->count() === 1 && is_string($contentTypes->first()) && str_contains($contentTypes->first(), ',')) {
            $contentTypes = collect(explode(',', $contentTypes->first()));
        }

        $contentTypes = $contentTypes
            ->filter(fn ($value) => is_string($value) && trim($value) !== '')
            ->map(fn (string $value) => trim($value))
            ->values();

        $query = ContentSubmission::query()
            ->with(['content:id,title,slug,status,content_type', 'events'])
            ->orderByDesc('submitted_at');

        if ($status = $request->query('status')) {
            $query->where('submission_status', $status);
        }
        if ($contentTypes->count() === 1) {
            $query->where('content_type', $contentTypes->first());
        } elseif ($contentTypes->isNotEmpty()) {
            $query->whereIn('content_type', $contentTypes->all());
        }

        $countQuery = ContentSubmission::query();
        if ($contentTypes->count() === 1) {
            $countQuery->where('content_type', $contentTypes->first());
        } elseif ($contentTypes->isNotEmpty()) {
            $countQuery->whereIn('content_type', $contentTypes->all());
        }

        $countsByStatus = $countQuery
            ->selectRaw('submission_status, count(*) as count')
            ->groupBy('submission_status')
            ->pluck('count', 'submission_status');

        $perPage = max(1, min((int) $request->integer('per_page', 50), 100));
        $result = $query->paginate($perPage);

        return response()->json(array_merge($result->toArray(), [
            'counts_by_status' => $countsByStatus,
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'contentId' => ['required', 'uuid'],
        ]);

        $contentId = $payload['contentId'];
        $content   = Video::find($contentId);
        if (! $content) {
            return response()->json(['error' => 'Content not found.'], 404);
        }

        $profile = $request->user();
        $now     = now();

        $existing = ContentSubmission::where('content_id', $contentId)->first();

        $payload = [
            'content_id'        => $content->id,
            'submitted_by'      => $profile->id,
            'author_label'      => $profile->display_name ?? $profile->email,
            'author_role'       => $profile->role,
            'submission_status' => 'submitted',
            'content_type'      => $content->content_type,
            'title'             => $content->title,
            'niche'             => $content->niche,
            'tags'              => $content->tags ?? [],
            'submitted_at'      => $now,
        ];

        $submissionId = DB::transaction(function () use ($existing, $payload, $contentId, $profile) {
            if ($existing) {
                $existing->update(array_merge($payload, ['revision_count' => ($existing->revision_count ?? 0) + 1]));
                $submissionId = $existing->id;
            } else {
                $submission   = ContentSubmission::create(array_merge($payload, ['id' => Str::uuid(), 'revision_count' => 0]));
                $submissionId = $submission->id;
            }

            Video::where('id', $contentId)->update(['status' => 'pending_review', 'published_at' => null]);

            SubmissionEvent::create([
                'submission_id' => $submissionId,
                'actor_id'      => $profile->id,
                'actor_label'   => $profile->display_name ?? $profile->email,
                'event_type'    => $existing ? 'resubmitted' : 'submitted',
            ]);

            return $submissionId;
        });

        return response()->json(['ok' => true, 'submissionId' => $submissionId]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $submission = ContentSubmission::findOrFail($id);
        $action     = $request->input('action', '');
        $note       = trim($request->input('note', ''));

        $statusMap = [
            'start_review'     => 'in_review',
            'approve'          => 'approved',
            'reject'           => 'rejected',
            'request_revision' => 'revision_requested',
            'publish'          => 'published',
        ];
        $eventTypeMap = [
            'start_review'     => 'review_started',
            'approve'          => 'approved',
            'reject'           => 'rejected',
            'request_revision' => 'revision_requested',
            'publish'          => 'published',
        ];

        if (! isset($statusMap[$action])) {
            return response()->json(['error' => 'Invalid action.'], 400);
        }

        $profile = $request->user();
        $newStatus = $statusMap[$action];

        DB::transaction(function () use ($action, $eventTypeMap, $id, $newStatus, $note, $profile, $submission) {
            $reviewedAt = $action === 'start_review' ? null : now();
            $publishedAt = $action === 'publish' ? now() : $submission->published_at;

            $submission->update([
                'submission_status' => $newStatus,
                'reviewer_id'       => $profile->id,
                'reviewer_label'    => $profile->display_name ?? $profile->email,
                'review_note'       => $note ?: null,
                'reviewed_at'       => $reviewedAt,
                'published_at'      => $publishedAt,
            ]);

            if ($action === 'publish' && $submission->content_id) {
                Video::where('id', $submission->content_id)->update(['status' => 'published', 'published_at' => $publishedAt]);
            }

            if (in_array($action, ['reject', 'request_revision'], true) && $submission->content_id) {
                Video::where('id', $submission->content_id)->update(['status' => 'draft', 'published_at' => null]);
            }

            if ($action === 'approve' && $submission->content_id) {
                Video::where('id', $submission->content_id)->update(['status' => 'approved']);
            }

            if ($action === 'start_review' && $submission->content_id) {
                Video::where('id', $submission->content_id)->update(['status' => 'in_review']);
            }

            SubmissionEvent::create([
                'submission_id' => $id,
                'actor_id'      => $profile->id,
                'actor_label'   => $profile->display_name ?? $profile->email,
                'event_type'    => $eventTypeMap[$action],
                'note'          => $note ?: null,
            ]);
        });

        return response()->json(['ok' => true, 'newStatus' => $newStatus]);
    }
}
