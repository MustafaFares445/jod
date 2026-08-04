<?php

namespace App\Providers;

use App\Models\User;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
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

        if (! class_exists(Scramble::class)) {
            return;
        }

        Scramble::registerApi('mobile', [
            'api_path' => 'api/mobile',
            'export_path' => 'public/mobile-api.json',
            'info' => [
                'version' => env('API_VERSION', '1.0.0'),
                'description' => <<<'MARKDOWN'
# JOD Mobile API

Documentation for the implemented native mobile API endpoints only. Public authentication endpoints are unauthenticated; profile and dashboard-context endpoints require a Sanctum bearer token.
MARKDOWN,
            ],
            'servers' => [
                'Current host' => 'api/mobile',
            ],
            'security_strategy' => [
                MiddlewareAuthSecurityStrategy::class,
                [
                    'middleware' => ['auth:sanctum', '*Authenticate:sanctum'],
                    'scheme' => SecurityScheme::http('bearer'),
                ],
            ],
            'ui' => [
                'title' => 'JOD Mobile API',
                'theme' => 'light',
                'hide_try_it' => false,
                'hide_schemas' => false,
                'logo' => '',
                'try_it_credentials_policy' => 'include',
                'layout' => 'responsive',
            ],
        ])
            ->routes(static fn (Route $route): bool => Str::startsWith($route->uri(), 'api/mobile/'))
            ->expose(ui: 'docs/mobile-api', document: 'docs/mobile-api.json');

        Scramble::configure()
            ->routes(static fn (Route $route): bool => Str::startsWith($route->uri(), 'api/v1/'))
            ->withDocumentTransformers(static function (OpenApi $openApi): void {
                $openApi->secure(
                    SecurityScheme::http('bearer')
                );
            });
    }
}
