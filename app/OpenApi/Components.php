<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Annotations as OA;

/**
 * Shared building blocks for the API contract. Controllers reference these via $ref,
 * so the envelope is documented once and cannot drift per endpoint.
 *
 * This class is intentionally empty: swagger-php only needs a scanned docblock to attach to.
 * (l5-swagger scans app/ by default; see config/l5-swagger.php -> annotations.)
 *
 * @OA\Schema(
 *     schema="ApiMeta",
 *     required={"request_id"},
 *     @OA\Property(property="request_id", type="string", example="req_01JCQ7Z8K2X4M9N5P3R6T8V0WY",
 *         description="Also returned in the X-Request-Id response header. Quote it when reporting a problem.")
 * )
 *
 * @OA\Schema(
 *     schema="ApiError",
 *     required={"message","error_code","errors","meta"},
 *     @OA\Property(property="message", type="string", example="The given data was invalid."),
 *     @OA\Property(property="error_code", type="string", example="VALIDATION_ERROR",
 *         description="Stable machine-readable code. Clients should branch on this, not on message."),
 *     @OA\Property(property="errors", type="object", nullable=true,
 *         example={"email": {"These credentials do not match our records."}},
 *         additionalProperties=@OA\Schema(type="array", @OA\Items(type="string"))),
 *     @OA\Property(property="meta", ref="#/components/schemas/ApiMeta")
 * )
 *
 * @OA\Schema(
 *     schema="AuthUser",
 *     required={"id","name","email","role"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Shiwa"),
 *     @OA\Property(property="email", type="string", format="email", example="member@example.com"),
 *     @OA\Property(property="role", type="string", nullable=true, example="member")
 * )
 *
 * @OA\Schema(
 *     schema="AuthPayload",
 *     required={"token","token_type","user"},
 *     @OA\Property(property="token", type="string", example="3|EhXrEQSLM1lun4BIdBX4lQEbDS7BLLjm1xGTTLK6eac3817c"),
 *     @OA\Property(property="token_type", type="string", example="Bearer"),
 *     @OA\Property(property="user", ref="#/components/schemas/AuthUser")
 * )
 *
 * @OA\Schema(
 *     schema="AuthResponse",
 *     required={"message","data","meta"},
 *     @OA\Property(property="message", type="string", example="Login successful."),
 *     @OA\Property(property="data", ref="#/components/schemas/AuthPayload"),
 *     @OA\Property(property="meta", ref="#/components/schemas/ApiMeta")
 * )
 *
 * @OA\Response(
 *     response="Unauthenticated",
 *     description="Missing, invalid, or revoked bearer token",
 *     @OA\JsonContent(ref="#/components/schemas/ApiError", example={
 *         "message": "Unauthenticated.",
 *         "error_code": "UNAUTHENTICATED",
 *         "errors": null,
 *         "meta": {"request_id": "req_01JCQ7Z8K2X4M9N5P3R6T8V0WY"}
 *     })
 * )
 *
 * @OA\Response(
 *     response="RateLimited",
 *     description="Rate limit exceeded",
 *     @OA\Header(header="Retry-After", description="Seconds until the client may retry", @OA\Schema(type="integer")),
 *     @OA\Header(header="X-RateLimit-Limit", description="Requests allowed in the window", @OA\Schema(type="integer")),
 *     @OA\Header(header="X-RateLimit-Remaining", description="Requests left in the window", @OA\Schema(type="integer")),
 *     @OA\JsonContent(ref="#/components/schemas/ApiError", example={
 *         "message": "Too many requests. Please try again after 30 second(s).",
 *         "error_code": "RATE_LIMIT_EXCEEDED",
 *         "errors": null,
 *         "meta": {"request_id": "req_01JCQ7Z8K2X4M9N5P3R6T8V0WY", "limit": 5, "remaining": 0, "retry_after": 30}
 *     })
 * )
 */
final class Components {}
