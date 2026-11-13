<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class MemberCollection extends ResourceCollection
{
    public $collects = MemberResource::class;

    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'data' => $this->collection,
            'meta' => [
                'per_page' => $this->resource->perPage(),
                'next_cursor' => $this->resource->nextCursor()?->encode(),
                'prev_cursor' => $this->resource->previousCursor()?->encode(),
                'has_more' => $this->resource->hasMorePages(),
                'filters' => $this->resolveAppliedFilters($request),
                'request_id' => $request->header('X-Request-ID', uniqid('req_')),
                'api_version' => 'v1',
                'timestamp' => now()->toIso8601String(),
            ],
            '_links' => [
                'self' => ['href' => $request->fullUrl()],
                'prev' => ['href' => $this->resource->previousPageUrl()],
                'next' => ['href' => $this->resource->nextPageUrl()],
                'create' => ['href' => route('members.store'), 'method' => 'POST'],
            ],
        ];
    }

    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        return [];
    }

    private function resolveAppliedFilters(Request $request): array
    {
        $raw = [
            'search' => $request->input('search'),
            'sort_by' => $request->input('sort_by', 'name'),
            'sort_dir' => $request->input('sort_dir', 'asc'),
            'is_active' => $request->input('is_active'),
            'trashed' => $request->input('trashed'),
            'per_page' => (int) $request->input('per_page', 15),
        ];

        return array_filter($raw, fn ($v) => $v !== null && $v !== '');
    }
}