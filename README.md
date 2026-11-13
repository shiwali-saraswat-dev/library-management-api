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
- **Transactional borrow and return flow** that cannot hand out the last copy twice *(planned)*

## Status

| Module | Status |
|---|---|
| Auth (register, login, refresh, logout) | ✅ Done |
| Categories | ✅ Done |
| Books | ✅ Done |
| Authors | ✅ Done |
| Members | ✅ Done |
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

The seeder also creates 20 extra members through a factory, and a few soft-deleted authors, categories and books so the `trashed`, `restore` and `force-delete` endpoints can be tried straight away.

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
curl "http://127.0.0.1:8000/api/v1/members?per_page=5&sort_by=name" \
  -H "Authorization: Bearer <token>" \
  -H "Accept: application/json"
```

**3. See the permission-aware links**

Log in as the member and as the librarian, then fetch the same author. The librarian's `_links` include `update` and `destroy`; the member's only include `self`.

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

## Access control: who can call what

Legend: ✅ allowed · ❌ `403 Forbidden` · **own** = only the caller's own record.

Permissions are named `<resource>.<action>` (for example `author.update`) and checked by Laravel Policies. A user with no token always gets `401`.

### Quick summary by module

| Module | Owner | Admin | Librarian | Member |
|---|---|---|---|---|
| Auth | Full | Full | Full | Full |
| Authors | Full | Everything except force delete | Create, edit, soft delete, restore | Read only |
| Categories | Full | Everything except force delete | Create, edit, soft delete, restore | Read only |
| Books | Full | Everything except force delete | Create, edit, soft delete, restore | Read only |
| Members | Full | Everything except force delete | Create, edit, soft delete, restore | View and edit **own** profile |
| Borrowings *(planned)* | Full | Full | Borrow, return, view all | View **own** |

### Auth

| Endpoint | Who can call it |
|---|---|
| `POST /auth/register` | Public. New accounts always get the `member` role; a role can never be sent in the request. |
| `POST /auth/login` | Public (rate limited) |
| `POST /auth/refresh` | Any logged-in user |
| `POST /auth/logout` | Any logged-in user |

### Authors, Categories and Books

Authors are a **separate catalog table**, not users: an author has no login and no role. The `author.*` permissions only control which staff can manage author records. Authors, Categories and Books all follow the same rules, so one table covers all three (replace `{resource}` with `authors`, `categories` or `books`; the permission prefix is `author`, `category` or `book`).

| Endpoint | Permission | Owner | Admin | Librarian | Member |
|---|---|:-:|:-:|:-:|:-:|
| `GET /{resource}` | `list` | ✅ | ✅ | ✅ | ✅ |
| `GET /{resource}/view/{id}` | `view` | ✅ | ✅ | ✅ | ✅ |
| `POST /{resource}/store` | `create` | ✅ | ✅ | ✅ | ❌ |
| `PUT /{resource}/update/{id}` | `update` | ✅ | ✅ | ✅ | ❌ |
| `DELETE /{resource}/destroy/{id}` | `delete` | ✅ | ✅ | ✅ | ❌ |
| `GET /{resource}/trashed` | `restore` | ✅ | ✅ | ✅ | ❌ |
| `PATCH /{resource}/restore/{id}` | `restore` | ✅ | ✅ | ✅ | ❌ |
| `DELETE /{resource}/force-delete/{id}` | `force_delete` | ✅ | ❌ | ❌ | ❌ |

The `trashed` query parameter on list endpoints (`only_deleted`, `with_deleted`) needs the same permission as `GET /{resource}/trashed`. A member who sends it gets `403`.

### Members

| Endpoint | Permission | Owner | Admin | Librarian | Member |
|---|---|:-:|:-:|:-:|:-:|
| `GET /members` | `member.list` | ✅ | ✅ | ✅ | ❌ |
| `GET /members/view/{id}` | `member.view` | ✅ | ✅ | ✅ | **own** |
| `POST /members/store` | `member.create` | ✅ | ✅ | ✅ | ❌ |
| `PUT /members/update/{id}` | `member.update` | ✅ | ✅ | ✅ | **own** (profile fields only) |
| `DELETE /members/destroy/{id}` | `member.delete` | ✅ | ✅ | ✅ | ❌ |
| `GET /members/trashed` | `member.restore` | ✅ | ✅ | ✅ | ❌ |
| `PATCH /members/restore/{id}` | `member.restore` | ✅ | ✅ | ✅ | ❌ |
| `DELETE /members/force-delete/{id}` | `member.force_delete` | ✅ | ❌ | ❌ | ❌ |

A member editing their own profile cannot change `is_active`, `membership_number` or roles. Deleting a member who has an active borrowing returns `409`.

### Borrowings *(planned)*

| Endpoint | Permission | Owner | Admin | Librarian | Member |
|---|---|:-:|:-:|:-:|:-:|
| `GET /borrowings` | `borrowing.list` | ✅ | ✅ | ✅ | **own** |
| `GET /borrowings/{id}` | `borrowing.view` | ✅ | ✅ | ✅ | **own** |
| `POST /borrowings` | `borrowing.create` | ✅ | ✅ | ✅ | ❌ |
| `PATCH /borrowings/{id}/return` | `borrowing.return` | ✅ | ✅ | ✅ | ❌ |

### Rules behind the tables

1. **Deny by default.** A route with no matching permission is forbidden.
2. **Ownership.** `own` is enforced in the Policy and in the query scope, so a member can never list or fetch another member's data.
3. **Two-step delete.** A record must be soft deleted before it can be restored or force deleted.
4. **Irreversible actions are owner-only.** Only the owner can force delete.
5. **No privilege escalation through the API.** No endpoint assigns roles; roles are set by seeders or by the owner outside the API.
6. **Inactive users are locked out.** An inactive or soft-deleted user cannot log in and their tokens are revoked.

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

Four roles (owner, admin, librarian, member) and one permission per action, named `<resource>.<action>`. Permissions are stored with [spatie/laravel-permission](https://spatie.be/docs/laravel-permission) on the `sanctum` guard and seeded by `RolePermissionSeeder`.

| Role | Intended for | Scope |
|---|---|---|
| Owner | The library owner | Everything, including force delete |
| Admin | Branch manager | Everything except force delete |
| Librarian | Front desk | Day-to-day catalog and member work; no force delete |
| Member | Library patron | Read the catalog, manage own profile, view own borrowings |

Access is enforced by Laravel Policies, and the `_links` in each response only include actions the caller may perform (for example, `restore` appears only on a soft-deleted record and only for users with `*.restore`).

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

**Authors are not users.** Authors, categories and books live in their own catalog tables (`authors`, `categories`, `books`). An author has no account, no role and no login. Each book belongs to one author and one category.

## Testing

```bash
php artisan test
```

## API documentation

- **Swagger UI:** run `php artisan l5-swagger:generate`, then open `/api/documentation`
- **Postman collection:** [docs/postman/library_management_api.postman_collection.json](docs/postman/library_management_api.postman_collection.json)

## Roadmap

- [x] Auth, Categories, Books, Authors and Members endpoints
- [ ] Borrow and return flow with transaction and row locking
- [ ] Permission tests: one test per role and endpoint from the access tables above
- [ ] Feature test suite and CI

**Not planned:** reservations, fines, notifications and a frontend. They were left out on purpose to keep the project focused on core REST design.

## License

MIT

---

Built by [@shiwali-saraswat-dev](https://github.com/shiwali-saraswat-dev)
