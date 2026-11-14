<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
| Every Feature test boots the app, starts from an empty database and gets the
| real roles and permissions seeded, so tests use the same guard as production.
*/

pest()->extend(Tests\TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => $this->seed(RolePermissionSeeder::class))
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Shared constants and helpers
|--------------------------------------------------------------------------
*/

const AUTH_SUCCESS = [
    'message',
    'data' => ['token', 'token_type', 'user' => ['id', 'name', 'email', 'role']],
    'meta' => ['request_id'],
];

const ERROR_ENVELOPE = ['message', 'error_code', 'errors', 'meta' => ['request_id']];

function registrationPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Shiwa',
        'email' => 'member@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ], $overrides);
}

function memberWithPassword(string $password = 'secret123'): User
{
    $user = User::factory()->create(['password' => Hash::make($password)]);
    $user->assignRole('member');

    return $user;
}

/** Logs in through the real endpoint so the token is a genuine PersonalAccessToken. */
function bearerTokenFor(User $user, string $password = 'secret123'): string
{
    return test()->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => $password,
    ])->json('data.token');
}