<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasStringPrimaryKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['id', 'category_id', 'keyword'])]
class CategoryKeyword extends Model
{
    use HasStringPrimaryKey;

    public $incrementing = false;
    protected $keyType = 'string';

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
