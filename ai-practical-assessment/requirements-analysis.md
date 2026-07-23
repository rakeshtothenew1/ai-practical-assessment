# Requirements analysis — Support Ticket Management System

## Selected Project Option

**Support Ticket Management System** (Drupal 11 + DDEV)

A backend-centric application for creating, tracking, and commenting on support tickets, with a constrained status state machine, keyword/status filtering, validated persistence, and a Twig + vanilla JS UI that surfaces validation and error states.

---

## My Understanding

The system manages support tickets for authenticated (or seeded) users. Tickets carry metadata (priority, status, assignees, audit timestamps) and a thread of comments. Status changes are not free-form: only the listed transitions are allowed. Users can create tickets, browse/filter/search them, view detail (including comments), update ticket fields, transition status legally, and add comments. Data must persist across requests. The backend validates all inputs and illegal transitions; the frontend must show clear error states when those fail. Users themselves are seeded only—no full user-management CRUD is in scope for this assessment.

**Implementation constraint:** `Ticket` (and ticket `Comment`) must be implemented as **custom content entities** owned by `ticket_management`—not Drupal core entity types such as Node (`node`), core Comment (`comment`), Taxonomy Term, Media, or similar reused core bundles.

---

## Functional Requirements

### Entities

| Entity | Fields | Notes |
|--------|--------|-------|
| **User** | Identity fields as needed for display/assignment | Drupal **core User** entity — **seeded only**; no create/update/delete user UI or API in scope |
| **Ticket** | `id`, `title`, `description`, `priority`, `status`, `assignedTo`, `createdBy`, `createdAt`, `updatedAt` | **Custom content entity** (e.g. `ticket`) defined by `ticket_management` — **not** Node or any other core entity type |
| **Comment** | `id`, `ticketId`, `message`, `createdBy`, `createdAt` | **Custom content entity** (e.g. `ticket_comment`) belonging to a ticket — **not** the Drupal core Comment entity; append-only for this scope |

**Explicitly out of scope for persistence modeling:** reusing or bundling `node`, core `comment`, `taxonomy_term`, `media`, or Paragraphs to represent tickets/comments.

### Features

1. **Create ticket** — Accept title, description, priority (and optional assignee); set initial status to `Open`; record `createdBy`, `createdAt`, `updatedAt`.
2. **List tickets** — Paginated or complete list with enough columns to identify tickets (id, title, status, priority, assignee, dates).
3. **Ticket detail** — Show all ticket fields plus associated comments in chronological order.
4. **Update ticket** — Change mutable fields (e.g. title, description, priority, assignee) subject to validation; bump `updatedAt`.
5. **Status state machine** — Transition status only via allowed edges (see below); reject all others with a clear validation error.
6. **Add comments** — Attach a message to an existing ticket; record `createdBy` and `createdAt`.
7. **Keyword search + status filter** — Filter list by free-text keyword (title/description at minimum) and/or by status.
8. **Persistence** — Store tickets and comments via **custom entity** storage/schema so data survives process restarts (dedicated tables / entity definitions—not Nodes).
9. **Backend validation** — Required fields, types/enums, referential integrity (assignee/user, ticket for comments), and illegal transitions.
10. **Frontend error states** — Display validation and server errors on forms and actions (create/update/comment/transition/search) without silent failure.

### Status state machine (allowed transitions)

```text
Open         → In Progress
Open         → Cancelled
In Progress  → Resolved
In Progress  → Cancelled
Resolved     → Closed
```

**Disallowed examples (non-exhaustive):** `Open → Resolved`, `Open → Closed`, `Resolved → Open`, `Closed → *`, `Cancelled → *`, skipping steps, or same-status “no-op” treated as invalid unless explicitly allowed later.

Initial status on create: **Open**.

---

## Non-Functional Requirements

| Area | Expectation |
|------|-------------|
| **Platform** | Drupal 11, PHP 8.3, DDEV; MySQL 8.0 (default) or MariaDB |
| **API surface** | JSON:API and/or custom REST for ticket/comment operations as needed |
| **UI** | Twig templates + vanilla JS only — no Node.js / npm build pipeline |
| **Code location** | Custom module under `web/modules/custom/ticket_management` |
| **Architecture** | State machine logic in a **standalone injectable service class** |
| **Domain model** | Ticket + ticket Comment as **custom content entities** in `ticket_management`; do **not** use core Node/Comment entities for this domain |
| **Coding standards** | Drupal coding standards + PSR-12 |
| **Security** | Escape output (Twig auto-escape); sanitize/validate input; prevent XSS in comment/ticket text rendering |
| **Testability** | PHPUnit via `ddev exec` (or `ddev exec vendor/bin/phpunit`) for domain/validation/state-machine coverage |
| **Operability** | All runtime commands via `ddev exec` / `ddev drush` / `ddev composer` |
| **Reliability** | Clear HTTP/API error responses for invalid input and illegal transitions |

---

## Assumptions

1. **Users are pre-seeded** (Drush/fixtures); login may use a seeded account; user CRUD is out of scope.
2. **`createdBy` / `assignedTo` / comment `createdBy`** reference seeded users by id (or Drupal uid).
3. **Initial ticket status** is always `Open`.
4. **`Closed` and `Cancelled` are terminal** — no outbound transitions unless clarified otherwise.
5. **Priority** is a fixed enum (e.g. Low / Medium / High / Critical) — exact values to be fixed in design/API contract.
6. **Keyword search** matches at least `title` and `description` (case-insensitive); comments may be excluded from search unless clarified.
7. **Update** may include status change only when the transition is legal; otherwise status updates go through an explicit transition action.
8. **Timestamps** are stored in UTC (or Drupal’s standard storage) and displayed consistently.
9. **AuthN/AuthZ** — at minimum, mutating operations require an authenticated seeded user; fine-grained roles (agent vs requester) are optional unless specified later.
10. **Persistence** uses Drupal **custom content entity** APIs (`ContentEntityBase` + `*.entity_type` / base field definitions) for Ticket and ticket Comment. Core Node and core Comment entities are **not** used for this domain.
11. **User** remains the only core entity reused (seeded accounts for `createdBy` / `assignedTo`).

---

## Clarifications

Items to confirm if ambiguity appears during implementation (sensible defaults listed):

| Topic | Question | Default if unanswered |
|-------|----------|------------------------|
| Priority values | Exact enum set? | `low`, `medium`, `high`, `critical` |
| Search scope | Include comment bodies? | Title + description only |
| Assignee | Required on create? | Optional; nullable `assignedTo` |
| Status on update | Via general PATCH or dedicated transition endpoint? | Dedicated transition API + UI control |
| Pagination | Required on list? | Yes, reasonable page size (e.g. 25) |
| Comment edit/delete | In scope? | No — create + list on detail only |
| Soft delete | Tickets/comments? | No — no delete in MVP |
| Concurrent updates | Last-write-wins vs conflict detection? | Last-write-wins + document race; optional `updatedAt` check if time allows |
| Anonymous access | Read-only list/detail? | Authenticated only for mutations; read policy TBD in design |

---

## Edge Cases

| Case | Expected behavior |
|------|-------------------|
| **Invalid status transition** (e.g. `Open → Closed`) | Backend rejects (4xx); no DB change; frontend shows explicit error (e.g. “Transition from Open to Closed is not allowed”). |
| **Empty search** (blank keyword, no status) | Return full list (or first page); not an error. Empty keyword **with** status filter still filters by status only. |
| **Comment on nonexistent ticket** | Backend returns 404 (or equivalent); no comment row created; frontend shows “Ticket not found” / failed submit state. |
| **XSS in comment text** | Persist raw text safely; **never** render unescaped HTML. Twig escaping (and API consumers treating text as plain) prevent script execution. Optional: strip/forbid HTML on input. |
| **Orphaned assignee** | `assignedTo` points to missing/deleted user (should be rare with seed-only users). Validate assignee exists on create/update; if historical orphan appears, show ticket with placeholder (“Unknown user” / empty) and block new saves that keep an invalid id. |
| **Concurrent status update** | Two agents transition the same ticket near-simultaneously. Second request must re-read current status and apply state-machine rules; if transition is no longer valid from the new status, reject. Prefer documenting last-write-wins for field updates; status changes always validated against **current** stored status, not a stale client copy alone. |

### Additional edge cases (implicit)

- Missing required fields (`title`, `message`, etc.) → 422/validation error + field-level UI errors.
- Extremely long title/description/comment → enforce max lengths server-side.
- Assigning a non-seeded / invalid user id → validation error.
- Acting on `Closed` or `Cancelled` ticket (status change) → reject; field edits policy TBD (default: allow non-status field edits unless product says lock).

---

## Traceability (for later steps)

This analysis feeds:

- `acceptance-criteria.md` — testable Given/When/Then per feature and edge case  
- `implementation-plan.md` / `design-notes.md` — entities, services, routes, Twig screens  
- `api-contract.md` / `data-model.md` — field types, enums, transition matrix  
- `test-strategy.md` — PHPUnit cases for state machine + validation + search filters  
