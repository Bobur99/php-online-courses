# Project Engineering Guidelines

## 1. Project Scope

The functional scope of this project is defined by the provided technical specification (ТЗ).

Rules:

- Do not add business functionality that is not required by the ТЗ.
- Do not remove required functionality.
- Do not introduce speculative features, tables, fields, endpoints, services, abstractions, or integrations.
- If the ТЗ is ambiguous or contradictory, identify the issue and discuss it before implementation.
- Technical implementation may be improved when it does not change the required business behavior.

The ТЗ is the source of truth for business requirements.

---

## 2. Technical Baseline

The project uses:

- PHP 8.5.x
- Laravel 13.x
- PostgreSQL 16
- Docker / Docker Compose
- Nginx
- PHP-FPM

Do not upgrade, downgrade, or replace major technologies without an explicit decision.

Keep versions consistent with the project configuration and `composer.lock`.

---

## 3. Development Workflow

Work step-by-step.

For each task:

1. Understand the requirement.
2. Inspect the existing code and project structure.
3. Identify existing components that can be reused.
4. Propose the implementation approach.
5. Explain important architectural or technical decisions.
6. Write tests for the required behavior.
7. Run the tests and confirm that they fail for the expected reason.
8. Implement the minimum correct functionality.
9. Run the tests again and verify that they pass.
10. Refactor if necessary.
11. Run the relevant tests again.
12. Review the resulting changes.
13. Commit the completed logical change.

Do not implement the entire project at once.

One task → analysis → implementation → check → explanation → Git commit → next task.

---

## 4. Test-Driven Development

Business functionality should follow TDD.

Required approach:

1. Requirement
2. Expected behavior
3. Test
4. Confirm the test fails for the expected reason
5. Implementation
6. Test passes
7. Refactor
8. Test again

Tests must be derived from requirements and observable behavior, not from the implementation details.

Do not weaken, remove, or rewrite a correct test simply to make the implementation pass.

Do not write tests that only verify that a particular implementation was used when the requirement concerns behavior.

TDD is not required for trivial configuration or infrastructure-only changes where automated behavioral testing is not meaningful.

---

## 5. Existing Code First

Before creating a new:

- Service
- DTO
- Repository
- Interface
- Factory
- Trait
- Helper
- Exception
- Middleware
- Event
- Listener
- Job

inspect the existing project first.

Reuse existing functionality when appropriate.

Do not create abstractions only because they appear architecturally sophisticated.

Prefer the simplest solution that is:

- correct
- maintainable
- testable
- consistent with the existing architecture

Apply DRY, KISS, YAGNI, and SOLID pragmatically.

---

## 6. Architectural Consistency

The same type of problem should normally be solved using the same architectural pattern.

Maintain consistency in:

- namespaces
- directory structure
- dependency injection
- validation
- authorization
- API responses
- error handling
- logging
- database access
- tests
- naming conventions

Do not introduce a new pattern when an existing project pattern already solves the problem.

If a different approach is technically preferable, explain why before applying it.

---

## 7. Laravel Conventions

Use Laravel's standard mechanisms where they fit the requirements:

- Form Requests for request validation
- Policies / Gates for authorization
- Eloquent relationships for domain relationships
- Events / Listeners for domain events
- Jobs / Queues for asynchronous work
- Laravel Storage for file storage
- Dependency Injection
- API Resources when they provide a clear benefit
- Laravel logging mechanisms

Do not bypass Laravel mechanisms without a concrete technical reason.

Authentication must follow the ТЗ.

Do not use Breeze, Jetstream, Fortify, or similar authentication starters because the ТЗ requires custom API authentication.

Do not invent custom cryptography when standard secure PHP/Laravel mechanisms are sufficient.

---

## 8. Database

Database design must follow the ТЗ and approved architecture.

Before adding a table or column, verify that it is actually required.

For every schema change consider:

- correct data type
- nullability
- foreign keys
- uniqueness
- indexes
- delete/update behavior
- consistency with existing schema

Avoid speculative tables and fields.

Database migrations must be reviewed before execution.

---

## 9. API

The API follows REST principles and returns JSON.

Keep API behavior consistent across the project:

- HTTP methods
- route naming
- status codes
- request validation
- authorization
- response structure
- error handling

Do not create endpoints that are not required by the ТЗ without explicit agreement.

---

## 10. Security

Security requirements must be implemented on the backend.

At minimum:

- passwords must be securely hashed
- authorization must be enforced server-side
- blocked users must not bypass access restrictions
- input must be validated
- sensitive data must not be exposed unnecessarily
- payment webhooks must validate the required signature
- secrets must not be hardcoded

Use standard secure mechanisms provided by PHP and Laravel.

Do not add unrelated security features unless they are required or explicitly approved.

---

## 11. Payment Integration

Payment integration must follow the ТЗ.

Keep payment-provider-specific implementation isolated from core business logic where practical.

Use interfaces and dependency injection when they provide a real benefit for the payment integration and testing.

Never trust payment status received directly from the client.

Webhook processing must validate the provider signature before changing purchase state.

---

## 12. Events and Queues

Domain events required by the ТЗ must remain explicit and understandable.

Required events include:

- UserRegistered
- CoursePublished
- CoursePurchased
- CourseCompleted

Required asynchronous operations include:

- certificate generation
- post-payment notification

Do not move synchronous logic into queues unless there is a concrete reason.

Do not introduce events or jobs without a real responsibility.

---

## 13. Files and Storage

Files must be handled through Laravel's storage mechanisms.

Do not store file contents directly in the database unless explicitly required.

Database records should contain references such as file paths when appropriate.

File upload and access must be validated and authorized.

---

## 14. Explanation of Decisions

Before significant implementation changes, explain:

1. What will be changed.
2. Why it is needed.
3. Which alternatives exist.
4. Why the selected approach is preferable.
5. How it affects the architecture and ТЗ.

Do not silently make significant architectural decisions.

For trivial syntax or obvious configuration changes, avoid unnecessary explanations.

---

## 15. Documentation

Keep important project decisions documented.

Use:

- `docs/requirements.md` for the structured representation of the ТЗ.
- `docs/architecture.md` for the approved architecture.
- `docs/decisions.md` for important architectural and technical decisions.

Documentation must reflect the actual implementation.

Do not create documentation that contradicts the ТЗ or the code.

---

## 16. Scope Control

Do not perform unrelated refactoring while implementing a task.

If unrelated problems are discovered:

1. Mention them.
2. Explain their impact if relevant.
3. Do not change them unless they block the current task or are explicitly approved.

Keep changes focused and reviewable.

---

## 17. Git

Create small, logical commits.

A commit should represent one completed logical change.

Before committing:

- run relevant tests
- review changed files
- verify that no unrelated changes are included
- ensure the implementation matches the requirement

Do not create commits containing unfinished functionality unless explicitly requested.

---

## 18. Definition of Done

A task is complete when:

- the requirement is implemented
- required tests exist
- tests pass
- authorization and validation are correct
- the implementation follows the existing architecture
- no unnecessary functionality was added
- no unrelated files were changed
- important decisions are understood and documented when necessary
- the change is ready for a logical Git commit


## 19. Error Handling

All applications and services must handle errors consistently and predictably.

### General Rules

- Identify expected failure scenarios before implementing a feature.
- Handle expected errors explicitly.
- Do not silently swallow exceptions.
- Do not return success when an operation has failed or its outcome is unknown.
- Do not expose stack traces, internal exception messages, SQL details, credentials, or other sensitive information to clients.
- Use Laravel's exception handling mechanisms where appropriate.
- Avoid unnecessary `try/catch` blocks. Catch exceptions where the application can handle them meaningfully or add useful context.

### API Responses

- Return appropriate HTTP status codes.
- Keep error response formats consistent across endpoints.
- Use validation errors for invalid input.
- Distinguish authentication, authorization, missing resources, business rule violations, conflicts, and unexpected server errors.
- Do not expose internal implementation details in public error messages.

### External Service Failures

When integrating with external services, handle relevant failure scenarios, including:

- connection failures;
- timeouts;
- unsuccessful HTTP responses;
- invalid or unexpected response formats;
- authentication failures;
- rate limits, when applicable;
- temporary service unavailability.

Do not mark a payment as successful merely because a request was sent or a response could not be verified.

### Database and Transaction Safety

- Preserve data consistency when an operation involves multiple database changes.
- Use database transactions when necessary.
- Avoid partial updates that leave the system in an invalid state.
- Handle expected database conflicts and constraint violations appropriately.

### Logging and Diagnostics

- Log unexpected failures with sufficient diagnostic context.
- Include relevant operation identifiers when available.
- Never log passwords, secret keys, payment credentials, or other sensitive data.
- Avoid logging the same exception repeatedly at multiple layers without a clear reason.

### Testing

- Test important expected failure scenarios.
- Verify HTTP status codes, response formats, and resulting database state.
- Test external service failures and webhook validation where relevant.
- Ensure failed operations do not produce unintended side effects.

Apply these rules to the Laravel backend and the Mock Payment Provider. Adapt error handling to each service's responsibilities rather than forcing identical implementations.
