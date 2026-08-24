<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MediaModel;
use App\Models\Concerns\HasStringPrimaryKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'id',
    'model_type',
    'model_id',
    'post_id',
    'prop',
    'disk',
    'path',
    'original_name',
    'description',
    'mime_type',
    'size',
    'position',
])]
class Media extends Model
{
    use HasFactory, HasStringPrimaryKey;

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function booted(): void
    {
        static::creating(function (Media $media): void {
            $type = $media->model_type instanceof MediaModel ? $media->model_type->value : (string) $media->model_type;
            if ($type === MediaModel::POST->value && ! $media->post_id) {
                $media->post_id = $media->model_id;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'model_type' => MediaModel::class,
            'size' => 'integer',
            'position' => 'integer',
        ];
    }

    public function publicUrl(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}
