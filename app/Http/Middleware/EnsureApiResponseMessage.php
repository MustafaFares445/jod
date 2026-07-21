<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiResponseMessage
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->is('api/*')) {
            return $response;
        }

        $message = $this->messageFor($request, $response);

        if ($response->getStatusCode() === Response::HTTP_NO_CONTENT) {
            return response()->json(['message' => $message]);
        }

        if (! $response instanceof JsonResponse) {
            return $response;
        }

        $payload = $response->getData(true);

        if (is_array($payload) && ! array_key_exists('message', $payload)) {
            $payload['message'] = $message;
            $response->setData($payload);
        }

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
