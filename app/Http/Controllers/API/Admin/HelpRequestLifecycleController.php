<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\Admin;

use App\Enums\HelpRequestStatus;
use App\Enums\PermissionAction;
use App\Enums\PermissionGroup;
use App\Enums\PostUrgency;
use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Support\Permissions\PermissionNameResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HelpRequestLifecycleController extends Controller
{
    public function __invoke(Request $request, Post $post): PostResource|JsonResponse
    {
        abort_unless($request->user()?->can(PermissionNameResolver::resolve(PermissionGroup::POST_REVIEW, PermissionAction::APPROVE)), 403);

        if ($post->type !== 'help_request') {
            return response()->json(['message' => 'Lifecycle controls are available only for help requests.'], 409);
        }

        $data = $request->validate([
            'urgency' => ['sometimes', Rule::enum(PostUrgency::class)],
            'urgencyReason' => ['nullable', 'string', 'max:500'],
            'expiresAt' => ['nullable', 'date'],
            'fulfillmentStatus' => ['sometimes', Rule::enum(HelpRequestStatus::class)],
        ]);

        $nextUrgency = $data['urgency'] ?? ($post->urgency?->value ?? PostUrgency::Normal->value);
        if (in_array($nextUrgency, [PostUrgency::Urgent->value, PostUrgency::Critical->value], true)
            && blank($data['urgencyReason'] ?? $post->urgency_reason)) {
            return response()->json(['message' => 'Urgency reason is required for urgent and critical requests.'], 422);
        }

        $nextStatus = $data['fulfillmentStatus'] ?? ($post->help_status?->value ?? HelpRequestStatus::Open->value);
        $nextExpiration = array_key_exists('expiresAt', $data) ? $data['expiresAt'] : $post->expires_at;
        if (in_array($nextStatus, [HelpRequestStatus::Open->value, HelpRequestStatus::InProgress->value], true)
            && $nextExpiration !== null
            && now()->greaterThanOrEqualTo($nextExpiration)) {
            return response()->json(['message' => 'Cannot keep an expired request active without a new future expiration.'], 409);
        }

        $updates = ['updated_by' => (string) $request->user()->id];
        if (array_key_exists('urgency', $data)) $updates['urgency'] = $data['urgency'];
        if (array_key_exists('urgencyReason', $data)) $updates['urgency_reason'] = $data['urgencyReason'];
        if (array_key_exists('expiresAt', $data)) $updates['expires_at'] = $data['expiresAt'];
        if (array_key_exists('fulfillmentStatus', $data)) $updates['help_status'] = $data['fulfillmentStatus'];
        $post->update($updates);

        return PostResource::make($post->refresh()->load(['organization', 'campaign', 'category', 'images', 'videos', 'author', 'updatedBy', 'reviewedBy', 'blockedBy']));
    }
}
