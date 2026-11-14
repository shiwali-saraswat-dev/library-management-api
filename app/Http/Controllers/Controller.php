<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'Library Management System API',
    version: '1.0.0',
    description: 'A production-grade RESTful API for managing a library catalog.',
    contact: new OA\Contact(
        name: 'Shiwali Saraswat',
        email: 'shiwali@library.test'
    ),
    license: new OA\License(
        name: 'MIT',
        url: 'https://opensource.org/licenses/MIT'
    )
)]
// Includes the /api/v1 prefix, so paths like /auth/login resolve correctly.
// Set L5_SWAGGER_CONST_HOST in .env (see config/l5-swagger.php -> constants).
#[OA\Server(
    url: L5_SWAGGER_CONST_HOST,
    description: 'API v1'
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Sanctum token',
    description: 'Paste the token from POST /auth/login (without the word "Bearer").'
)]
#[OA\Tag(name: 'Auth',       description: 'Authentication endpoints')]
#[OA\Tag(name: 'Authors',    description: 'Author management')]
#[OA\Tag(name: 'Categories', description: 'Category management')]
#[OA\Tag(name: 'Books',      description: 'Book catalog management')]
#[OA\Tag(name: 'Members',    description: 'Member management')]
#[OA\Schema(
    schema: 'SuccessMeta',
    properties: [
        new OA\Property(property: 'request_id', type: 'string', example: 'req_8f3e2a1b'),
    ]
)]
#[OA\Schema(
    schema: 'PaginationMeta',
    properties: [
        new OA\Property(property: 'total',        type: 'integer', example: 50),
        new OA\Property(property: 'per_page',     type: 'integer', example: 15),
        new OA\Property(property: 'current_page', type: 'integer', example: 1),
        new OA\Property(property: 'last_page',    type: 'integer', example: 4),
        new OA\Property(property: 'request_id',   type: 'string',  example: 'req_8f3e2a1b'),
    ]
)]
#[OA\Schema(
    schema: 'ValidationError',
    properties: [
        new OA\Property(property: 'message',    type: 'string', example: 'The given data was invalid.'),
        new OA\Property(property: 'error_code', type: 'string', example: 'VALIDATION_ERROR'),
        new OA\Property(property: 'errors',     type: 'object'),
    ]
)]
#[OA\Schema(
    schema: 'NotFoundError',
    properties: [
        new OA\Property(property: 'message',    type: 'string', example: 'Resource not found.'),
        new OA\Property(property: 'error_code', type: 'string', example: 'NOT_FOUND'),
    ]
)]
#[OA\Schema(
    schema: 'UnauthenticatedError',
    properties: [
        new OA\Property(property: 'message',    type: 'string', example: 'Unauthenticated.'),
        new OA\Property(property: 'error_code', type: 'string', example: 'UNAUTHENTICATED'),
    ]
)]
#[OA\Schema(
    schema: 'ForbiddenError',
    properties: [
        new OA\Property(property: 'message',    type: 'string', example: 'This action is unauthorized.'),
        new OA\Property(property: 'error_code', type: 'string', example: 'FORBIDDEN'),
    ]
)]
// Author, Category and Book schemas live in app/OpenApi/Schemas/ (one definition per name).
abstract class Controller
{
    use AuthorizesRequests;
}