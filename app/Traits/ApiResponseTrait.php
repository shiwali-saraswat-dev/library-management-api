<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\CursorPaginator;

trait ApiResponseTrait
{
    protected function success(mixed $data = null, string $message = 'Success',
        int $status = 200, array $meta = [], array $headers = []): JsonResponse
    {
        return response()->json(
            $this->withRequestId(['message' => $message, 'data' => $data], $meta),
            $status,
            $headers
        );
    }

    protected function ok(mixed $data = null, string $message = 'Success'): JsonResponse
    {
        return $this->success($data, $message);
    }

    protected function created(mixed $data, string $message = 'Resource created successfully.',
        ?string $location = null): JsonResponse
    {
        return $this->success($data, $message, 201, headers: $location ? ['Location' => $location] : []);
    }

    protected function noContent(): Response
    {
        return response()->noContent();
    }

    protected function paginated(LengthAwarePaginator $p, string $resource, string $message = 'Success'): JsonResponse
    {
        return response()->json($this->withRequestId($this->paginatedPayload($p, $resource, $message)));
    }

    /** Cache-safe body (no request_id), used by BookController. */
    protected function paginatedPayload(LengthAwarePaginator $p, string $resource, string $message = 'Success'): array
    {
        return [
            'message' => $message,
            'data'    => $resource::collection($p->items())->resolve(request()),
            'links'   => [
                'first' => $p->url(1),
                'last'  => $p->url($p->lastPage()),
                'prev'  => $p->previousPageUrl(),
                'next'  => $p->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $p->currentPage(),
                'per_page'     => $p->perPage(),
                'last_page'    => $p->lastPage(),
                'total'        => $p->total(),
            ],
        ];
    }

    protected function error(string $message, string $errorCode, int $status = 400, ?array $errors = null): JsonResponse
    {
        return response()->json($this->withRequestId([
            'message' => $message, 'error_code' => $errorCode, 'errors' => $errors,
        ]), $status);
    }

    protected function withRequestId(array $body, array $meta = []): array
    {
        $body['meta'] = array_merge($body['meta'] ?? [], $meta, [
            'request_id' => request()->header('X-Request-Id'),
        ]);

        return $body;
    }

    /**
     * Cursor-paginated list in the standard envelope:
     * { message, data, meta: { per_page, next_cursor, prev_cursor, has_more, request_id } }.
     *
     * @param  CursorPaginator<int, \Illuminate\Database\Eloquent\Model>  $paginator
     * @param  class-string<JsonResource>  $resource
     */
    protected function cursorPaginated(CursorPaginator $paginator, string $resource, string $message = 'Success'): JsonResponse
    {
        return response()->json($this->withRequestId([
            'message' => $message,
            'data' => $resource::collection($paginator->items())->resolve(request()),
            'meta' => [
                'per_page' => $paginator->perPage(),
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'prev_cursor' => $paginator->previousCursor()?->encode(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ]));
    }
}