<?php

namespace App\OpenApi\Schemas;

/**
 * @OA\Schema(
 *     schema="Book",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="isbn", type="string", example="9780143127550"),
 *     @OA\Property(property="title", type="string", example="The Goldfinch"),
 *     @OA\Property(property="author", type="string", example="Donna Tartt"),
 *     @OA\Property(property="genre", type="string", example="Fiction"),
 *     @OA\Property(property="total_copies", type="integer", example=5),
 *     @OA\Property(property="available_copies", type="integer", example=2),
 *     @OA\Property(property="published_at", type="string", format="date", example="2013-10-22"),
 *     @OA\Property(
 *         property="_links",
 *         type="object",
 *         description="HATEOAS links, state-driven — 'borrow' when available_copies > 0, 'reserve' otherwise, 'restore' only when trashed"
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="BookInput",
 *     type="object",
 *     required={"isbn","title","author_id","genre","total_copies","available_copies"},
 *     @OA\Property(property="isbn", type="string", example="9780316769488"),
 *     @OA\Property(property="title", type="string", example="The Catcher in the Rye"),
 *     @OA\Property(property="author_id", type="integer", example=1),
 *     @OA\Property(property="category_id", type="integer", nullable=true, example=1),
 *     @OA\Property(property="genre", type="string", example="Fiction"),
 *     @OA\Property(property="total_copies", type="integer", example=6),
 *     @OA\Property(property="available_copies", type="integer", example=6),
 *     @OA\Property(property="published_at", type="string", format="date", nullable=true, example="1951-07-16")
 * )
 */
class BookSchema
{
    // annotation-only container — never instantiated
}