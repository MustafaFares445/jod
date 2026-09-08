<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasStringPrimaryKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['id', 'group_id', 'invited_user_id', 'invited_by', 'status', 'responded_at'])]
class GroupInvitation extends Model
{
    use HasStringPrimaryKey;

    public $incrementing = false;
    protected $keyType = 'string';

    protected function casts(): array { return ['responded_at' => 'datetime']; }

    public function group(): BelongsTo { return $this->belongsTo(Group::class); }
    public function invitedUser(): BelongsTo { return $this->belongsTo(User::class, 'invited_user_id'); }
    public function inviter(): BelongsTo { return $this->belongsTo(User::class, 'invited_by'); }
}
