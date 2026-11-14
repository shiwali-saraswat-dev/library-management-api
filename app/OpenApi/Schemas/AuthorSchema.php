<?php

namespace App\OpenApi\Schemas;

/**
 * @OA\Schema(
 *     schema="Author",
 *     type="object",
 *     required={"id","name"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Donna Tartt"),
 *     @OA\Property(property="bio", type="string", nullable=true, example="American novelist."),
 *     @OA\Property(property="dob", type="string", format="date", nullable=true, example="1963-12-23"),
 *     @OA\Property(property="country", type="string", nullable=true, example="United States"),
 *     @OA\Property(property="books_count", type="integer", description="Only present when the list was requested with a book count", example=3),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2026-11-13T09:16:12+00:00"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2026-11-13T09:16:12+00:00"),
 *     @OA\Property(property="deleted_at", type="string", format="date-time", description="Only present on soft-deleted authors", example="2026-11-10T09:16:12+00:00"),
 *     @OA\Property(
 *         property="_links",
 *         type="object",
 *         description="HATEOAS links. 'update' and 'destroy' appear only if the caller is allowed to use them."
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="AuthorInput",
 *     type="object",
 *     required={"name"},
 *     @OA\Property(property="name", type="string", example="Donna Tartt"),
 *     @OA\Property(property="bio", type="string", nullable=true, example="American novelist."),
 *     @OA\Property(property="dob", type="string", format="date", nullable=true, example="1963-12-23"),
 *     @OA\Property(property="country", type="string", nullable=true, example="United States")
 * )
 */
class AuthorSchema
{
    // annotation-only container — never instantiated
}