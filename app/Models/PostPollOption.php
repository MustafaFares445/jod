<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasStringPrimaryKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['id', 'poll_id', 'label', 'position', 'votes_count'])]
class PostPollOption extends Model
{
    use HasStringPrimaryKey;
    public $incrementing = false;
    protected $keyType = 'string';
    protected function casts(): array { return ['position' => 'integer', 'votes_count' => 'integer']; }
    public function poll(): BelongsTo { return $this->belongsTo(PostPoll::class, 'poll_id'); }
    public function votes(): HasMany { return $this->hasMany(PostPollVote::class, 'option_id'); }
}
