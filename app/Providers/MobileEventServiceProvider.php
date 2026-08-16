<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\CampaignApplication;
use App\Models\Post;
use App\Observers\CampaignApplicationObserver;
use App\Observers\PostObserver;
use Illuminate\Support\ServiceProvider;

class MobileEventServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        CampaignApplication::observe(CampaignApplicationObserver::class);
        Post::observe(PostObserver::class);
    }
}
