<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiResponseMessage
{
    private const JSON_OPTIONS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->is('api/*')) {
            return $response;
        }

        $message = $this->messageFor($request, $response);

        if ($response->getStatusCode() === Response::HTTP_NO_CONTENT) {
            return response()->json([
                'statusCode' => Response::HTTP_OK,
                'message' => $message,
            ], Response::HTTP_OK, [], self::JSON_OPTIONS);
        }

        if (! $response instanceof JsonResponse) {
            return $response;
        }

        $payload = $response->getData(true);

        if (! is_array($payload)) {
            return $response;
        }

        if (! array_key_exists('message', $payload)) {
            $payload['message'] = $message;
        }

        // `item` is no longer part of the API contract. Remove it even when
        // an older controller/resource still includes it explicitly.
        unset($payload['item']);

        if ($response->getStatusCode() < Response::HTTP_BAD_REQUEST) {
            $payload['statusCode'] ??= $response->getStatusCode();
        }

        $response->setEncodingOptions($response->getEncodingOptions() | self::JSON_OPTIONS);
        $response->setData($payload);

        return $response;
    }

    private function messageFor(Request $request, Response $response): string
    {
        return match (true) {
            $response->getStatusCode() === Response::HTTP_UNAUTHORIZED => 'Unauthenticated.',
            $response->getStatusCode() === Response::HTTP_FORBIDDEN => 'This action is unauthorized.',
            $response->getStatusCode() === Response::HTTP_NOT_FOUND => 'Resource not found.',
            $response->getStatusCode() >= Response::HTTP_INTERNAL_SERVER_ERROR => 'Server error.',
            $request->isMethod('delete') => 'Data deleted successfully.',
            $request->isMethod('patch'), $request->isMethod('put') => 'Data updated successfully.',
            $request->isMethod('post') && $response->getStatusCode() === Response::HTTP_CREATED => 'Data created successfully.',
            $request->isMethod('post') => 'Operation completed successfully.',
            default => 'Data retrieved successfully.',
        };
    }
}
