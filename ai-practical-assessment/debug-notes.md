# Debugging notes — CSRF 403 on ticket write API

## Symptom (real failure)

After Step 13 added `_csrf_request_header_token: 'TRUE'` on write routes, the functional suite failed under DDEV (PHP 8.3 / MySQL):

```text
ddev exec vendor/bin/phpunit -c web/core/phpunit.xml.dist \
  web/modules/custom/ticket_management/tests/src/Functional/TicketApiFunctionalTest.php --testdox

✘ Valid status transition returns 200
   Valid transition must return HTTP 200.
   Failed asserting that 403 is identical to 200.

✘ Invalid status transition returns 422
   Invalid transition must return HTTP 422.
   Failed asserting that 403 is identical to 422.

✔ List tickets returns 200

✘ Create ticket returns 201
   Failed asserting that 403 is identical to 201.
```

Pattern: **all mutating requests (POST/PATCH) → 403**; **GET collection still 200**. That is not a state-machine rejection (those are 422 from `TicketApiController::patchStatus`).

## Diagnosis

| Layer | Role | Is it the cause? |
|-------|------|------------------|
| `TicketStateMachine` | Allows `open → in_progress`, rejects `open → closed` with `InvalidTicketTransitionException` | **No** — tests never reach the controller body; HTTP never gets to 200/422. |
| `TicketApiController::patchStatus` / `post` | Validates payload, calls `$stateMachine->apply()`, saves entity | **No** — access fails before the controller runs. |
| Route requirements | `_permission` + `_user_is_logged_in` + **`_csrf_request_header_token: 'TRUE'`** | **Yes** — Drupal's CSRF request-header access checker returns **403** when `X-CSRF-Token` is missing/invalid. |
| Test helper `jsonRequest()` | Sends `CONTENT_TYPE` / `HTTP_ACCEPT` only | **Yes (gap)** — never fetches `/session/token` or sets `HTTP_X_CSRF_TOKEN`. |

Production JS (`ticket-app.js`) already sends `X-CSRF-Token` on non-GET calls. The browser UI is fine; only the functional test client was out of date after the review fix.

## Likely cause

**Missing CSRF header on session-authenticated write requests in `TicketApiFunctionalTest::jsonRequest()`**, after Step 13 enforced `_csrf_request_header_token` on:

- `POST /api/tickets`
- `PATCH /api/tickets/{id}`
- `PATCH /api/tickets/{id}/status`
- `POST /api/tickets/{id}/comments`

## Fix

Update `jsonRequest()` to:

1. For non-GET/HEAD methods, GET `/session/token` (session cookie already set by `drupalLogin()`).
2. Pass that value as `HTTP_X_CSRF_TOKEN` on the BrowserKit request.

Do **not** weaken the route CSRF requirement — the 403 is correct security behaviour; the test must match the production client.

## Verification

```bash
ddev exec vendor/bin/phpunit -c web/core/phpunit.xml.dist \
  web/modules/custom/ticket_management/tests/src/Functional/TicketApiFunctionalTest.php --testdox
```

Expect:

- Valid `open → in_progress` → **200** (state machine + controller path works).
- Invalid `open → closed` → **422** with `errors[0].code === invalid_transition`.
- List → **200**.
- Create → **201** with `status: open`.

Negative check (optional): omit the CSRF header once and confirm write routes still return **403**.
