<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentSubmission extends Model
{
    use HasUuids;

    protected $table = 'content_submissions';

    protected $fillable = [
        'content_id', 'submitted_by', 'author_label', 'author_role',
        'submission_status', 'content_type', 'title', 'niche', 'tags',
        'reviewer_id', 'reviewer_label', 'review_note', 'revision_count',
        'submitted_at', 'reviewed_at', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'tags'           => 'array',
            'revision_count' => 'integer',
            'submitted_at'   => 'datetime',
            'reviewed_at'    => 'datetime',
            'published_at'   => 'datetime',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(SubmissionEvent::class, 'submission_id');
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(Video::class, 'content_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(AdminProfile::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(AdminProfile::class, 'reviewer_id');
    }
}
