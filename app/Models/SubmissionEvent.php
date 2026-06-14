<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubmissionEvent extends Model
{
    protected $table = 'submission_events';

    protected $fillable = [
        'submission_id', 'actor_id', 'actor_label', 'event_type', 'note',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
