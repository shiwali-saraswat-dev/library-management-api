<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

/**
 * Single place where any exception becomes the API error envelope:
 * { message, error_code, errors, meta: { request_id } }
 */
final class ApiError
{
    public static function from(Throwable $e, Request $request): JsonResponse
    {
        if ($e instanceof HttpResponseException) {
            return $e->getResponse();
        }

        [$status, $message, $code] = match (true) {
            $e instanceof ValidationException => [422, 'The given data was invalid.', 'VALIDATION_ERROR'],
            $e instanceof AuthenticationException => [401, 'Unauthenticated.', 'UNAUTHENTICATED'],
            $e instanceof AuthorizationException,
            $e instanceof AccessDeniedHttpException => [403, 'This action is unauthorized.', 'FORBIDDEN'],
            $e instanceof ModelNotFoundException,
            $e instanceof NotFoundHttpException => [404, 'Resource not found.', 'NOT_FOUND'],
            $e instanceof MethodNotAllowedHttpException => [405, 'Method not allowed.', 'METHOD_NOT_ALLOWED'],
            $e instanceof TooManyRequestsHttpException => [429, 'Too many requests.', 'RATE_LIMIT_EXCEEDED'],
            $e instanceof HttpExceptionInterface => [$e->getStatusCode(), $e->getMessage() ?: 'Request failed.', 'HTTP_ERROR'],
            default => [500, config('app.debug') ? $e->getMessage() : 'Server error.', 'SERVER_ERROR'],
        };

        return self::respond(
            $request, $status, $message, $code,
            errors: $e instanceof ValidationException ? $e->errors() : null,
            headers: $e instanceof HttpExceptionInterface ? $e->getHeaders() : [],
        );
    }

    public static function respond(
        Request $request,
        int $status,
        string $message,
        string $code,
        ?array $errors = null,
        array $meta = [],
        array $headers = [],
    ): JsonResponse {
        $requestId = $request->headers->get('X-Request-Id') ?: 'req_'.Str::ulid();

        return response()->json([
            'message' => $message,
            'error_code' => $code,
            'errors' => $errors,
            'meta' => ['request_id' => $requestId] + $meta,
        ], $status, $headers + ['X-Request-Id' => $requestId]);
    }
}
