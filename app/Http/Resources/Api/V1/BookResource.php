<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'isbn' => $this->isbn,
            'title' => $this->title,
            'author' => $this->whenLoaded('author', fn () => $this->author->name),
            'genre' => $this->genre,
            'total_copies' => $this->total_copies,
            'available_copies' => $this->available_copies,
            'published_at' => optional($this->published_at)->toDateString(),
            'category' => new CategoryResource($this->whenLoaded('category')),
            '_links' => $this->buildLinks(),
        ];
    }

    /**
     * HATEOAS: links reflect the resource's current state.
     * A book with copies on the shelf offers "borrow"; a fully
     * checked-out book offers "reserve" instead — never both.
     */
    private function buildLinks(): array
    {
        $links = [
            'self' => ['href' => url("/api/v1/books/{$this->id}"), 'method' => 'GET'],
        ];

        if ($this->trashed()) {
            $links['restore'] = ['href' => url("/api/v1/books/{$this->id}/restore"), 'method' => 'POST'];

            return $links;
        }

        if ($this->available_copies > 0) {
            $links['borrow'] = ['href' => url('/api/v1/borrows'), 'method' => 'POST'];
        } else {
            $links['reserve'] = ['href' => url('/api/v1/reservations'), 'method' => 'POST'];
        }

        $links['update'] = ['href' => url("/api/v1/books/{$this->id}"), 'method' => 'PATCH'];
        $links['delete'] = ['href' => url("/api/v1/books/{$this->id}"), 'method' => 'DELETE'];

        return $links;
    }
}
