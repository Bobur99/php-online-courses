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
