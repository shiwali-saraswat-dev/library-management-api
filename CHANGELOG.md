# Changelog

All notable changes to this project are documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Planned
- Feature tests for Books, Categories, Authors, Members and Borrowings (following the pattern already used in `AuthTest`)
- Complete `@OA` (Swagger) annotations for the Members module
- Permission tests: one test per role per endpoint, per the access-control tables in the README

## [1.0.0] - 2026-11-XX
Initial release. A complete, versioned REST API covering the library's core catalog, members and borrowing workflow.

### Added
- **Auth module:** register, login, refresh, logout via Laravel Sanctum token authentication
- **Books, Categories, Authors modules:** full CRUD, soft delete, restore, and force delete, with cursor pagination, search, sorting and filtering on list endpoints
- **Members module:** full CRUD, soft delete, restore, force delete, with a guard that blocks deleting a member who has active borrowings
- **Borrowings module:** borrow and return flow
- **Role-based access control:** four-tier hierarchy (owner, admin, librarian, member) via spatie/laravel-permission, enforced through Laravel Policies
- **Consistent JSON envelope** for both success and error responses, including `message`, `error_code`, `errors`, and `meta.request_id`
- **HATEOAS-style `_links`** on resources, scoped to what the calling user is actually permitted to do
- **HTTP caching** on detail endpoints (`ETag`, `Cache-Control`, `304 Not Modified`)
- **Layered rate limiting** on login, configured in one place and applied via custom middleware and Laravel's `throttle`
- **Request tracing:** every response carries an `X-Request-Id` header, echoed consistently in the response body and in log context
- **Centralized API exception handling** via `ApiExceptionRenderer`, mapping domain exceptions to consistent HTTP status codes and error codes
- **OpenAPI documentation** via l5-swagger, covering Auth, Books, Categories and Authors
- **GitHub Actions CI pipeline**, running PHPStan (Larastan rules, level 6) and the Pest test suite on every push and pull request
- **Pest test suite** covering the Auth module end to end: registration (with rollback safety if role assignment fails), login (including identical responses for a wrong password vs. an unknown email, to prevent account enumeration), token refresh and revocation, logout, and request-id tracing

### Known limitations
- Test coverage is currently limited to the Auth module; other modules are implemented and manually verified via Postman but not yet covered by automated tests
- Swagger (`@OA`) annotations are not yet written for the Members module
