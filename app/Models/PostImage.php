<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasStringPrimaryKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'id',
    'post_id',
    'disk',
    'path',
    'original_name',
    'mime_type',
    'size',
    'position',
])]
class PostImage extends Model
{
    use HasFactory, HasStringPrimaryKey;

    public $incrementing = false;

    protected $keyType = 'string';

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function publicUrl(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}
