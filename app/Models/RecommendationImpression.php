<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FeedType;
use App\Models\Concerns\HasStringPrimaryKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['id', 'user_id', 'subject_type', 'subject_id', 'feed_type', 'category_id', 'publisher_type', 'publisher_id', 'city', 'score', 'reasons', 'shown_at'])]
class RecommendationImpression extends Model
{
    use HasStringPrimaryKey;
    public $incrementing = false;
    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['feed_type' => FeedType::class, 'score' => 'float', 'reasons' => 'array', 'shown_at' => 'datetime'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
}
