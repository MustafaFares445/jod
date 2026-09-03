<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasStringPrimaryKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'id', 'owner_id', 'organization_id', 'name', 'description', 'category', 'location',
    'status', 'purpose', 'rules', 'proposed_admin_ids', 'rejection_reason', 'suspension_reason',
    'submitted_at', 'reviewed_at', 'reviewed_by',
])]
class Group extends Model
{
    use HasFactory, HasStringPrimaryKey, SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'rules' => 'array',
            'proposed_admin_ids' => 'array',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_id'); }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function reviewedBy(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function memberships(): HasMany { return $this->hasMany(GroupMember::class); }
    public function activeMembers(): HasMany { return $this->memberships()->where('status', 'active'); }
    public function posts(): HasMany { return $this->hasMany(GroupPost::class); }

    public function avatarMedia(): HasOne
    {
        return $this->hasOne(Media::class, 'model_id')
            ->where('model_type', 'group')
            ->where('prop', 'avatar');
    }

    public function coverMedia(): HasOne
    {
        return $this->hasOne(Media::class, 'model_id')
            ->where('model_type', 'group')
            ->where('prop', 'cover');
    }
}
