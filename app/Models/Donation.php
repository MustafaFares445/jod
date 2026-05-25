<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'organization_id', 'campaign_id', 'name', 'email', 'phone', 'campaign_title',
    'amount_or_type', 'donated_at', 'city', 'source', 'payment_method',
    'campaign_ref', 'assigned_to', 'internal_notes', 'created_by',
])]
class Donation extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'donated_at' => 'datetime',
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
}
