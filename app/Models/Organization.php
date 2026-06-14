<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasStringPrimaryKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name',
    'email',
    'phone',
    'organization_type',
    'registration_number',
    'establishment_date',
    'short_address',
    'description',
    'location',
    'license_document_name',
    'delegation_document_name',
    'owner_full_name',
    'owner_email',
    'owner_phone',
    'website',
    'social_media',
    'bank_name',
    'iban',
    'status',
    'verification_status',
    'accepted_at',
    'campaigns_count',
    'posts_count',
    'active_volunteers_count',
    'activity_score',
    'last_active_at',
])]
class Organization extends Model
{
    use HasFactory, HasStringPrimaryKey, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'social_media' => 'array',
            'establishment_date' => 'date',
            'accepted_at' => 'datetime',
            'last_active_at' => 'datetime',
        ];
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    public function campaignApplications(): HasMany
    {
        return $this->hasMany(CampaignApplication::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(OrganizationRole::class);
    }

    public function staff(): HasMany
    {
        return $this->hasMany(OrganizationStaff::class);
    }
}
