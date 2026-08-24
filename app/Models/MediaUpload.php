<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasStringPrimaryKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'id',
    'organization_id',
    'uploaded_by',
    'replace_media_id',
    'media_id',
    'original_name',
    'description',
    'mime_type',
    'total_size',
    'chunk_size',
    'total_chunks',
    'received_chunks',
    'uploaded_bytes',
    'status',
    'expires_at',
    'completed_at',
])]
class MediaUpload extends Model
{
    use HasFactory, HasStringPrimaryKey;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'total_size' => 'integer',
            'chunk_size' => 'integer',
            'total_chunks' => 'integer',
            'received_chunks' => 'array',
            'uploaded_bytes' => 'integer',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
