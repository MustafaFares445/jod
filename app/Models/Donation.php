<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DonationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'organization_id', 'campaign_id', 'name', 'email', 'phone', 'campaign_title',
    'amount_or_type', 'donated_at', 'city', 'source', 'payment_method', 'status',
    'contact_method', 'notes', 'cancel_reason', 'contacted_at', 'agreed_at',
    'completed_at', 'cancelled_at', 'campaign_ref', 'assigned_to', 'internal_notes',
    'created_by', 'confirmed_by', 'is_anonymous',
])]
class Donation extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'status' => DonationStatus::class,
            'donated_at' => 'datetime',
            'contacted_at' => 'datetime',
            'agreed_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'is_anonymous' => 'boolean',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
