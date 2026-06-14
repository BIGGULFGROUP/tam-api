<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    use HasUuids;

    protected $table = 'media';

    protected $fillable = [
        'file_name', 'storage_path', 'public_url', 'mime_type',
        'size_bytes', 'width', 'height', 'alt_text', 'caption', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'width'      => 'integer',
            'height'     => 'integer',
        ];
    }
}
