<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'user_id',
    'organization_role_id',
    'name',
    'email',
    'phone',
    'status',
    'invitation_token'
])]
class OrganizationStaff extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'invited_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(OrganizationRole::class, 'organization_role_id');
    }

    public function generateInvitationToken(): string
    {
        return $this->update(['invitation_token' => bin2hex(random_bytes(32))]) ? $this->invitation_token : '';
    }
}
