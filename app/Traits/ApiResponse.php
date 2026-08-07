<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

trait ApiResponse
{
    protected function successResponse(
        mixed $data = null,
        string $message = 'Operation completed successfully.',
        int $status = 200,
    ): JsonResponse {
        $payload = ['message' => $message];

        if ($data !== null) {
            $payload = [
                'data' => $data instanceof JsonResource ? $data->resolve(request()) : $data,
                'message' => $message,
            ];
        }

        return response()->json($payload, $status);
    }

    protected function errorResponse(
        string $message,
        int $status = 400,
        mixed $errors = null,
    ): JsonResponse {
        $payload = ['message' => $message];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}
