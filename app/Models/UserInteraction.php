<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PersonalizationEventType;
use App\Models\Concerns\HasStringPrimaryKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'id', 'user_id', 'event_type', 'subject_type', 'subject_id', 'category_id',
    'publisher_type', 'publisher_id', 'metadata', 'occurred_at',
])]
class UserInteraction extends Model
{
    use HasStringPrimaryKey;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'event_type' => PersonalizationEventType::class,
            'metadata' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
