# Library Management API

A RESTful Laravel API for managing a library's catalog, members and borrowings. It is a small portfolio project that focuses on clean REST design: consistent responses, authentication and permissions, pagination, caching, rate limiting and safe transactions.

![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8-4479A1?logo=mysql&logoColor=white)
![License](https://img.shields.io/badge/license-MIT-green)

## Highlights

- **Versioned REST API** under `/api/v1` with one consistent JSON envelope for successes and errors
- **Token authentication** (Laravel Sanctum) and **role-based permissions** (owner, admin, librarian, member)
- **Layered architecture:** Controller → Service → Repository, with Form Requests, API Resources and Policies
- **Cursor pagination**, search, sorting and filtering on list endpoints
- **HATEOAS-style `_links`** that only show actions the current user is allowed to perform
- **HTTP caching** on detail endpoints (`ETag`, `Cache-Control`, `304 Not Modified`)
- **Layered rate limiting** on login, driven by one config file
- **Soft delete** with a two-step restore / force-delete safety gate
- **Transactional borrow and return flow** that cannot hand out the last copy twice *(in progress)*

## Status

| Module | Status |
|---|---|
| Auth (register, login, refresh, logout) | ✅ Done |
| Categories | ✅ Done |
| Books | ✅ Done |
| Members | ✅ Done |
| Authors | 🚧 In progress |
| Borrowings (borrow / return) | 🚧 Planned next |

See [docs/PROJECT_PLAN.md](docs/PROJECT_PLAN.md) for scope, data model and milestones.

## Tech stack

- PHP 8.3, Laravel 12
- MySQL 8
- Laravel Sanctum (authentication)
- spatie/laravel-permission (roles and permissions)
- l5-swagger (OpenAPI docs)
- Pest (tests) and Larastan (static analysis)

## Getting started

**Requirements:** PHP 8.3+, Composer, MySQL 8.

```bash
git clone https://github.com/shiwali-saraswat-dev/library-management-api.git
cd library-management-api

composer install
cp .env.example .env
php artisan key:generate
```

Create an empty MySQL database, then set `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD` in `.env`.

```bash
php artisan migrate --seed
php artisan serve
```

The API is now available at `http://127.0.0.1:8000/api/v1`.

> Running under XAMPP instead? The base URL is `http://localhost/library-management-api/api/v1`.

### Demo accounts (local seed data only)

| Role | Email | Password |
|---|---|---|
| Owner | `owner@library.test` | `owner@123` |
| Admin | `admin@library.test` | `admin@123` |
| Librarian | `librarian@library.test` | `librarian@123` |
| Member | `aisha@gmail.com` | `member@123` |

## Quick tour

**1. Log in and get a token**

```bash
curl -X POST http://127.0.0.1:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email":"librarian@library.test","password":"librarian@123"}'
```

**2. Call a protected endpoint**

```bash
curl http://127.0.0.1:8000/api/v1/members?per_page=5&sort_by=name \
  -H "Authorization: Bearer <token>" \
  -H "Accept: application/json"
```

## API overview

Base path: `/api/v1`. Every endpoint except register and login needs `Authorization: Bearer <token>`.

| Area | Endpoints |
|---|---|
| Auth | `POST /auth/register` · `POST /auth/login` · `POST /auth/refresh` · `POST /auth/logout` |
| Books | `GET /books` · `POST /books/store` · `GET /books/view/{id}` · `PUT /books/update/{id}` · `DELETE /books/destroy/{id}`, plus `GET /books/trashed`, `PATCH /books/restore/{id}` and `DELETE /books/force-delete/{id}` |
| Categories | same pattern on `/categories` |
| Authors | same pattern on `/authors` |
| Members | same pattern on `/members` |
| Borrowings | `POST /borrowings` · `PATCH /borrowings/{id}/return` · `GET /borrowings` *(planned)* |

The full, always-current list lives in the Swagger UI and the Postman collection (see [API documentation](#api-documentation)).

### Listing, searching and sorting

List endpoints accept the same query parameters. Example for members:

| Parameter | Values | Default |
|---|---|---|
| `search` | text, min 2 characters | none |
| `sort_by` | `name`, `email`, `created_at`, `updated_at` | `name` |
| `sort_dir` | `asc`, `desc` | `asc` |
| `per_page` | 1–100 | 15 |
| `is_active` | `true`, `false` | none |
| `trashed` | `only_deleted`, `with_deleted` | active records only |
| `cursor` | value from `meta.next_cursor` | first page |

Pagination is **cursor-based**: send the `next_cursor` from the previous response to get the next page.

### Response format

**Success (list)**

```json
{
  "success": true,
  "data": [ { "id": 4, "name": "Aisha Khan", "membership_number": "MEM-00004", "_links": { "self": { "href": "…/members/4" } } } ],
  "meta": {
    "per_page": 15,
    "next_cursor": "eyJpZCI6…",
    "prev_cursor": null,
    "has_more": true,
    "filters": { "sort_by": "name", "sort_dir": "asc", "per_page": 15 },
    "request_id": "req_6af6d55c3be69",
    "api_version": "v1",
    "timestamp": "2026-11-13T09:16:12+00:00"
  },
  "_links": { "self": { "href": "…" }, "next": { "href": "…" } }
}
```

**Error**

```json
{
  "message": "The given data was invalid.",
  "error_code": "VALIDATION_ERROR",
  "errors": { "email": ["A user with this email already exists."] },
  "meta": { "request_id": "req_ab12cd34" }
}
```

| Status | Meaning |
|---|---|
| `401` | Missing or invalid token |
| `403` | Authenticated but not allowed |
| `404` | Resource not found |
| `409` | Business rule conflict (for example, deleting a member who has active borrowings) |
| `422` | Validation failed |
| `429` | Rate limit exceeded |

## Rate limiting

Limits are defined once in `config/rate_limits.php` and applied through a custom middleware and Laravel's `throttle`.

| Layer | Applies to | Limit | Keyed by |
|---|---|---|---|
| General API guard | all `/api/v1` routes | 60 / minute | user id, or IP for guests |
| Login guard | `POST /auth/login` | 5 / minute | IP address |
| Login brute-force guard | `POST /auth/login` | 2 / minute | email + IP |

When a limit is hit the API returns `429` with `Retry-After`, `X-RateLimit-Limit` and `X-RateLimit-Remaining` headers and a JSON body with `retry_after` and `retry_at`.

## HTTP caching

Member detail responses send an `ETag` and `Cache-Control: private, max-age=60`. A client that repeats the request with `If-None-Match` receives an empty `304 Not Modified` when nothing changed. The ETag includes the caller's roles, so a response cached for a librarian is never reused for a member.

## Roles and permissions

Four roles (owner, admin, librarian, member) with permissions named `<resource>.<action>`, for example `member.create` or `book.force_delete`. Access is enforced by Laravel Policies, and the `_links` in each response only include actions the caller may perform.

Deleting is a two-step process: a record must be **soft deleted** before it can be **restored** or **force deleted**.

## Architecture

```
app/
├── Exceptions/                 # Domain exceptions mapped to JSON error codes
├── Http/
│   ├── Controllers/Api/V1/     # Thin controllers
│   ├── Middleware/             # ApiRateLimit
│   ├── Requests/Api/V1/        # Form Requests (validation + authorization)
│   └── Resources/Api/V1/       # JSON shape, _links
├── Models/
├── Policies/
├── Repositories/               # Database queries
└── Services/                   # Business rules
```

**Request flow:** Route → middleware (auth, rate limit) → Form Request → Controller → Service → Repository → Model → API Resource.

**Members are users.** Staff and members share the `users` table. A global scope on the `Member` model returns only users with the `member` role.

## Testing
```bash
php artisan test
```

## API documentation

- **Swagger UI:** run `php artisan l5-swagger:generate`, then open `/api/documentation`
- **Postman collection:** [docs/postman/library_management_api.postman_collection.json](docs/postman/library_management_api.postman_collection.json)

## Roadmap

- [ ] Borrow and return flow with transaction and row locking
- [ ] Authors endpoints
- [ ] Feature test suite and CI

**Not planned:** reservations, fines, notifications and a frontend. They were left out on purpose to keep the project focused on core REST design.

## License

MIT

---

Built by [@shiwali-saraswat-dev](https://github.com/shiwali-saraswat-dev)