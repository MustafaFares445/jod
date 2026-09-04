<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Models\Category;
use App\Models\User;
use App\Models\UserCategoryInterest;
use Illuminate\Support\Facades\DB;

class UserInterestService
{
    public function adjustBehavioralWeight(User $user, Category $category, float $delta): UserCategoryInterest
    {
        return DB::transaction(function () use ($user, $category, $delta): UserCategoryInterest {
            $interest = UserCategoryInterest::query()
                ->where('user_id', $user->id)
                ->where('category_id', $category->id)
                ->lockForUpdate()
                ->first();

            if ($interest === null) {
                $interest = new UserCategoryInterest([
                    'user_id' => $user->id,
                    'category_id' => $category->id,
                    'explicit_weight' => 0,
                    'behavioral_weight' => 0,
                ]);
            }

            $minimum = (float) config('recommendations.interests.behavioral_min', -50);
            $maximum = (float) config('recommendations.interests.behavioral_max', 100);
            $current = (float) ($interest->behavioral_weight ?? 0);
            $interest->behavioral_weight = max($minimum, min($maximum, $current + $delta));
            $interest->last_interaction_at = now();
            $interest->save();

            return $interest->refresh();
        });
    }
}
