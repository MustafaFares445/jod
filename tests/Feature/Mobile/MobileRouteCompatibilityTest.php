<?php

declare(strict_types=1);

namespace Tests\Feature\Mobile;

use Tests\TestCase;

class MobileRouteCompatibilityTest extends TestCase
{
    public function test_existing_mobile_route_names_and_paths_remain_registered(): void
    {
        $expectedRoutes = [
            'mobile.auth.register' => ['POST', 'api/mobile/auth/register'],
            'mobile.auth.login' => ['POST', 'api/mobile/auth/login'],
            'mobile.auth.logout' => ['POST', 'api/mobile/auth/logout'],
            'mobile.auth.forgot-password' => ['POST', 'api/mobile/auth/forgot-password'],
            'mobile.auth.verify-reset-code' => ['POST', 'api/mobile/auth/verify-reset-code'],
            'mobile.auth.reset-password' => ['POST', 'api/mobile/auth/reset-password'],
            'mobile.me.profile' => ['GET', 'api/mobile/me'],
            'mobile.me.profile.update' => ['PATCH', 'api/mobile/me/profile'],
            'mobile.me.change-password' => ['PATCH', 'api/mobile/me/change-password'],
            'mobile.me.permissions' => ['GET', 'api/mobile/me/permissions'],
            'mobile.me.dashboard-context' => ['GET', 'api/mobile/me/dashboard-context'],
            'mobile.me.ping' => ['GET', 'api/mobile/me/ping'],
        ];

        foreach ($expectedRoutes as $routeName => [$method, $uri]) {
            $route = app('router')->getRoutes()->getByName($routeName);

            $this->assertNotNull($route, "Route [{$routeName}] is not registered.");
            $this->assertSame($uri, $route->uri());
            $this->assertContains($method, $route->methods());
        }
    }
}
