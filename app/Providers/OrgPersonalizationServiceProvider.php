<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class OrgPersonalizationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['api', 'auth:sanctum', 'access-token', 'org-active'])
            ->prefix('api/v1/org')
            ->group(base_path('routes/org_personalization.php'));
    }
}
