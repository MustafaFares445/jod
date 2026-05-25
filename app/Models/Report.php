<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'title', 'description', 'status', 'severity', 'entity_type', 'entity_id',
    'organization_id', 'reporter_id', 'assignee_id', 'reporter_name',
    'evidence', 'timeline', 'closed_at'
])]
class Report extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'evidence' => 'array',
            'timeline' => 'array',
            'closed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }
}

