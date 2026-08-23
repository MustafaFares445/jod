<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasStringPrimaryKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'id',
    'title',
    'summary',
    'content',
    'type',
    'status',
    'location',
    'organization_id',
    'campaign_id',
    'category_id',
    'author_id',
    'rejection_reason',
    'views_count',
    'reactions_count',
    'applications_count',
    'published_at',
    'reviewed_at',
    'reviewed_by',
])]
class Post extends Model
{
    use HasFactory, HasStringPrimaryKey, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class, 'model_id')
            ->where('model_type', 'post')
            ->orderBy('prop')
            ->orderBy('position')
            ->orderBy('id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(Media::class, 'model_id')
            ->where('model_type', 'post')
            ->where('prop', 'images')
            ->orderBy('position')
            ->orderBy('id');
    }

    public function likes(): HasMany
    {
        return $this->hasMany(PostLike::class);
    }

    public function saves(): HasMany
    {
        return $this->hasMany(SavedPost::class);
    }

    public function campaignApplications(): HasMany
    {
        return $this->hasMany(CampaignApplication::class, 'campaign_id', 'campaign_id')
            ->whereNotIn('applicant_status', ['rejected', 'withdrawn']);
    }

    public function likedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'post_likes')->withTimestamps();
    }

    public function savedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'saved_posts')->withTimestamps();
    }
}
