<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

trait ApiResponseTrait
{
    /**
     * Returns a standardized successful API response.
     *
     * @param  mixed  $data  Response payload.
     * @param  int  $status  HTTP status code.
     * @param  array  $meta  additional response information (e.g., request ID,
     *                       pagination, execution time, API version, server time, warnings, or rate limit
     *                       details)
     */
    protected function success(mixed $data, int $status = 200, array $meta = []): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'meta' => array_merge(
                [
                    'request_id' => 'req_'.now()->format('YmdHisv').'_'.Str::random(6),
                ],
                $meta),
        ], $status);
    }

    /**
     * Returns a standardized error API response.
     *
     * @param  string  $message  Human-readable error message.
     * @param  string  $errorCode  Application-specific error code.
     * @param  int  $status  HTTP status code.
     * @param  array|null  $errors  Validation or detailed error information.
     */
    protected function error(string $message, string $errorCode, int $status = 400, ?array $errors = null): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'error_code' => $errorCode,
            'errors' => $errors,
            'meta' => [
                'request_id' => 'req_'.now()->format('YmdHisv').'_'.Str::random(6),
            ],
        ], $status);
    }
}
