<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Borrowing\ListBorrowingRequest;
use App\Http\Requests\Api\V1\Borrowing\StoreBorrowingRequest;
use App\Http\Resources\Api\V1\BorrowingResource;
use App\Models\Borrowing;
use App\Models\User;
use App\Services\BorrowingService;
use App\Traits\ApiResponseTrait;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class BorrowingController extends Controller
{
    use ApiResponseTrait;

    public function __construct(private readonly BorrowingService $borrowings) {}

    /**
     * @OA\Get(
     *     path="/borrowings",
     *     operationId="borrowingsIndex",
     *     tags={"Borrowings"},
     *     summary="List borrowings (staff: all, member: own only)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="search", in="query", description="Book title/ISBN or member name/email (min 2 chars)", @OA\Schema(type="string", minLength=2)),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"active","overdue","returned"})),
     *     @OA\Parameter(name="member_id", in="query", description="Staff only. Ignored for members.", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="book_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="sort_by", in="query", @OA\Schema(type="string", enum={"borrowed_at","due_at","returned_at","created_at"}, default="borrowed_at")),
     *     @OA\Parameter(name="sort_dir", in="query", @OA\Schema(type="string", enum={"asc","desc"}, default="desc")),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", minimum=1, maximum=100, default=15)),
     *     @OA\Parameter(name="cursor", in="query", description="Value from meta.next_cursor", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Cursor-paginated list", @OA\JsonContent(ref="#/components/schemas/BorrowingListResponse")),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ApiError")),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ApiError")),
     *     @OA\Response(response=429, ref="#/components/responses/RateLimited")
     * )
     */
    public function index(ListBorrowingRequest $request): JsonResponse
    {
        $page = $this->borrowings->list($request->validated(), $this->actor($request));

        return $this->cursorPaginated($page, BorrowingResource::class);
    }

    /**
     * @OA\Post(
     *     path="/borrowings",
     *     operationId="borrowingsStore",
     *     tags={"Borrowings"},
     *     summary="Check a book out to a member",
     *     description="Runs in one transaction with row locks on the book and member, so concurrent checkouts of the last copy can never both succeed. Safe to retry: send the same Idempotency-Key and the original response is replayed.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="Idempotency-Key", in="header", required=true, description="Unique per checkout attempt. Reuse it only when retrying that same attempt.", @OA\Schema(type="string", format="uuid")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(ref="#/components/schemas/BorrowingInput")),
     *     @OA\Response(response=201, description="Borrowed", @OA\Header(header="Location", description="URL of the new borrowing", @OA\Schema(type="string")), @OA\JsonContent(ref="#/components/schemas/BorrowingResponse")),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden (members cannot check books out)", @OA\JsonContent(ref="#/components/schemas/ApiError")),
     *     @OA\Response(response=409, description="BOOK_NOT_AVAILABLE, ALREADY_BORROWED or LOAN_LIMIT_REACHED", @OA\JsonContent(ref="#/components/schemas/ApiError")),
     *     @OA\Response(response=422, description="Validation error, or MEMBER_NOT_ELIGIBLE", @OA\JsonContent(ref="#/components/schemas/ApiError")),
     *     @OA\Response(response=429, ref="#/components/responses/RateLimited")
     * )
     */
    public function store(StoreBorrowingRequest $request): JsonResponse
    {
        $borrowing = $this->borrowings->checkout(
            $request->integer('book_id'),
            $request->integer('member_id'),
            $this->actor($request),
        );

        return $this->created(new BorrowingResource($borrowing), 'Book borrowed successfully.')
            ->header('Location', route('borrowings.show', $borrowing));
    }

    /**
     * @OA\Get(
     *     path="/borrowings/{borrowing}",
     *     operationId="borrowingsShow",
     *     tags={"Borrowings"},
     *     summary="Get one borrowing",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="borrowing", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="OK", @OA\JsonContent(ref="#/components/schemas/BorrowingResponse")),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden (a member can only view their own)", @OA\JsonContent(ref="#/components/schemas/ApiError")),
     *     @OA\Response(response=404, description="Not found", @OA\JsonContent(ref="#/components/schemas/ApiError")),
     *     @OA\Response(response=429, ref="#/components/responses/RateLimited")
     * )
     */
    public function show(Borrowing $borrowing): JsonResponse
    {
        $this->authorize('view', $borrowing);

        return $this->ok(new BorrowingResource($this->borrowings->show($borrowing)));
    }

    /**
     * @OA\Post(
     *     path="/borrowings/{borrowing}/return",
     *     operationId="borrowingsReturn",
     *     tags={"Borrowings"},
     *     summary="Return a borrowed book",
     *     description="Closes the loan, finalises the fine and puts the copy back into stock.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="borrowing", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Returned", @OA\JsonContent(ref="#/components/schemas/BorrowingResponse")),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=403, description="Forbidden", @OA\JsonContent(ref="#/components/schemas/ApiError")),
     *     @OA\Response(response=404, description="Not found", @OA\JsonContent(ref="#/components/schemas/ApiError")),
     *     @OA\Response(response=409, description="ALREADY_RETURNED", @OA\JsonContent(ref="#/components/schemas/ApiError")),
     *     @OA\Response(response=429, ref="#/components/responses/RateLimited")
     * )
     */
    public function returnBook(Request $request, Borrowing $borrowing): JsonResponse
    {
        $this->authorize('returnBook', $borrowing);

        $returned = $this->borrowings->return($borrowing->id, $this->actor($request));

        return $this->ok(new BorrowingResource($returned), 'Book returned successfully.');
    }

    /**
     * Narrows Authenticatable|null to User. The routes sit behind auth:sanctum, so this is a safety net
     * that renders as the standard 401 envelope.
     */
    private function actor(Request $request): User
    {
        $user = $request->user();

        return $user instanceof User ? $user : throw new AuthenticationException();
    }
}
