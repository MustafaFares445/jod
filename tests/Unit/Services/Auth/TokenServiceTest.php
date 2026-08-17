<?php

declare(strict_types=1);

use App\Services\Auth\TokenService;
use Laravel\Sanctum\PersonalAccessToken;

test('malformed access token metadata is rejected instead of throwing', function () {
    $token = new PersonalAccessToken();
    $token->name = false;

    expect((new TokenService())->isAccessToken($token))->toBeFalse();
});
