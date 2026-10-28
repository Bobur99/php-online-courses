# Backend Architecture 

## Contents

1.  [Purpose and scope](#1-purpose-and-scope)
2.  [Technology baseline](#2-technology-baseline)
3.  [General request flow](#3-general-request-flow)
4.  [Authentication and users](#4-authentication-and-users)
5.  [Categories](#5-categories)
6.  [Courses](#6-courses)
7.  [Lessons and learning progress](#7-lessons-and-learning-progress)
8.  [Purchases and payment
    integration](#8-purchases-and-payment-integration)
9.  [Certificates](#9-certificates)
10. [Events, listeners, and queues](#10-events-listeners-and-queues)
11. [Validation, authorization, errors, and
    logging](#11-validation-authorization-errors-and-logging)
12. [Database design principles](#12-database-design-principles)
13. [Implementation workflow](#13-implementation-workflow)
14. [Open
    decisions](#14-open-decisions-to-confirm-during-implementation)
15. [Definition of architectural
    completion](#15-definition-of-architectural-completion)

------------------------------------------------------------------------

------------------------------------------------------------------------

## 1. Purpose and scope

This document describes the implemented architecture for the backend API of
`php-online-courses`.

The technical specification (`docs/requirements.md`) is the source of
truth. This document explains how the required functionality is
organized; it does not introduce additional product features.

**Current stage:** implementation and verification. See `docs/decisions.md`
for the payment, private-material, and notification decisions.

------------------------------------------------------------------------

## 2. Technology baseline

-   PHP 8.5.x
-   Laravel 13.x
-   PostgreSQL 16
-   Docker Compose
-   Nginx and PHP-FPM
-   REST API returning JSON
-   Laravel Storage for uploaded materials and generated PDF files
-   Laravel queues for required background jobs
-   A separate Mock Payment Provider application communicating with the
    main backend over HTTP

Keep installed package versions consistent with the existing Composer
configuration and lock file. Do not upgrade or replace technologies
without approval.

------------------------------------------------------------------------

## 3. General request flow

For a typical API request, the responsibility flow is:

1.  **Route / middleware** --- selects the endpoint and applies
    authentication or other required middleware.
2.  **Form Request** --- validates request input where a dedicated
    request class is useful.
3.  **Authorization** --- a Policy or Gate checks whether the current
    user may perform the operation.
4.  **Controller** --- handles HTTP concerns and returns the appropriate
    JSON response and status code.
5.  **Service, when justified** --- coordinates a business operation
    that is more involved than straightforward CRUD.
6.  **Eloquent model / external service** --- persists data or
    communicates with the payment provider.
7.  **Response** --- returns only the data required by the API contract.

This is a responsibility guide, not a requirement to create a separate
class for every step. Keep straightforward CRUD in the controller and
Eloquent where appropriate. Do not add repositories, DTOs, factories,
interfaces, traits, or services without a concrete need.

------------------------------------------------------------------------

## 4. Authentication and users

### Responsibilities

-   `AuthController`: HTTP endpoints for registration, login, and
    logout.
-   `AuthService`: provisionally coordinates registration, login, and
    logout if this keeps authentication workflow cohesive. It must not
    duplicate request validation or Laravel's token/authentication
    mechanisms.
-   Separate Form Requests: validate registration and login inputs
    because their rules differ.
-   `User` model: user data, role/block state, relationships, and
    Laravel authentication integration.
-   `UserController`: profile operations and administrative
    user-management HTTP operations.
-   `UserPolicy` and/or explicit role checks: enforce permissions
    server-side.

### Authentication approach

Use a custom API authentication flow, without Breeze, Jetstream,
Fortify, or similar authentication starter kits, as required by the
project rules.

Laravel Sanctum may be used for API tokens, subject to checking
compatibility with the project's actual Laravel and Composer versions
before installing or changing dependencies. Sanctum is an authentication
mechanism, not an authentication starter kit.

### Security rules

-   Passwords must be stored using Laravel's secure password hashing
    mechanisms.
-   Users must not be able to assign themselves an administrative role
    through profile updates.
-   Users must not be able to unblock themselves or bypass restrictions.
-   Administrative actions require server-side authorization.
-   A blocked user must not be able to bypass the block by using an
    existing or newly issued token.
-   Profile update validation must not expose role or block-state
    changes to ordinary users.

The exact endpoint paths and response schemas must follow the
requirements and be confirmed during API implementation; do not invent
additional endpoints.

------------------------------------------------------------------------

## 5. Categories

-   `CategoryController`: HTTP CRUD operations for categories.
-   `Category` model: category data and relationships.
-   Form Requests: validate category input where appropriate.
-   Authorization: apply the permissions specified in the requirements.

Do not add category features that are not required by the specification.

------------------------------------------------------------------------

## 6. Courses

-   `CourseController`: HTTP operations for listing, viewing, creating,
    updating, deleting, and publishing courses, as required by the API
    contract.
-   `Course` model: course data and relationships with its author,
    category, lessons, and purchases.
-   Form Requests: validate course input.
-   `CoursePolicy`: enforce course ownership and administrator
    permissions.
-   Eloquent queries: implement search by title, category/teacher
    filters, and sorting by price/date as specified.

### Access rules

-   Administrators may manage courses within the permissions defined by
    the requirements.
-   Teachers may manage only their own courses, subject to the
    requirements.
-   Students may not create, update, or delete courses.
-   Only an authorized author or administrator may publish a course.
-   Only published courses may be purchased.

Do not create `CourseService` solely to wrap simple CRUD. Add a service
only if a real business operation requires orchestration.

------------------------------------------------------------------------

## 7. Lessons and learning progress

### Lessons

-   `LessonController`: HTTP operations for listing and managing
    lessons.
-   `Lesson` model: lesson title, content, order number, optional
    material path, and relationship to a course.
-   `LessonPolicy`: enforce permissions for lesson operations.
-   Form Requests: validate lesson input.
-   Laravel Storage: store uploaded lesson materials; persist the
    storage path in the database, not the file bytes.

Access to lesson content and materials must follow the requirements,
including course access and ownership rules. The precise access policy
must not be broadened beyond the specification.

Public lesson lists expose the program, not lesson content or file paths.
Authors, administrators, and paid students may read content. Drafts are
hidden from other users. Materials use the private disk and
`GET /api/lessons/{lesson}/material` with Sanctum and LessonPolicy.
Nginx blocks legacy `/storage/lessons/` links. Existing installations run
`php artisan materials:privatize` to move verified copies to private storage.

### Progress

-   A progress record represents one user's completion state for a
    lesson.
-   Enforce uniqueness for the relevant user/lesson pair at the database
    level where the approved schema supports it.
-   Prevent duplicate completion records when a user repeats a
    completion request.
-   A course is completed when all lessons belonging to that course are
    completed by that user.
-   Emit `CourseCompleted` when the completion condition is met, without
    repeatedly triggering the business effect for duplicate requests.

The schema design must confirm how completion records relate to users,
lessons, and courses before migrations are written.

------------------------------------------------------------------------

## 8. Purchases and payment integration

### Main application responsibilities

-   `PurchaseController`: receives purchase-related HTTP requests and
    returns API responses.
-   `PurchaseService`: coordinates purchase creation and payment
    workflow.
-   `Purchase` model: stores purchase status, course/user relationships,
    and the provider's operation identifier.
-   `PaymentProviderInterface`: defines the operations the main application
    needs from a payment provider.
-   A concrete provider adapter: performs HTTP communication with the
    Mock Payment Provider.
-   Webhook handler/controller: receives provider notifications and
    delegates verification and state changes to the appropriate
    application logic.

Keep provider-specific HTTP details and signature handling isolated from
the core purchase workflow where practical. Do not create additional
layers unless implementation demonstrates a real benefit.

Purchase creation locks the student's row before checking for existing paid
or pending purchases. Failed attempts remain in history and a retry creates
a new operation. Provider failures return 503 and roll back local creation.
Webhook verification uses HMAC-SHA256 over the raw request body, validates
the operation and status, and locks the purchase before changing it.
Terminal results are immutable; identical notifications are idempotent.

The purchase API still returns `payment_url` as required, but the course UI
does not follow it. Both UIs remain on their own origins. The course checkout
polls its own backend; only a verified webhook grants access.

### Separate Mock Payment Provider

The Mock Payment Provider is a standalone application with its own HTTP
API. It is not merely a class inside the Laravel application.

Its contract must be defined before integration and must cover, as
required by the specification:

-   creating a payment operation;
-   returning a provider operation identifier (`operation_id`) and
    payment URL (`payment_url`);
-   simulating the payment result;
-   notifying the main application through a webhook;
-   signing notifications using a shared secret or other agreed provider
    contract.

The exact request/response fields, routes, secret configuration, and
signature algorithm must be documented when the provider API is
designed. Do not assume an undocumented contract.

### Payment security and reliability

-   A course must be published before it can be purchased.
-   Never trust a payment status supplied by the browser or frontend.
-   Verify the webhook signature before changing purchase state.
-   Validate that the referenced operation belongs to the expected
    purchase and that the status transition is allowed.
-   Make webhook processing idempotent so a repeated notification does
    not duplicate business effects.
-   Grant course access only after a verified successful payment.
-   Handle provider timeouts, HTTP errors, malformed responses, and
    signature failures without falsely marking a purchase as paid.
-   Use database transactions for related database changes where needed.
-   Dispatch post-payment effects only after the successful state change
    has been persisted reliably.

The detailed state machine and failure behavior must be agreed during
payment implementation and tested.

------------------------------------------------------------------------

## 9. Certificates

-   Certificate data is associated with the user and completed course
    according to the approved schema.
-   Generate the required certificate as a PDF using a background job.
-   Store the PDF using Laravel Storage and keep its path in the
    database.
-   Provide only the download behavior required by the API
    specification.
-   Enforce authorization before allowing a certificate to be
    downloaded.
-   Avoid storing PDF bytes directly in the database.

Do not add certificate fields or lifecycle states unless required by the
specification or necessary for correct implementation.

### Student-facing API projections

-   `GET /api/student/courses` returns only the authenticated student's
    paid courses and derives lesson totals, completed lesson totals, and
    progress percentage from the existing course and progress records.
-   `GET /api/certificates` returns only the authenticated student's
    certificates with course metadata and an authorized download URL.
-   Lesson listing includes the authenticated student's completion flag;
    it does not create a separate progress state or duplicate stored data.

------------------------------------------------------------------------

## 10. Events, listeners, and queues

Use the events required by the specification:

-   `UserRegistered`
-   `CoursePublished`
-   `CoursePurchased`
-   `CourseCompleted`

Events represent facts that have occurred; they should not replace the
primary business operation.

Use background jobs for the required asynchronous work:

-   certificate PDF generation;
-   the required post-payment notification.

The exact listener/job mapping should be kept simple. Avoid creating
events, listeners, or jobs that have no required purpose. Ensure that
events and jobs are not dispatched as if a database operation succeeded
when it actually failed.

------------------------------------------------------------------------

## 11. Validation, authorization, errors, and logging

-   Use Form Requests for input validation where appropriate.
-   Use Policies/Gates and server-side checks for authorization.
-   Return consistent JSON responses and appropriate HTTP status codes.
-   Do not expose stack traces, secrets, or internal exception details
    to clients.
-   Log unexpected errors with useful context while avoiding passwords,
    tokens, payment secrets, and other sensitive values.
-   Handle expected external-service failures explicitly.
-   Do not add broad `catch` blocks that hide failures or make an
    operation appear successful.
-   Test important success paths, authorization failures, validation
    failures, payment-provider failures, and webhook edge cases.

------------------------------------------------------------------------

## 12. Database design principles

Database schema design is a separate next step and must be based on the
requirements and this architecture.

For each proposed table, review:

-   required columns and appropriate types;
-   nullability and defaults;
-   foreign keys and deletion behavior;
-   uniqueness constraints;
-   indexes for relationships, lookup, and required search/filter/sort
    operations;
-   status representation and allowed transitions;
-   consistency rules that must be enforced by the database as well as
    application code.

First inspect existing Laravel migrations and current database state. Do
not blindly delete, rewrite, or duplicate existing migrations. Review
the proposed schema before executing migrations.

### Approved database design decisions

- Users have the roles `admin`, `teacher`, and `student`, plus an `is_blocked` flag. Blocked users must not be able to log in or access protected API operations, including with existing credentials or tokens.
- Only administrators may manage categories.
- A course has one author and one category. Course statuses are `draft` and `published`.
- A student can have multiple purchase attempts for a course, preserving failed attempts as history. Creation is serialized per student and blocked if any paid or pending purchase already exists. A retry after failure creates a new provider operation.
- The application uses UZS as its single currency. Purchase amount is captured when the purchase is created.
- Course access is granted only after a verified successful payment notification.
- Lesson completion is unique for each student/lesson pair. A course is completed when all its lessons are completed by the student.
- A student may have only one certificate for a given course. Certificate generation is asynchronous, and duplicate job execution must not create duplicate certificates.
- Existing Laravel migrations must be preserved. Schema changes to existing tables must use new migrations.
- The proposed schema must be reviewed and approved before any new migrations are created.

### Implemented database tables

Business tables:
- `users` — extend the existing table with role and blocked-user fields.
- `categories` — course categories.
- `courses` — course details, author, category, price, and status.
- `lessons` — course lessons and optional material path.
- `purchases` — student, course, amount, payment operation identifier, and purchase status.
- `lesson_progress` — student lesson-completion records.
- `certificates` — student, course, and generated PDF path.

Existing Laravel infrastructure tables for cache and queues remain unchanged unless implementation reveals a specific requirement to modify them.

------------------------------------------------------------------------

## 13. Implementation workflow

Follow the project rules and work in small, reviewable steps:

1.  Confirm the requirements and inspect the existing code.
2.  Agree on architecture and document significant decisions.
3.  Design and review the database schema.
4.  Create/review migrations and verify the resulting schema.
5.  Implement one business feature at a time using TDD:
    -   write a focused test;
    -   run it and confirm the expected failure;
    -   implement the minimum correct solution;
    -   run tests and confirm success;
    -   refactor only where useful and retest.
6.  Review authorization, errors, regressions, and changed files.
7.  Make a logical Git commit only after the step is complete.

Do not implement the whole project in one step. Do not weaken tests or
omit required functionality to compensate for schedule pressure; raise
schedule risks early.

------------------------------------------------------------------------

## 14. Open decisions to confirm during implementation

The following details must be checked against the full requirements and
existing project before implementation; this document does not decide
them by assumption:

-   Exact API routes and JSON response schemas.
-   Exact role and blocked-user representation in the database.
-   Exact course and purchase status values and allowed transitions.
-   Exact policy for lesson/material access and purchased-course access.
-   Exact payment provider API contract and webhook signature format.
-   Exact database representation of learning progress and certificates.
-   Queue driver and operational setup supported by the current
    environment.
-   Sanctum compatibility with the existing dependency versions.

Any ambiguity or conflict with `docs/requirements.md` must be surfaced
for approval rather than silently resolved by adding requirements.

------------------------------------------------------------------------

## 15. Definition of architectural completion

Architecture is ready for the next stage when:

-   it has been compared against `docs/requirements.md`;
-   no known requirement has been omitted;
-   no unapproved feature has been introduced;
-   module responsibilities and payment boundaries are understood;
-   open decisions are identified rather than guessed;
-   the database schema can be designed from the approved requirements
    and architecture.

Continue validating changes against the specification with regression tests,
including authorization, provider outages, webhook delivery, and UI behavior.
