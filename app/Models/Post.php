<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HelpOfferStatus;
use App\Enums\HelpRequestStatus;
use App\Enums\PostUrgency;
use App\Models\Concerns\HasStringPrimaryKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'id', 'title', 'summary', 'content', 'type', 'audience', 'status', 'help_status', 'urgency', 'urgency_reason', 'location',
    'organization_id', 'campaign_id', 'category_id', 'author_id', 'updated_by',
    'block_reason', 'views_count', 'reactions_count', 'applications_count',
    'published_at', 'expires_at', 'submitted_at', 'reviewed_at', 'reviewed_by', 'blocked_at', 'blocked_by',
])]
class Post extends Model
{
    use HasFactory, HasStringPrimaryKey, SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';

    protected static function booted(): void
    {
        static::saving(function (Post $post): void {
            if ($post->type === 'help_request' && $post->help_status === null) {
                $post->help_status = HelpRequestStatus::Open;
            }

            if ($post->type !== 'help_request') {
                $post->help_status = null;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'help_status' => HelpRequestStatus::class,
            'urgency' => PostUrgency::class,
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'blocked_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function campaign(): BelongsTo { return $this->belongsTo(Campaign::class); }
    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'author_id'); }
    public function updatedBy(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
    public function reviewedBy(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function blockedBy(): BelongsTo { return $this->belongsTo(User::class, 'blocked_by'); }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class, 'model_id')->where('model_type', 'post')->orderBy('prop')->orderBy('position')->orderBy('id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(Media::class, 'model_id')->where('model_type', 'post')->where('prop', 'images')->orderBy('position')->orderBy('id');
    }

    public function videos(): HasMany
    {
        return $this->hasMany(Media::class, 'model_id')->where('model_type', 'post')->where('prop', 'videos')->orderBy('position')->orderBy('id');
    }

    public function likes(): HasMany { return $this->hasMany(PostLike::class); }
    public function saves(): HasMany { return $this->hasMany(SavedPost::class); }
    public function helpOffers(): HasMany { return $this->hasMany(HelpOffer::class); }

    public function activeHelpOffers(): HasMany
    {
        return $this->helpOffers()->whereIn('status', array_map(
            static fn (HelpOfferStatus $status) => $status->value,
            [HelpOfferStatus::Pending, HelpOfferStatus::Accepted, HelpOfferStatus::Contacting, HelpOfferStatus::Agreed],
        ));
    }

    public function completedHelpOffers(): HasMany
    {
        return $this->helpOffers()->where('status', HelpOfferStatus::Completed->value);
    }

    public function campaignApplications(): HasMany
    {
        return $this->hasMany(CampaignApplication::class, 'campaign_id', 'campaign_id')
            ->whereNotIn('applicant_status', ['rejected', 'withdrawn']);
    }

    public function likedByUsers(): BelongsToMany { return $this->belongsToMany(User::class, 'post_likes')->withTimestamps(); }
    public function savedByUsers(): BelongsToMany { return $this->belongsToMany(User::class, 'saved_posts')->withTimestamps(); }
}
