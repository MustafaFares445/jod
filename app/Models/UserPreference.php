<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AvailabilityStatus;
use App\Enums\UserIntent;
use App\Models\Concerns\HasStringPrimaryKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'id', 'user_id', 'intent', 'preferred_city', 'preferred_governorate', 'preferred_radius_km',
    'remote_help_enabled', 'availability_status', 'onboarding_completed_at',
])]
class UserPreference extends Model
{
    use HasStringPrimaryKey;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'intent' => UserIntent::class,
            'remote_help_enabled' => 'boolean',
            'availability_status' => AvailabilityStatus::class,
            'onboarding_completed_at' => 'datetime',
            'preferred_radius_km' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
