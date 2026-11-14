<?php

declare(strict_types=1);

use App\Models\User;
use Spatie\Permission\Models\Role;

// Shared helpers and the RefreshDatabase + role seeding live in tests/Pest.php.

describe('POST /auth/register', function () {
    it('creates a member and returns the standard envelope with a token', function () {
        // The client tries to pick its own role: it must be ignored.
        $this->postJson('/api/v1/auth/register', registrationPayload(['role' => 'admin']))
            ->assertCreated()
            ->assertJsonStructure(AUTH_SUCCESS)
            ->assertJsonPath('message', 'Registered successfully.')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.role', 'member')
            ->assertHeader('X-Request-Id');

        $user = User::where('email', 'member@example.com')->firstOrFail();

        expect($user->getRoleNames()->all())->toBe(['member']);
    });

    it('leaves no user behind when the role cannot be assigned', function () {
        Role::query()->delete();

        $this->postJson('/api/v1/auth/register', registrationPayload())
            ->assertStatus(500)
            ->assertJsonStructure(ERROR_ENVELOPE)
            ->assertJsonPath('error_code', 'SERVER_ERROR');

        expect(User::count())->toBe(0);
    });

    it('rejects invalid input with a 422 error envelope', function () {
        $this->postJson('/api/v1/auth/register', registrationPayload(['password_confirmation' => 'different']))
            ->assertUnprocessable()
            ->assertJsonStructure(ERROR_ENVELOPE)
            ->assertJsonPath('error_code', 'VALIDATION_ERROR')
            ->assertJsonValidationErrors(['password']);
    });

    it('rejects a duplicate email', function () {
        User::factory()->create(['email' => 'member@example.com']);

        $this->postJson('/api/v1/auth/register', registrationPayload())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });
});

describe('POST /auth/login', function () {
    it('returns the standard envelope with a token and the user', function () {
        $user = memberWithPassword();

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'secret123'])
            ->assertOk()
            ->assertJsonStructure(AUTH_SUCCESS)
            ->assertJsonPath('message', 'Login successful.')
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonPath('data.user.role', 'member');
    });

    it('answers wrong password and unknown email identically (no account enumeration)', function () {
        $user = memberWithPassword();

        $wrongPassword = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'nope']);
        $unknownEmail = $this->postJson('/api/v1/auth/login', ['email' => 'ghost@example.com', 'password' => 'nope']);

        $wrongPassword->assertUnprocessable()
            ->assertJsonStructure(ERROR_ENVELOPE)
            ->assertJsonPath('error_code', 'INVALID_CREDENTIALS');

        expect($unknownEmail->status())->toBe($wrongPassword->status())
            ->and($unknownEmail->json('error_code'))->toBe($wrongPassword->json('error_code'))
            ->and($unknownEmail->json('errors'))->toBe($wrongPassword->json('errors'));
    });

    it('validates required fields', function () {
        $this->postJson('/api/v1/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonStructure(ERROR_ENVELOPE)
            ->assertJsonPath('error_code', 'VALIDATION_ERROR')
            ->assertJsonValidationErrors(['email', 'password']);
    });
});

describe('POST /auth/refresh', function () {
    it('issues a new token and revokes the old one', function () {
        $user = memberWithPassword();
        $oldToken = bearerTokenFor($user);

        $response = $this->withToken($oldToken)->postJson('/api/v1/auth/refresh')
            ->assertOk()
            ->assertJsonStructure(AUTH_SUCCESS)
            ->assertJsonPath('message', 'Token refreshed successfully.');

        expect($response->json('data.token'))->not->toBe($oldToken)
            ->and($user->tokens()->count())->toBe(1);

        // The auth guard caches the resolved user for the lifetime of the test app.
        app('auth')->forgetGuards();

        $this->withToken($oldToken)->postJson('/api/v1/auth/refresh')
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'UNAUTHENTICATED');
    });

    it('requires authentication', function () {
        $this->postJson('/api/v1/auth/refresh')
            ->assertUnauthorized()
            ->assertJsonStructure(ERROR_ENVELOPE)
            ->assertJsonPath('error_code', 'UNAUTHENTICATED');
    });
});

describe('POST /auth/logout', function () {
    it('revokes the current token and returns a null data envelope', function () {
        $user = memberWithPassword();
        $token = bearerTokenFor($user);

        $this->withToken($token)->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonStructure(['message', 'data', 'meta' => ['request_id']])
            ->assertJsonPath('message', 'Logged out successfully.')
            ->assertJsonPath('data', null);

        expect($user->tokens()->count())->toBe(0);

        app('auth')->forgetGuards();

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertUnauthorized();
    });

    it('requires authentication', function () {
        $this->postJson('/api/v1/auth/logout')
            ->assertUnauthorized()
            ->assertJsonStructure(ERROR_ENVELOPE);
    });
});

describe('request tracing', function () {
    it('echoes the incoming X-Request-Id in both the header and the body', function () {
        $this->withHeader('X-Request-Id', 'req_test_123')
            ->postJson('/api/v1/auth/login', ['email' => 'ghost@example.com', 'password' => 'nope'])
            ->assertHeader('X-Request-Id', 'req_test_123')
            ->assertJsonPath('meta.request_id', 'req_test_123');
    });
});