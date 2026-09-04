<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AdminPersonalizationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::middleware(['api', 'auth:sanctum', 'access-token'])
            ->prefix('api/v1/admin')
            ->as('admin.personalization.')
            ->group(base_path('routes/admin_personalization.php'));
    }
}
