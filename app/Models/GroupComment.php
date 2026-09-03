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

#[Fillable(['id', 'post_id', 'author_id', 'parent_id', 'body', 'status', 'likes_count'])]
class GroupComment extends Model
{
    use HasFactory, HasStringPrimaryKey, SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['likes_count' => 'integer'];
    }

    public function post(): BelongsTo { return $this->belongsTo(GroupPost::class, 'post_id'); }
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'author_id'); }
    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function replies(): HasMany { return $this->hasMany(self::class, 'parent_id')->where('status', 'published')->orderBy('created_at'); }
    public function likedByUsers(): BelongsToMany { return $this->belongsToMany(User::class, 'group_comment_likes', 'comment_id', 'user_id')->withTimestamps(); }
}
