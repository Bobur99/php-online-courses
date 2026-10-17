# Project Requirements

## 1. Project Purpose

The project is a backend for an online learning platform.

The frontend is a separate application and is outside the scope of this project.

All interaction with the backend is performed through a REST API.

---

## 2. User Roles

The system has three roles:

- `admin`
- `teacher`
- `student`

The backend must enforce access permissions according to the user's role.

Users can be blocked by an administrator.

---

## 3. Authentication and User Management

### Registration

The system must provide user registration.

### Authentication

The system must provide API authentication.

Authentication must be implemented without:

- Laravel Breeze
- Laravel Jetstream
- Laravel Fortify
- similar authentication starter packages

### User operations

An authenticated user must be able to:

- view their own profile;
- edit their profile;
- change their password;
- log out.

### Administrator operations

An administrator must be able to:

- view the list of users;
- change user roles;
- block users.

---

## 4. Courses

A course contains:

- title;
- description;
- price;
- category;
- author;
- status.

Course statuses:

- `draft`
- `published`

### Course permissions

Only:

- the course author;
- an administrator

can modify a course.

The backend must enforce these permissions.

---

## 5. Categories

The system must provide category management.

Categories must support CRUD operations.

Each course belongs to one category.

---

## 6. Lessons

Lessons belong to a course.

A lesson contains:

- title;
- text/content;
- order number;
- optional file material.

The API must support:

- creating lessons;
- updating lessons;
- deleting lessons;
- listing lessons of a course.

---

## 7. Course Purchase

A student can purchase a published course.

The purchase process must:

1. Create a purchase record.
2. Call the external payment provider.
3. Store the payment operation ID.
4. Return the payment URL to the client.

Only published courses can be purchased.

---

## 8. Payment Webhook

The backend must provide a webhook for the external payment provider.

The webhook must:

1. Receive the payment notification.
2. Validate the provider signature.
3. Process the payment result.
4. Change the purchase status.
5. Grant the student access to the purchased course.

The backend must not trust payment status received directly from the client.

---

## 9. Learning Progress

A student must be able to mark lessons as completed.

The system must store the student's lesson progress.

When all lessons of a course are completed, the course must be considered completed by the student.

---

## 10. Certificates

After completing a course, the student must receive a certificate.

The certificate must:

- be generated as a PDF;
- be stored;
- be available for download through the API.

Certificate generation must be performed asynchronously using a queue.

Certificate generation must not block the main HTTP request.

---

## 11. Search and Filtering

The course API must support:

### Search

Search by:

- course title.

### Filtering

Filter by:

- category;
- teacher.

### Sorting

Sort courses by:

- price;
- date.

---

## 12. Queues

The project must use Laravel queues.

At minimum, the following operations must be implemented as jobs:

1. Certificate generation.
2. Post-payment notification.

---

## 13. Domain Events

The project must use Laravel Events and Listeners.

The following domain events are required:

- `UserRegistered`
- `CoursePublished`
- `CoursePurchased`
- `CourseCompleted`

Events and listeners must have clear responsibilities.

---

## 14. REST API

The backend must provide a REST API.

The API must use:

- `GET`
- `POST`
- `PUT`
- `PATCH`
- `DELETE`

Responses must use JSON.

The API must use appropriate HTTP status codes.

---

## 15. Validation

Request validation must be implemented using Laravel Form Requests.

Validation rules must correspond to the requirements of each operation.

Invalid requests must be rejected by the backend.

---

## 16. Authorization

Authorization must be enforced on the backend.

Use Laravel:

- Policies;
- Gates

where appropriate.

The client must not be trusted to enforce permissions.

---

## 17. Logging

Important application events and errors must be logged.

At minimum, logging should cover:

- exceptions;
- course publication;
- purchases;
- payment webhook processing;
- course completion.

---

## 18. Functional Scope

The following rules apply to the entire project:

- Implement only functionality required by this specification.
- Do not add unrelated business functionality without explicit approval.
- Do not create unnecessary database tables or fields.
- Do not create unnecessary API endpoints.
- Do not introduce additional authentication or payment features unless required by the specification or explicitly approved.
- Technical improvements are allowed only when they do not change the required business behavior.

---

## 19. Implementation Principle

Every implementation task must be derived from a requirement defined in this document.

For business functionality, development follows TDD:

1. Define expected behavior from the requirement.
2. Write the test.
3. Run the test and verify the expected failure.
4. Implement the minimum required functionality.
5. Run the test and verify success.
6. Refactor if necessary.
7. Run the relevant tests again.

Tests must verify requirements and observable behavior rather than being written to match a particular implementation.
