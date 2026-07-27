<?php

declare(strict_types=1);

namespace App\Support\Mobile;

use Illuminate\Http\JsonResponse;

class MobileApiResponse
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public static function success(mixed $data = null, string $message = 'Operation completed successfully.', array $meta = []): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'error' => null,
            'meta' => (object) $meta,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $details
     */
    public static function error(string $code, string $message, ?array $details = null, int $status = 422): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
            'meta' => (object) [],
        ], $status);
    }
}
