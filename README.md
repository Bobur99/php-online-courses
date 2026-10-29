# PHP Online Courses

Backend API for an online learning platform built with Laravel and PostgreSQL.

## Project Overview

PHP Online Courses is a backend project for an online learning platform. It is being developed as a step-by-step learning project with an emphasis on clean architecture, secure API design, automated testing, and maintainable code.

The project requirements are documented in [`docs/requirements.md`](docs/requirements.md), and the architecture is described in [`docs/architecture.md`](docs/architecture.md).

## Technology Stack

- PHP 8.5
- Laravel 13
- PostgreSQL 16
- Docker Compose
- Nginx
- PHP-FPM
- PHPUnit

## Implemented Features

- RESTful JSON API
- User authentication and role-based access control
- Course and category management
- Lessons and learning progress tracking
- Course purchases and payment provider integration
- Payment webhook verification
- Asynchronous certificate generation
- Automated tests

Implementation details and deployment notes are documented in
[`docs/decisions.md`](docs/decisions.md).

## Authorization: RBAC and Policies

Backend authorization uses database-backed roles and permissions:

```text
User -> Role -> Permission -> Laravel Gate -> Policy (ownership / paid access)
```

The three roles remain `admin`, `teacher`, and `student`. Each user has one
role, and the API retains its existing string `role`. The RBAC migration
adds the reference tables and initializes default grants automatically;
existing users, tokens, purchases, and learning progress are preserved.

Code entry points for review:

- [Role and permission matrix](database/seeders/RbacSeeder.php)
- [Permission names](app/Enums/PermissionName.php)
- [User role relationship and permission lookup](app/Models/User.php)
- [Gate registration](app/Providers/AppServiceProvider.php)
- [Course ownership policy](app/Policies/CoursePolicy.php)
- [RBAC regression tests](tests/Feature/RbacTest.php)
- [Migration compatibility tests](tests/Feature/RbacMigrationTest.php)

For an existing installation, apply the additive migration:

```bash
docker compose exec app php artisan migrate
```

## Development Principles

- Follow the approved project requirements.
- Work incrementally, one task at a time.
- Use test-driven development for business functionality.
- Apply Laravel conventions and established software engineering principles.
- Protect credentials and other sensitive configuration.
- Avoid unnecessary features and unrelated refactoring.

## Getting Started

Copy `.env.example` to `.env`, install Composer dependencies, configure the
application key, then start Docker and apply migrations. The provider uses
`PAYMENT_PROVIDER_WEBHOOK_SECRET` from the same `.env` as Laravel.

Start each frontend with `npm run dev` from its own directory:

- `frontend/apps/course-platform`: http://localhost:5174
- `frontend/apps/payment-platform`: http://localhost:5173

The API runs on http://localhost and the provider API on http://localhost:8080.
The UIs remain independent; payment confirmation is delivered by a signed
server-to-server webhook. Local email uses the log transport by default.

## Project Status

**In development.**

## License

No project license has been specified yet.
