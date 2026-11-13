<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Member\ListMemberRequest;
use App\Http\Requests\Api\V1\Member\StoreMemberRequest;
use App\Http\Requests\Api\V1\Member\UpdateMemberRequest;
use App\Http\Resources\Api\V1\MemberCollection;
use App\Http\Resources\Api\V1\MemberResource;
use App\Models\Member;
use App\Services\MemberService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MemberController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private readonly MemberService $memberService,
    ) {}

    public function index(ListMemberRequest $request): JsonResponse
    {
        $members = $this->memberService->list($request->validated());
        return response()->json(new MemberCollection($members));
    }

    public function store(StoreMemberRequest $request): JsonResponse
    {
        $member = $this->memberService->create($request->validated(), $request->user()->id);
        return $this->created(data: new MemberResource($member));
    }

    /**
     * Member profile reads are low-risk and read-mostly, so we add HTTP-level
     * caching here: an ETag identifies the exact representation served, and
     * Cache-Control: private, max-age=60 tells the client's own cache
     * (browser / mobile HTTP client) it can reuse the response for up to 60s
     * without even asking the server. "private" (not "public") is important —
     * it prevents shared caches/CDNs from storing member data for one user
     * and serving it to another.
     *
     * On a revalidation request within/after that window, the client sends
     * If-None-Match with the ETag it already has. If it still matches the
     * current record, we skip re-serializing the resource entirely and
     * return 304 Not Modified with an empty body — cheaper for us, faster
     * for the client.
     */
    public function show(Request $request, int $member_id): JsonResponse|Response
    {
        $member = $this->memberService->findOrFail($member_id);
        $this->authorize('view', $member);

        $etag = $this->buildEtag($member, $request);

        if ($request->headers->get('If-None-Match') === $etag) {
            return response('', 304)
                ->header('ETag', $etag)
                ->header('Cache-Control', 'private, max-age=60');
        }

        return $this->success(data: new MemberResource($member))
            ->header('ETag', $etag)
            ->header('Cache-Control', 'private, max-age=60');
    }

    public function update(UpdateMemberRequest $request, int $member_id): JsonResponse
    {
        $member = $this->memberService->findOrFail($member_id);
        $this->authorize('update', $member);

        $updated = $this->memberService->update($member, $request->validated(), $request->user()->id);
        return $this->ok(data: new MemberResource($updated), message: 'Member updated successfully.');
    }

    public function destroy(int $member_id): JsonResponse
    {
        $member = $this->memberService->findOrFail($member_id);
        $this->authorize('delete', $member);

        $this->memberService->softDelete($member);
        return $this->noContent('Member deleted successfully.');
    }

    public function restore(int $member_id): JsonResponse
    {
        $member = $this->memberService->findTrashedOrFail($member_id);
        $this->authorize('restore', $member);

        $restored = $this->memberService->restore($member_id);
        return $this->ok(data: new MemberResource($restored), message: 'Member restored successfully.');
    }

    public function forceDelete(int $member_id): JsonResponse
    {
        $member = $this->memberService->findTrashedOrFail($member_id);
        $this->authorize('forceDelete', $member);

        $this->memberService->forceDelete($member_id);
        return $this->noContent('Member permanently deleted.');
    }

    /**
     * Weak ETag from a stable fingerprint of the record's current state.
     * Any field change updates `updated_at`, so this naturally invalidates
     * on every mutation without needing to hash the full serialized payload.
     *
     * We fold the requesting user's role into the fingerprint because
     * MemberResource conditionally includes created_by/updated_by for
     * admin/librarian — without this, a librarian's cached 304 could mask
     * fields a regular user should never have seen (and vice versa).
     */
    private function buildEtag(Member $member, Request $request): string
    {
        $roleFingerprint = $request->user()?->role ?? 'guest';

        $raw = implode('|', [
            $member->id,
            $member->updated_at?->timestamp,
            $member->deleted_at?->timestamp,
            $roleFingerprint,
        ]);

        return 'W/"'.md5($raw).'"';
    }
}