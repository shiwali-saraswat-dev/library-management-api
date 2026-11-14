<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use OpenApi\Annotations as OA;

class AuthController extends Controller
{
    use ApiResponseTrait;

    private const TOKEN_NAME = 'api-token';

    /** Self-registration always creates this role. Privileged roles are granted by an admin, never by the client. */
    private const DEFAULT_ROLE = 'member';

    /**
     * @OA\Post(
     *     path="/auth/register",
     *     operationId="authRegister",
     *     tags={"Auth"},
     *     summary="Register a new member account",
     *     description="Self-registration always creates a `member`. Any `role` sent by the client is ignored.",
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"name","email","password","password_confirmation"},
     *         @OA\Property(property="name", type="string", example="Shiwa"),
     *         @OA\Property(property="email", type="string", format="email", example="member@example.com"),
     *         @OA\Property(property="password", type="string", format="password", example="secret123"),
     *         @OA\Property(property="password_confirmation", type="string", format="password", example="secret123")
     *     )),
     *     @OA\Response(response=201, description="Registered", @OA\JsonContent(ref="#/components/schemas/AuthResponse")),
     *     @OA\Response(response=422, description="Validation error", @OA\JsonContent(ref="#/components/schemas/ApiError")),
     *     @OA\Response(response=429, ref="#/components/responses/RateLimited")
     * )
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        // One transaction: if the role does not exist, no orphaned user row is left behind.
        $user = DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make((string) $data['password']),
            ]);

            $user->assignRole(self::DEFAULT_ROLE);

            return $user;
        });

        return $this->created(
            $this->authPayload($user, $this->issueToken($user)),
            'Registered successfully.',
        );
    }

    /**
     * @OA\Post(
     *     path="/auth/login",
     *     operationId="authLogin",
     *     tags={"Auth"},
     *     summary="Log in and receive a bearer token",
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"email","password"},
     *         @OA\Property(property="email", type="string", format="email", example="member@example.com"),
     *         @OA\Property(property="password", type="string", format="password", example="secret123")
     *     )),
     *     @OA\Response(response=200, description="Authenticated", @OA\JsonContent(ref="#/components/schemas/AuthResponse")),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error, or credentials do not match (error_code INVALID_CREDENTIALS)",
     *         @OA\JsonContent(ref="#/components/schemas/ApiError")
     *     ),
     *     @OA\Response(response=429, ref="#/components/responses/RateLimited")
     * )
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        // Same response for "unknown email" and "wrong password": no account enumeration.
        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return $this->error(
                'The given data was invalid.',
                'INVALID_CREDENTIALS',
                422,
                ['email' => ['These credentials do not match our records.']]
            );
        }

        return $this->ok(
            $this->authPayload($user, $this->issueToken($user)),
            'Login successful.',
        );
    }

    /**
     * @OA\Post(
     *     path="/auth/refresh",
     *     operationId="authRefresh",
     *     tags={"Auth"},
     *     summary="Rotate the current token",
     *     description="Issues a new token and revokes the one used for this request.",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="New token issued, old one revoked", @OA\JsonContent(ref="#/components/schemas/AuthResponse")),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=429, ref="#/components/responses/RateLimited")
     * )
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);

        // Rotate atomically: never leave the client without a valid token if issuing fails.
        $payload = DB::transaction(function () use ($user): array {
            $this->revokeCurrentToken($user);

            return $this->authPayload($user, $this->issueToken($user));
        });

        return $this->ok($payload, 'Token refreshed successfully.');
    }

    /**
     * @OA\Post(
     *     path="/auth/logout",
     *     operationId="authLogout",
     *     tags={"Auth"},
     *     summary="Log out (revoke the current token)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Logged out, current token revoked",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Logged out successfully."),
     *             @OA\Property(property="data", type="object", nullable=true, example=null),
     *             @OA\Property(property="meta", ref="#/components/schemas/ApiMeta")
     *         )
     *     ),
     *     @OA\Response(response=401, ref="#/components/responses/Unauthenticated"),
     *     @OA\Response(response=429, ref="#/components/responses/RateLimited")
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        $this->revokeCurrentToken($this->currentUser($request));

        return $this->ok(null, 'Logged out successfully.');
    }

    /**
     * The one place the auth response body is assembled (register, login, refresh).
     *
     * @return array{token: string, token_type: string, user: array{id: int, name: string, email: string, role: string|null}}
     */
    private function authPayload(User $user, string $token): array
    {
        return [
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->getRoleNames()->first(),
            ],
        ];
    }

    private function issueToken(User $user): string
    {
        return $user->createToken(self::TOKEN_NAME)->plainTextToken;
    }

    /**
     * Narrows Authenticatable|null to User (keeps Larastan happy). The routes sit behind
     * auth:sanctum, so the exception is a safety net, and renders as the standard 401 envelope.
     */
    private function currentUser(Request $request): User
    {
        $user = $request->user();

        return $user instanceof User ? $user : throw new AuthenticationException();
    }

    /**
     * currentAccessToken() is a PersonalAccessToken for real bearer tokens, but a TransientToken
     * for session / actingAs() requests, which has nothing to delete.
     */
    private function revokeCurrentToken(User $user): void
    {
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }
    }
}