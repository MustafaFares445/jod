<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasStringPrimaryKey;
use Cviebrock\EloquentSluggable\Sluggable;
use Database\Factories\ArticleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Article extends Model
{
    /** @use HasFactory<ArticleFactory> */
    use HasFactory, HasStringPrimaryKey, Sluggable;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'title', 'excerpt', 'content', 'status', 'published_at', 'author_name', 'author_id'];

    protected $casts = [
        'status' => 'string',
        'published_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'title',
                'onUpdate' => true,
            ],
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class, 'model_id')
            ->where('model_type', 'article')
            ->orderBy('prop')
            ->orderBy('position')
            ->orderBy('id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(Media::class, 'model_id')
            ->where('model_type', 'article')
            ->where('prop', 'images')
            ->orderBy('position')
            ->orderBy('id');
    }

    public function videos(): HasMany
    {
        return $this->hasMany(Media::class, 'model_id')
            ->where('model_type', 'article')
            ->where('prop', 'videos')
            ->orderBy('position')
            ->orderBy('id');
    }
}
