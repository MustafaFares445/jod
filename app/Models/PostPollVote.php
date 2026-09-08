<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasStringPrimaryKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['id', 'poll_id', 'option_id', 'user_id'])]
class PostPollVote extends Model
{
    use HasStringPrimaryKey;
    public $incrementing = false;
    protected $keyType = 'string';
    public function poll(): BelongsTo { return $this->belongsTo(PostPoll::class, 'poll_id'); }
    public function option(): BelongsTo { return $this->belongsTo(PostPollOption::class, 'option_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
