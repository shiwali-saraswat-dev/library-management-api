<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

/**
 * Fails CI when annotations break (bad $ref, duplicate schema name, syntax error),
 * so the published Swagger UI can never silently go stale.
 */
it('generates an OpenAPI spec that documents the auth contract', function () {
    expect(Artisan::call('l5-swagger:generate'))->toBe(0);

    $file = storage_path('api-docs/api-docs.json');
    expect($file)->toBeFile();

    /** @var array{paths: array<string, mixed>, components: array{schemas: array<string, mixed>, responses?: array<string, mixed>}} $spec */
    $spec = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);

    expect($spec['paths'])->toHaveKeys(['/auth/register', '/auth/login', '/auth/refresh', '/auth/logout'])
        ->and($spec['components']['schemas'])->toHaveKeys(['ApiMeta', 'ApiError', 'AuthPayload', 'AuthResponse'])
        ->and($spec['components']['responses'] ?? [])->toHaveKeys(['Unauthenticated', 'RateLimited']);
});
