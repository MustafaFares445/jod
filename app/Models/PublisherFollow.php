<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasStringPrimaryKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['id', 'follower_user_id', 'target_type', 'target_id', 'notification_level'])]
class PublisherFollow extends Model
{
    use HasFactory, HasStringPrimaryKey;

    public const TARGET_USER = 'user';
    public const TARGET_ORGANIZATION = 'organization';

    public const NOTIFICATION_ALL = 'all';
    public const NOTIFICATION_IMPORTANT = 'important';
    public const NOTIFICATION_MUTED = 'muted';

    public $incrementing = false;
    protected $keyType = 'string';

    public function follower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'follower_user_id');
    }

    public function scopeByFollower($query, string $userId)
    {
        return $query->where('follower_user_id', $userId);
    }

    public function scopeByTarget($query, string $type, string $id)
    {
        return $query->where('target_type', $type)->where('target_id', $id);
    }
}
