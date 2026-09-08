<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasStringPrimaryKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['id', 'group_id', 'category'])]
class GroupCategory extends Model
{
    use HasStringPrimaryKey;

    public $incrementing = false;
    protected $keyType = 'string';

    public function group(): BelongsTo { return $this->belongsTo(Group::class); }
}
