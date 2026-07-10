<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * ApiResponseTrait
 *
 * Centralizes the shape of every JSON response this API returns, so every
 * controller sends back the same consistent envelope (message/data/meta,
 * or message/error_code/errors/meta) instead of each method hand-rolling
 * its own response()->json([...]) call with slightly different keys.
 *
 * Use this trait in any controller that needs to return standardized
 * success, error, created, ok, or no-content responses.
 */
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
    // protected function success(mixed $data, int $status = 200, array $meta = []): JsonResponse
    // {
    //     return response()->json([
    //         'data' => $data,
    //         'meta' => array_merge(
    //             [
    //                 'request_id' => 'req_'.now()->format('YmdHisv').'_'.Str::random(6),
    //             ],
    //             $meta),
    //     ], $status);
    // }

    /**
     * Base success response builder. Every other "happy path" helper below
     * (created, ok) delegates to this one, so the envelope shape only needs
     * to be defined and changed in a single place.
     *
     * @param  mixed  $data  The payload to return -- a model, resource, array, or null.
     * @param  string  $message  Human-readable success message shown to the API consumer.
     * @param  int  $status  HTTP status code (200, 201, etc).
     * @param  array  $meta  Extra meta fields merged in alongside the auto-generated request_id.
     */
    protected function success(mixed $data = null, string $message = 'Success',
        int $status = 200, array $meta = []): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'data' => $data,
            'meta' => array_merge([
                'request_id' => 'req_'.now()->format('YmdHisv').'_'.Str::random(6),
            ], $meta),
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

    /**
     * Shortcut for a "resource successfully created" response.
     * Always returns HTTP 201, matching REST convention for a successful
     * POST that creates a new record.
     *
     * @param  JsonResource|array|null  $data  The newly created resource.
     * @param  string  $message  Human-readable confirmation message.
     */
    protected function created(JsonResource|array|null $data = null,
        string $message = 'Resource created successfully.'): JsonResponse
    {
        return $this->success(
            data: $data,
            message: $message,
            status: 201
        );
    }

    /**
     * Shortcut for a plain "200 OK" success response.
     * Use for reads and updates that don't need a specific status override.
     *
     * @param  mixed  $data  The payload to return.
     * @param  string  $message  Human-readable success message.
     */
    protected function ok(mixed $data = null, string $message = 'Success'): JsonResponse
    {
        return $this->success(
            data: $data,
            message: $message,
            status: Response::HTTP_OK
        );
    }

    /**
     * Shortcut for a "204 No Content" response, used after a successful
     * delete/soft-delete/restore where there's nothing meaningful to return
     * in the body. Note: including a JSON body on a true 204 is technically
     * against the HTTP spec (204 means "no body"), so treat this helper as a
     * pragmatic convenience rather than a strict-spec response -- some HTTP
     * clients may ignore or strip the body on a 204 regardless of what's sent.
     *
     * @param  string  $message  Optional confirmation message.
     */
    protected function noContent(string $message = ''): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => null,
            'meta' => $this->buildMeta(),
        ], Response::HTTP_NO_CONTENT);
    }

    /**
     * Builds the standard meta block attached to noContent() responses:
     * request id (from the incoming X-Request-ID header if present,
     * otherwise a generated one), API version, and a server-side timestamp.
     *
     * Note: this uses a different request_id format/source than success()
     * and error() above (which generate their own via Str::random()) --
     * worth unifying to one approach if consistent request tracing across
     * all response types matters for your logging/observability setup.
     */
    private function buildMeta(): array
    {
        return [
            'request_id' => request()->header('X-Request-ID', uniqid('req_')),
            'api_version' => 'v1',
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
