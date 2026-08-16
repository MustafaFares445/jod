<?php

declare(strict_types=1);
use App\Support\Mobile\MobileApiResponse;
use Illuminate\Pagination\LengthAwarePaginator;
test('success response serializes empty meta as object', function () {
    $response = MobileApiResponse::success([
        'pong' => true,
    ], 'Test response.');
    $payload = $response->getData();

    expect($response->getStatusCode())->toBe(200);
    expect($payload->success)->toBeTrue();
    expect($payload->message)->toBe('Test response.');
    expect($payload->data->pong)->toBeTrue();
    expect($payload->error)->toBeNull();
    expect($payload->meta)->toEqual(new \stdClass);
});
test('paginated response returns only mobile pagination meta keys', function () {
    $paginator = new LengthAwarePaginator(
        items: collect([
            ['id' => 'first-record'],
            ['id' => 'second-record'],
        ]),
        total: 7,
        perPage: 2,
        currentPage: 2,
    );

    $response = MobileApiResponse::paginated(
        $paginator,
        'Records retrieved successfully.',
    );
    $payload = $response->getData();

    expect($response->getStatusCode())->toBe(200);
    expect($payload->success)->toBeTrue();
    expect($payload->message)->toBe('Records retrieved successfully.');
    expect($payload->data[0]->id)->toBe('first-record');
    expect($payload->error)->toBeNull();
    expect($payload->meta->currentPage)->toBe(2);
    expect($payload->meta->perPage)->toBe(2);
    expect($payload->meta->total)->toBe(7);
    expect($payload->meta->lastPage)->toBe(4);
    expect(array_keys((array) $payload->meta))->toBe(['currentPage', 'perPage', 'total', 'lastPage']);
});
