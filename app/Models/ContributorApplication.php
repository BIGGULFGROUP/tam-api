<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContributorApplication extends Model
{
    use HasUuids;

    protected $table = 'contributor_applications';

    protected $fillable = [
        'email',
        'full_name',
        'bio',
        'portfolio_url',
        'expertise_areas',
        'content_types',
        'motivation',
        'status',
        'reviewer_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'expertise_areas' => 'array',
            'content_types' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(AdminProfile::class, 'reviewed_by');
    }
}
