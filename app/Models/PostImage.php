<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasStringPrimaryKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @deprecated Use Media. Kept as a compatibility adapter for existing mobile code/tests.
 */
#[Fillable([
    'id',
    'post_id',
    'model_id',
    'model_type',
    'prop',
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

    protected $table = 'media';

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function booted(): void
    {
        static::addGlobalScope('post_images', static function (Builder $builder): void {
            $builder->where('model_type', 'post')->where('prop', 'images');
        });

        static::creating(function (PostImage $image): void {
            $image->model_type = 'post';
            $image->prop = 'images';
            $image->model_id = $image->model_id ?: $image->post_id;
            $image->post_id = $image->post_id ?: $image->model_id;
        });
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'post_id');
    }

    public function publicUrl(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}
