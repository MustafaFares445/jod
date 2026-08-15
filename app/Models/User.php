<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasStringPrimaryKey;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['id', 'name', 'email', 'password', 'phone', 'status', 'user_type', 'organization_id', 'last_active_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, HasStringPrimaryKey, Notifiable;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_active_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function organizationStaffMembership(): HasOne
    {
        return $this->hasOne(OrganizationStaff::class);
    }

    public function activeOrganizationStaffMembership(): HasOne
    {
        return $this->organizationStaffMembership()
            ->where('organization_id', $this->organization_id)
            ->where('status', 'active');
    }

    public function organizationDashboardRole(): ?string
    {
        if ($this->user_type === 'admin') {
            return 'admin';
        }

        if ($this->organization_id === null) {
            return null;
        }

        $membership = $this->activeOrganizationStaffMembership()
            ->with('role')
            ->first();

        if ($membership === null || $membership->role === null || ! $membership->role->is_active) {
            return null;
        }

        return $membership->isOwner() ? 'org_owner' : 'org_staff';
    }

    public function isOrganizationOwner(): bool
    {
        return $this->organizationDashboardRole() === 'org_owner';
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'reporter_id');
    }

    public function likedPosts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_likes')->withTimestamps();
    }

    public function savedPosts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'saved_posts')->withTimestamps();
    }

    public function assignedReports(): HasMany
    {
        return $this->hasMany(Report::class, 'assignee_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'recipient_id');
    }

    public function createdNotifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'created_by');
    }

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class, 'created_by');
    }

    public function campaignApplications(): HasMany
    {
        return $this->hasMany(CampaignApplication::class, 'created_by');
    }
}
