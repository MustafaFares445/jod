<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasStringPrimaryKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['id', 'group_id', 'author_id', 'body', 'status', 'is_pinned', 'likes_count', 'comments_count'])]
class GroupPost extends Model
{
    use HasFactory, HasStringPrimaryKey, SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'likes_count' => 'integer',
            'comments_count' => 'integer',
        ];
    }

    public function group(): BelongsTo { return $this->belongsTo(Group::class); }
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'author_id'); }
    public function comments(): HasMany { return $this->hasMany(GroupComment::class, 'post_id'); }
    public function likedByUsers(): BelongsToMany { return $this->belongsToMany(User::class, 'group_post_likes', 'post_id', 'user_id')->withTimestamps(); }
}
