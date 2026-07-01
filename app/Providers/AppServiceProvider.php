<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureApiDocumentation();
    }

    private function configureApiDocumentation(): void
    {
        Gate::define('viewApiDocs', static function (?User $user = null): bool {
            return app()->environment('local') || (bool) config('scramble.allow_production_docs', false);
        });

        if (! class_exists(\Dedoc\Scramble\Scramble::class)) {
            return;
        }

        \Dedoc\Scramble\Scramble::configure()
            ->routes(static fn (Route $route): bool => Str::startsWith($route->uri(), 'api/v1/'))
            ->withDocumentTransformers(static function (\Dedoc\Scramble\Support\Generator\OpenApi $openApi): void {
                $openApi->secure(
                    \Dedoc\Scramble\Support\Generator\SecurityScheme::http('bearer')
                );
            });
    }
}
