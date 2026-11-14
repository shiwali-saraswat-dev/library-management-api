<?php

namespace App\OpenApi\Schemas;

/**
 * @OA\Schema(
 *     schema="Category",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Fiction"),
 *     @OA\Property(property="slug", type="string", example="fiction"),
 *     @OA\Property(property="description", type="string", nullable=true, example="Novels and fictional literature."),
 *     @OA\Property(property="is_active", type="boolean", example=true)
 * )
 *
 * @OA\Schema(
 *     schema="CategoryInput",
 *     type="object",
 *     required={"name"},
 *     @OA\Property(property="name", type="string", example="Fiction"),
 *     @OA\Property(property="slug", type="string", nullable=true, example="fiction"),
 *     @OA\Property(property="description", type="string", nullable=true, example="Novels and fictional literature."),
 *     @OA\Property(property="is_active", type="boolean", example=true)
 * )
 */
class CategorySchema
{
    // annotation-only container — never instantiated
}