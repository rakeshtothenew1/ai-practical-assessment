# Acceptance criteria — Support Ticket Management

Checklist mirroring the assignment’s **core acceptance criteria**, derived from [`requirements-analysis.md`](requirements-analysis.md), [`api-contract.md`](api-contract.md), and [`ui-flow.md`](ui-flow.md).

Mark each item when verified (automated and/or manual on DDEV).

---

## A. Domain model & persistence

- [ ] **A1.** `Ticket` is a **custom content entity** (`ticket`) in `ticket_management` — not Node or another core entity type.
- [ ] **A2.** Ticket **Comment** is a **custom content entity** (`ticket_comment`) — not the Drupal core Comment entity.
- [ ] **A3.** Ticket fields present and persisted: id, title, description, priority, status, assignedTo, createdBy, createdAt, updatedAt.
- [ ] **A4.** Comment fields present and persisted: id, ticketId, message, createdBy, createdAt.
- [ ] **A5.** Users are **seeded only** (install/update hook); no user management CRUD in scope.
- [ ] **A6.** Data survives request/process restart (MySQL via entity schema; no reliance on in-memory-only storage).

---

## B. Create ticket

- [ ] **B1.** Authenticated permitted user can create a ticket with title, description, priority, optional assignee.
- [ ] **B2.** New tickets always start with status **Open** (`open`).
- [ ] **B3.** `createdBy` / `createdAt` / `updatedAt` are set on create.
- [ ] **B4.** Invalid/missing required fields are rejected by the backend with clear errors.

---

## C. List tickets

- [ ] **C1.** User can view a list of tickets with identifying columns (at least id, title, status, priority).
- [ ] **C2.** List supports pagination or an equivalent complete browsable set for MVP data volumes.

---

## D. Ticket detail

- [ ] **D1.** User can open a ticket and see all ticket fields.
- [ ] **D2.** Detail shows associated comments in chronological order.

---

## E. Update ticket

- [ ] **E1.** User can update mutable non-status fields (title, description, priority, assignee) subject to validation.
- [ ] **E2.** `updatedAt` changes on successful update.
- [ ] **E3.** Status cannot be arbitrarily changed via general update/PATCH (must use transition flow).

---

## F. Status state machine

- [ ] **F1.** Allowed: Open → In Progress.
- [ ] **F2.** Allowed: In Progress → Resolved.
- [ ] **F3.** Allowed: Resolved → Closed.
- [ ] **F4.** Allowed: Open → Cancelled.
- [ ] **F5.** Allowed: In Progress → Cancelled.
- [ ] **F6.** Disallowed transitions are rejected (e.g. Open → Closed, Open → Resolved, any transition from Closed/Cancelled).
- [ ] **F7.** Transition logic lives in a **standalone service class** (`TicketStateMachine`), not inline in controllers/forms only.
- [ ] **F8.** On illegal transition, persisted status is unchanged and the client receives a clear error.

---

## G. Comments

- [ ] **G1.** User can add a comment (message) to an existing ticket.
- [ ] **G2.** Comment records `createdBy` and `createdAt`.
- [ ] **G3.** Comment on a nonexistent ticket returns **not found** / equivalent and creates no row.

---

## H. Keyword search + status filter

- [ ] **H1.** List can be filtered by keyword matching title and/or description.
- [ ] **H2.** List can be filtered by status.
- [ ] **H3.** Keyword + status can be combined.
- [ ] **H4.** Empty keyword (no text) is valid and does not error (returns unfiltered-by-text results, optionally still status-filtered).
- [ ] **H5.** Search/filter implemented via Drupal **Database API** (custom query service), not solely ad-hoc Views/JSON:API filters.

---

## I. Backend validation

- [ ] **I1.** Required fields enforced server-side.
- [ ] **I2.** Priority and status values restricted to defined enums.
- [ ] **I3.** Assignee must reference an existing user when provided.
- [ ] **I4.** Illegal status transitions enforced server-side.
- [ ] **I5.** Max lengths enforced for title/description/message as specified.

---

## J. Frontend (Twig + vanilla JS)

- [ ] **J1.** Ticket list UI with search/filter.
- [ ] **J2.** Ticket detail UI with status controls and comments.
- [ ] **J3.** Create ticket form UI.
- [ ] **J4.** Frontend built with **Twig + vanilla JS** — **no Node.js/npm** build step or dependency.
- [ ] **J5.** User-facing **error states** shown for validation/server failures (create/update/comment/transition/search) — no silent failure.
- [ ] **J6.** Loading / empty / success / error behaviors covered per [`ui-flow.md`](ui-flow.md) for list, create, and detail.

---

## K. Security & quality

- [ ] **K1.** XSS in comment/ticket text is mitigated (Twig escaping; no unsafe raw HTML render).
- [ ] **K2.** Mutating operations require authentication and correct permissions.
- [ ] **K3.** Coding standards: Drupal + PSR-12 for custom module code.
- [ ] **K4.** PHPUnit tests exist for state machine and key kernel paths; runnable via `ddev exec` ([`test-strategy.md`](test-strategy.md)).

---

## L. Platform / delivery

- [ ] **L1.** Runs under **DDEV** with PHP 8.3 and MySQL (or MariaDB if configured).
- [ ] **L2.** Custom code under `web/modules/custom/ticket_management`.
- [ ] **L3.** Module README documents enablement, seeded users, URLs, and test commands.
- [ ] **L4.** Operations documented/used via `ddev drush` / `ddev composer` / `ddev exec` (not host PHP/MySQL assumptions).

---

## Edge-case criteria (explicit)

- [ ] **X1.** Invalid transition → error + no status change.
- [ ] **X2.** Empty search → full list (or first page), not an error.
- [ ] **X3.** Comment on nonexistent ticket → 404/error, no comment created.
- [ ] **X4.** XSS payload in comment → displayed safely (escaped).
- [ ] **X5.** Orphaned/invalid assignee on write → validation error; display handles missing user gracefully if encountered.
- [ ] **X6.** Concurrent/stale status update → second illegal transition rejected against current stored status.

---

## Sign-off

| | |
|--|--|
| Date verified | |
| Verified by | |
| Environment | DDEV project `ai-practical-assessment` |
| Notes / evidence | See `test-results.md` |
