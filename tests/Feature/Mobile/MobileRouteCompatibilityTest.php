<?php

declare(strict_types=1);

test('existing and requested mobile route names and paths remain registered', function () {
    $expectedRoutes = [
        'mobile.auth.register' => ['POST', 'api/mobile/auth/register'],
        'mobile.auth.login' => ['POST', 'api/mobile/auth/login'],
        'mobile.auth.refresh' => ['POST', 'api/mobile/auth/refresh'],
        'mobile.auth.logout' => ['POST', 'api/mobile/auth/logout'],
        'mobile.auth.forgot-password' => ['POST', 'api/mobile/auth/forgot-password'],
        'mobile.auth.verify-reset-code' => ['POST', 'api/mobile/auth/verify-reset-code'],
        'mobile.auth.reset-password' => ['POST', 'api/mobile/auth/reset-password'],
        'mobile.search' => ['GET', 'api/mobile/search'],
        'mobile.discovery.posts' => ['GET', 'api/mobile/discovery/posts'],
        'mobile.discovery.posts.show' => ['GET', 'api/mobile/discovery/posts/{post}'],
        'mobile.discovery.media' => ['GET', 'api/mobile/discovery/media'],
        'mobile.discovery.media.show' => ['GET', 'api/mobile/discovery/media/{video}'],
        'mobile.discovery.campaigns' => ['GET', 'api/mobile/discovery/campaigns'],
        'mobile.discovery.campaigns.show' => ['GET', 'api/mobile/discovery/campaigns/{campaign}'],
        'mobile.discovery.articles' => ['GET', 'api/mobile/discovery/articles'],
        'mobile.discovery.articles.show' => ['GET', 'api/mobile/discovery/articles/{article}'],
        'mobile.discovery.categories' => ['GET', 'api/mobile/discovery/categories'],
        'mobile.me.profile' => ['GET', 'api/mobile/me'],
        'mobile.me.profile.update' => ['PATCH', 'api/mobile/me/profile'],
        'mobile.me.change-password' => ['PATCH', 'api/mobile/me/change-password'],
        'mobile.me.permissions' => ['GET', 'api/mobile/me/permissions'],
        'mobile.me.posts.index' => ['GET', 'api/mobile/me/posts'],
        'mobile.me.posts.show' => ['GET', 'api/mobile/me/posts/{post}'],
        'mobile.me.saved-posts.index' => ['GET', 'api/mobile/me/saved-posts'],
        'mobile.me.donations.index' => ['GET', 'api/mobile/me/donations'],
        'mobile.me.donations.show' => ['GET', 'api/mobile/me/donations/{donation}'],
        'mobile.me.devices.store' => ['PUT', 'api/mobile/me/devices'],
        'mobile.me.devices.destroy' => ['DELETE', 'api/mobile/me/devices/{device}'],
        'mobile.me.notifications.index' => ['GET', 'api/mobile/me/notifications'],
        'mobile.me.notifications.unread-count' => ['GET', 'api/mobile/me/notifications/unread-count'],
        'mobile.me.notifications.read-all' => ['PATCH', 'api/mobile/me/notifications/read-all'],
        'mobile.me.notifications.show' => ['GET', 'api/mobile/me/notifications/{notification}'],
        'mobile.me.notifications.read' => ['PATCH', 'api/mobile/me/notifications/{notification}/read'],
        'mobile.me.notifications.unread' => ['PATCH', 'api/mobile/me/notifications/{notification}/unread'],
        'mobile.campaigns.donations.store' => ['POST', 'api/mobile/campaigns/{campaign}/donations'],
        'mobile.posts.images.store' => ['POST', 'api/mobile/posts/{post}/images'],
        'mobile.posts.images.reorder' => ['PATCH', 'api/mobile/posts/{post}/images/order'],
        'mobile.posts.images.destroy' => ['DELETE', 'api/mobile/posts/{post}/images/{image}'],
    ];

    foreach ($expectedRoutes as $routeName => [$method, $uri]) {
        $route = app('router')->getRoutes()->getByName($routeName);

        expect($route)->not->toBeNull("Route [{$routeName}] is not registered.");
        expect($route->uri())->toBe($uri);
        expect($route->methods())->toContain($method);
    }
});

test('public discovery and global search routes are throttled', function () {
    $routeNames = [
        'mobile.search',
        'mobile.discovery.posts',
        'mobile.discovery.posts.show',
        'mobile.discovery.media',
        'mobile.discovery.media.show',
        'mobile.discovery.campaigns',
        'mobile.discovery.campaigns.show',
        'mobile.discovery.articles',
        'mobile.discovery.articles.show',
        'mobile.discovery.categories',
    ];

    foreach ($routeNames as $routeName) {
        $route = app('router')->getRoutes()->getByName($routeName);

        expect($route)->not->toBeNull("Route [{$routeName}] is not registered.");
        expect($route->gatherMiddleware())->toContain('throttle:60,1');
    }
});

test('authenticated mobile routes require access token ability', function () {
    foreach (['mobile.auth.logout', 'mobile.me.profile', 'mobile.me.posts.show', 'mobile.me.devices.store', 'mobile.posts.images.store'] as $routeName) {
        $route = app('router')->getRoutes()->getByName($routeName);

        expect($route)->not->toBeNull("Route [{$routeName}] is not registered.");
        expect($route->gatherMiddleware())->toContain('auth:sanctum');
        expect($route->gatherMiddleware())->toContain('mobile-access-token');
    }
});
