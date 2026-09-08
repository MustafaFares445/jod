<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasStringPrimaryKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['id', 'post_id', 'question', 'allows_multiple_choices', 'ends_at'])]
class PostPoll extends Model
{
    use HasStringPrimaryKey;
    public $incrementing = false;
    protected $keyType = 'string';
    protected function casts(): array { return ['allows_multiple_choices' => 'boolean', 'ends_at' => 'datetime']; }
    public function post(): BelongsTo { return $this->belongsTo(Post::class); }
    public function options(): HasMany { return $this->hasMany(PostPollOption::class, 'poll_id')->orderBy('position'); }
    public function votes(): HasMany { return $this->hasMany(PostPollVote::class, 'poll_id'); }
}
