<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Exceptions\Category\CategoryHasBooksException;
use App\Exceptions\Category\CategoryNotTrashedExceptions;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class Handler extends ExceptionHandler
{
    public function register(): void
    {
        // -----------------------------------------------------------------------
        // Category domain exceptions
        // -----------------------------------------------------------------------

        $this->renderable(function (CategoryHasBooksException $e): JsonResponse {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [
                    'category_id' => $e->getCategory()->id,
                    'books_count' => $e->getCategory()->books_count,
                    '_links' => [
                        'category_books' => [
                            'href' => route('categories.books.index', $e->getCategory()->id),
                            'method' => 'GET',
                        ],
                    ],
                ],
                'meta' => $this->buildMeta(),
            ], Response::HTTP_CONFLICT);
        });

        $this->renderable(function (CategoryNotTrashedExceptions $e): JsonResponse {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [
                    'category_id' => $e->getCategory()->id,
                    '_links' => [
                        'soft_delete' => [
                            'href' => route('categories.destroy', $e->getCategory()->id),
                            'method' => 'DELETE',
                        ],
                    ],
                ],
                'meta' => $this->buildMeta(),
            ], Response::HTTP_CONFLICT);
        });

        // -----------------------------------------------------------------------
        // Generic Laravel exceptions — all return consistent JSON envelope
        // -----------------------------------------------------------------------

        $this->renderable(function (ModelNotFoundException $e): JsonResponse {
            $model = class_basename($e->getModel());

            return response()->json([
                'success' => false,
                'message' => "{$model} not found.",
                'meta' => $this->buildMeta(),
            ], Response::HTTP_NOT_FOUND);
        });

        $this->renderable(function (NotFoundHttpException $e): JsonResponse {
            return response()->json([
                'success' => false,
                'message' => 'The requested endpoint does not exist.',
                'meta' => $this->buildMeta(),
            ], Response::HTTP_NOT_FOUND);
        });

        $this->renderable(function (AuthenticationException $e): JsonResponse {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please provide a valid Bearer token.',
                'meta' => $this->buildMeta(),
            ], Response::HTTP_UNAUTHORIZED);
        });

        $this->renderable(function (AccessDeniedHttpException $e): JsonResponse {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to perform this action.',
                'meta' => $this->buildMeta(),
            ], Response::HTTP_FORBIDDEN);
        });

        $this->renderable(function (ValidationException $e): JsonResponse {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
                'meta' => $this->buildMeta(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        });
    }

    /**
     * @return array<string, string>
     */
    private function buildMeta(): array
    {
        return [
            'request_id' => request()->header('X-Request-ID', uniqid('req_')),
            'api_version' => 'v1',
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
