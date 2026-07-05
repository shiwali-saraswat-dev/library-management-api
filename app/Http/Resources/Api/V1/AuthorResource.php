<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthorResource extends JsonResource
{
    /**
     * This is the ONLY place that decides what an Author looks like on the wire.
     * The Model can have extra internal columns; this class is the public
     * contract with API consumers, decoupled from the database schema.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'bio' => $this->bio,
            'dob' => $this->dob?->format('Y-m-d'),
            'country' => $this->country,

            // whenCounted() only includes this key if the controller/service
            // actually called withCount('books') on the query — otherwise it's
            // omitted from the JSON entirely instead of returning null noise.
            'books_count' => $this->whenCounted('books'),

            // whenLoaded avoids an N+1: books is only included if the caller
            // eager-loaded it (->with('books')), never triggers a lazy query.
            // 'books' => BookResource::collection($this->whenLoaded('books')),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Only relevant on the /authors/trashed listing — merge() drops
            // the key entirely on a non-trashed author instead of sending null.
            $this->mergeWhen($this->trashed(), [
                'deleted_at' => $this->deleted_at?->toIso8601String(),
            ]),
        ];
    }
}
