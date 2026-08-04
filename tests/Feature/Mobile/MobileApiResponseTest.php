<?php

declare(strict_types=1);

namespace Tests\Feature\Mobile;

use App\Support\Mobile\MobileApiResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class MobileApiResponseTest extends TestCase
{
    public function test_success_response_serializes_empty_meta_as_object(): void
    {
        $response = MobileApiResponse::success([
            'pong' => true,
        ], 'Test response.');
        $payload = $response->getData();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($payload->success);
        $this->assertSame('Test response.', $payload->message);
        $this->assertTrue($payload->data->pong);
        $this->assertNull($payload->error);
        $this->assertEquals(new \stdClass, $payload->meta);
    }

    public function test_paginated_response_returns_only_mobile_pagination_meta_keys(): void
    {
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

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($payload->success);
        $this->assertSame('Records retrieved successfully.', $payload->message);
        $this->assertSame('first-record', $payload->data[0]->id);
        $this->assertNull($payload->error);
        $this->assertSame(2, $payload->meta->currentPage);
        $this->assertSame(2, $payload->meta->perPage);
        $this->assertSame(7, $payload->meta->total);
        $this->assertSame(4, $payload->meta->lastPage);
        $this->assertSame(
            ['currentPage', 'perPage', 'total', 'lastPage'],
            array_keys((array) $payload->meta),
        );
    }
}
