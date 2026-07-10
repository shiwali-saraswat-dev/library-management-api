<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Category
 */
class CategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'is_active' => $this->is_active,

            // books_count is a computed value via withCount('books') subquery.
            // It is NOT a real column — works fine without a migration.
            'books_count' => $this->books_count ?? 0,

            // FIX — removed color, icon, sort_order.
            // These columns do not exist in your current table.
            // Accessing $this->color would return null silently for now,
            // but it's misleading in the response. Remove until migration is done.
            //
            // When you are ready to add them, run the migration first, then
            // uncomment these lines:
            // 'color'      => $this->color,
            // 'icon'       => $this->icon,
            // 'sort_order' => $this->sort_order,

            // Soft-delete field — only included when record is trashed
            'deleted_at' => $this->when(
                $this->trashed(),
                fn () => $this->deleted_at?->toIso8601String()
            ),

            // Audit fields — only for admin/librarian, and only when columns exist.
            // created_by / updated_by columns don't exist yet in your table.
            // The whenLoaded() will safely return nothing since the relation
            // will never be loaded if the FK column doesn't exist.
            // Uncomment after migration:
            // 'created_by' => $this->when(
            //     $request->user()?->role !== 'member',
            //     fn () => $this->whenLoaded('createdBy', fn () => [
            //         'id'   => $this->createdBy->id,
            //         'name' => $this->createdBy->name,
            //     ])
            // ),
            // 'updated_by' => $this->when(
            //     $request->user()?->role !== 'member',
            //     fn () => $this->whenLoaded('updatedBy', fn () => [
            //         'id'   => $this->updatedBy->id,
            //         'name' => $this->updatedBy->name,
            //     ])
            // ),

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),

            '_links' => $this->resolveLinks($request),
        ];
    }

    /**
     * Builds item-level HATEOAS links, branching on two things:
     *   1. Is this record trashed or active? (different actions make sense for each)
     *   2. Is the current user actually authorized for each action?
     *      (never advertise a link the user can't use)
     *
     * This single method correctly serves every context this resource is
     * used in — list item, single GET, create response, update response,
     * and restore response all reuse this exact logic, since 'trashed' and
     * 'authorized' fully determine what should be shown either way.
     *
     * @return array<string, array<string, string>>
     */
    private function resolveLinks(Request $request): array
    {
        $user = $request->user();

        // 'self' and 'list' apply in every state — trashed or active,
        // you can always fetch this exact record again, and always
        // navigate back up to the collection it belongs to.
        $links = [
            'self' => ['href' => route('categories.show', ['category_id' => $this->id])],
            'list' => ['href' => route('categories.index')],
        ];

        if ($this->trashed()) {
            // Trashed records only support restore / force-delete —
            // update/delete on an already-deleted row doesn't make sense.
            if ($user?->can('restore', $this->resource)) {
                $links['restore'] = [
                    'href' => route('categories.restore', ['category_id' => $this->id]),
                    'method' => 'PATCH', // matches the actual route verb, not POST
                ];
            }

            if ($user?->can('forceDelete', $this->resource)) {
                $links['force_delete'] = [
                    'href' => route('categories.force-delete', ['category_id' => $this->id]),
                    'method' => 'DELETE',
                ];
            }

            return $links;
        }

        // Active record — update/delete are the relevant next actions.
        if ($user?->can('update', $this->resource)) {
            $links['update'] = [
                'href' => route('categories.update', ['category_id' => $this->id]),
                'method' => 'PUT',
            ];
        }

        if ($user?->can('delete', $this->resource)) {
            $links['delete'] = [
                'href' => route('categories.destroy', ['category_id' => $this->id]),
                'method' => 'DELETE',
            ];
        }

        return $links;
    }
}
