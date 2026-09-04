<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Models\UserCategoryInterest;

class BehavioralInterestDecayService
{
    public function decay(): int
    {
        $factor = max(0.0, min(1.0, (float) config('recommendations.interests.decay_factor', 0.80)));
        $cleanupThreshold = max(0.0, (float) config('recommendations.interests.decay_cleanup_threshold', 0.5));
        $updated = 0;

        UserCategoryInterest::query()
            ->where('behavioral_weight', '!=', 0)
            ->where('updated_at', '<=', now()->subDays(7))
            ->orderBy('id')
            ->chunkById(200, function ($interests) use ($factor, $cleanupThreshold, &$updated): void {
                foreach ($interests as $interest) {
                    $next = round(((float) $interest->behavioral_weight) * $factor, 2);

                    if ((float) $interest->explicit_weight === 0.0 && abs($next) < $cleanupThreshold) {
                        $interest->delete();
                        $updated++;
                        continue;
                    }

                    $interest->behavioral_weight = $next;
                    $interest->save();
                    $updated++;
                }
            });

        return $updated;
    }
}
