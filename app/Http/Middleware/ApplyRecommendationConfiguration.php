<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\RecommendationConfigurationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyRecommendationConfiguration
{
    public function __construct(
        private readonly RecommendationConfigurationService $configuration,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        config(['recommendations' => $this->configuration->effective()]);

        return $next($request);
    }
}
