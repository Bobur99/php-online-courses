# Implementation decisions

## Independent interfaces (2026-10-28)

The course UI runs on localhost:5174, the provider UI on localhost:5173,
Laravel on localhost:80, and the mock provider API on localhost:8080.
Neither UI links or redirects to the other. The purchase API retains
`payment_url` to satisfy requirements §7, but checkout does not navigate to it.
The provider does not store or return new `return_url` values. Historical
records may still contain that unused field.

## Payment result versus webhook delivery

A provider result is terminal (`success` or `failed`). Delivery of its
notification is recorded separately in the existing JSON storage:
`webhook_delivered`, `webhook_attempts`, and `webhook_http_status`.
A 502 means delivery failed, not that payment was declined. The provider UI
can resend the same signed result through the existing complete/fail routes.
It cannot reverse an operation's result. A declined purchase is retried on
the course platform as a new operation, preserving history.

Legacy operations without delivery metadata show an unconfirmed delivery;
an operator can resend their existing result. A conflicting terminal result
at Laravel returns 409 and requires inspection rather than overwriting data.

Provider storage uses shared/exclusive locks over complete read/write
operations. HTTP errors and storage failures return safe JSON and are logged.
Tests use temporary storage and local HTTP servers, never the demo history.
The shared webhook secret comes from environment configuration in both services.

Laravel serializes purchase creation using the student's row, and webhook
processing using the purchase row. Post-payment work is dispatched within
the transaction using the configured database queue. The external HTTP call
and PostgreSQL cannot share an atomic transaction: a provider success followed
by a local commit failure can leave an orphan provider operation. Automatic
financial reconciliation is outside the mock integration's requirements.

## Protected learning materials

Guests see lesson titles/order, not the full content. A paid student retains
learning access if the course is later unpublished. Authors/admins retain
management access. New materials are private and served through an authorized
API. The frontend fetches them with its token and opens/downloads a Blob URL.

Deployments run `materials:privatize` to copy legacy files, verify bytes, and
remove public copies only after verification. Conflicting destinations stop
the command without removing the original. Nginx denies old public material
URLs as an additional protection. Certificates are also private.

## Events, queues, notifications

All four required domain events have logging listeners. Purchase creation,
signature rejection, webhook processing, and conflicts are also logged using
identifiers, without secrets or request bodies.

`SendPaymentNotification` sends a Laravel mail notification from the queue.
`MAIL_MAILER=log` is the documented local demo transport: the rendered email
is written to the application log, not delivered to a mailbox. SMTP delivery
requires the existing mail configuration; no additional provider is added.
`GenerateCertificate` creates/stores a PDF and skips regeneration of an
existing file. Storage failures fail the job instead of reporting success.
The queue container uses `restart: unless-stopped`, so the worker starts
again after the normal graceful exit triggered by `queue:restart`.

## Deployment and checks

```bash
docker compose up -d --build payment-provider
docker compose exec app php artisan materials:privatize
docker compose exec nginx nginx -t
docker compose exec nginx nginx -s reload
docker compose exec app php artisan queue:restart
docker compose exec app php artisan test
```

Run `npm run test:unit -- --run`, `npm run build`, `npx eslint .`, and
`npx oxlint .` in each frontend directory. The dev scripts pin their ports
with `--strictPort` so another app is not silently served on the wrong port.
