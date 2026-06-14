<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasStringPrimaryKey;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, HasStringPrimaryKey, SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'target',
        'description',
        'status',
        'usage_count',
    ];

    protected $casts = [
        'usage_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}
