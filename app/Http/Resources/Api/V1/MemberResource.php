<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'membership_number' => $this->membership_number,
            'is_active' => $this->is_active,

            'created_by' => $this->when(
                in_array($user?->role, ['admin', 'librarian'], true),
                fn () => $this->createdBy?->name
            ),
            'updated_by' => $this->when(
                in_array($user?->role, ['admin', 'librarian'], true),
                fn () => $this->updatedBy?->name
            ),

            'deleted_at' => $this->when(
                $this->trashed(),
                fn () => $this->deleted_at?->toIso8601String()
            ),

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),

            '_links' => $this->resolveLinks($request),
        ];
    }

    private function resolveLinks(Request $request): array
    {
        $user = $request->user();

        $links = [
            'self' => ['href' => route('members.show', ['member_id' => $this->id])],
            'list' => ['href' => route('members.index')],
        ];

        if ($this->trashed()) {
            if ($user?->can('restore', $this->resource)) {
                $links['restore'] = [
                    'href' => route('members.restore', ['member_id' => $this->id]),
                    'method' => 'PATCH',
                ];
            }

            if ($user?->can('forceDelete', $this->resource)) {
                $links['force_delete'] = [
                    'href' => route('members.force-delete', ['member_id' => $this->id]),
                    'method' => 'DELETE',
                ];
            }

            return $links;
        }

        if ($user?->can('update', $this->resource)) {
            $links['update'] = [
                'href' => route('members.update', ['member_id' => $this->id]),
                'method' => 'PUT',
            ];
        }

        if ($user?->can('delete', $this->resource)) {
            $links['delete'] = [
                'href' => route('members.destroy', ['member_id' => $this->id]),
                'method' => 'DELETE',
            ];
        }

        return $links;
    }
}