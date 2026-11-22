<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Base class for business-rule failures. Carries an HTTP status and a stable,
 * machine-readable error_code, and renders itself in the standard API error envelope:
 * { message, error_code, errors, meta: { request_id } }.
 *
 * If you already have a base domain exception, extend that instead and delete this file.
 */
abstract class ApiException extends RuntimeException
{
    /**
     * @param  array<string, list<string>>|null  $errors
     */
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status = 400,
        public readonly ?array $errors = null,
    ) {
        parent::__construct($message);
    }

    public function render(Request $request): JsonResponse
    {
        $requestId = $request->headers->get('X-Request-Id') ?: 'req_'.Str::ulid();

        return response()->json([
            'message' => $this->getMessage(),
            'error_code' => $this->errorCode,
            'errors' => $this->errors,
            'meta' => ['request_id' => $requestId],
        ], $this->status, ['X-Request-Id' => $requestId]);
    }
}
