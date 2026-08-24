<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HelpOfferStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'post_id', 'helper_user_id', 'post_owner_id', 'type', 'amount', 'description',
    'status', 'contact_method', 'phone', 'cancel_reason', 'rejection_reason',
    'accepted_at', 'contacted_at', 'agreed_at', 'helper_confirmed_at',
    'receiver_confirmed_at', 'completed_at', 'cancelled_at', 'rejected_at',
])]
class HelpOffer extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => HelpOfferStatus::class,
            'amount' => 'decimal:2',
            'accepted_at' => 'datetime',
            'contacted_at' => 'datetime',
            'agreed_at' => 'datetime',
            'helper_confirmed_at' => 'datetime',
            'receiver_confirmed_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function helper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'helper_user_id');
    }

    public function postOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'post_owner_id');
    }
}
