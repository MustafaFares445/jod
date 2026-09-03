<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasStringPrimaryKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['id', 'group_id', 'user_id', 'role', 'status', 'joined_at', 'left_at'])]
class GroupMember extends Model
{
    use HasFactory, HasStringPrimaryKey;

    public $incrementing = false;
    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    public function group(): BelongsTo { return $this->belongsTo(Group::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
