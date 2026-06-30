# Library Management System API

A RESTful Laravel API for managing a library's catalog, members, borrowing
workflow, reservations, and fines — built to demonstrate concurrency-safe
transactions, idempotent writes, HATEOAS, and OpenAPI-documented endpoints.

## Status

🚧 Early development — scaffolding in progress.

## Tech Stack (planned)

- PHP 8.3, Laravel 12
- MySQL 8, Redis
- Sanctum (auth), spatie/laravel-permission (RBAC)
- l5-swagger (OpenAPI docs), Pest (testing), Larastan (static analysis)

## Local Setup

\`\`\`bash
git clone https://github.com/<your-username>/library-management-api.git
cd library-management-api
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
\`\`\`

## License

MIT