<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class CategoryCollection extends ResourceCollection
{
    public $collects = CategoryResource::class;

    /**
     * @return array<string, mixed>
     */
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

                // Applied filters reflected back — client knows what was active
                'filters' => $this->resolveAppliedFilters($request),

                // Trace fields on every response
                'request_id' => $request->header('X-Request-ID', uniqid('req_')),
                'api_version' => 'v1',
                'timestamp' => now()->toIso8601String(),
            ],

            // FIX 18: Original had both 'links' and '_links' keys — two navigation
            // blocks for the same purpose. Merged into one '_links' block only.
            // _links is the HATEOAS convention; 'links' is Laravel's offset pagination
            // convention. We use cursor pagination, so offset-style 'links' is dropped.
            '_links' => [
                'self' => ['href' => $request->fullUrl()],
                'prev' => ['href' => $this->resource->previousPageUrl()],
                'next' => ['href' => $this->resource->nextPageUrl()],
                'create' => ['href' => route('categories.store'), 'method' => 'POST'],
            ],
        ];
    }

    /**
     * Suppress Laravel's automatic pagination meta injection.
     *
     * Laravel's ResourceCollection auto-merges its own default 'links' and 'meta'
     * blocks via array_merge_recursive when the wrapped resource is a paginator.
     * Because we build both blocks manually above, returning [] here prevents
     * Laravel's version from colliding with ours (which would turn scalar values
     * like per_page into arrays: [15, 15]).
     *
     * @param  array<string, mixed>  $paginated
     * @param  array<string, mixed>  $default
     * @return array<string, mixed>
     */
    public function paginationInformation(Request $request, array $paginated, array $default): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveAppliedFilters(Request $request): array
    {
        // FIX 19: array_filter with the fn() callback removes null AND empty string
        // but also removes integer 0 and boolean false — which are valid filter values
        // (e.g. is_active=0). Fixed to only remove null and empty string explicitly.
        $raw = [
            'search' => $request->input('search'),
            'sort_by' => $request->input('sort_by', 'sort_order'),
            'sort_dir' => $request->input('sort_dir', 'asc'),
            'is_active' => $request->input('is_active'),
            'trashed' => $request->input('trashed'),
            'per_page' => (int) $request->input('per_page', 15),
        ];

        return array_filter($raw, fn ($v) => $v !== null && $v !== '');
    }
}
