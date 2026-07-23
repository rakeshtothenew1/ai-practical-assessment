# Technical specification — `ticket_management`

Custom Drupal 11 module for the Support Ticket Management System. Runtime: DDEV, PHP 8.3, MySQL 8.0 (MariaDB optional). No Node.js.

**Related docs:** [`../../requirements-analysis.md`](../../requirements-analysis.md) · [`../../data-model.md`](../../data-model.md) · [`../../api-contract.md`](../../api-contract.md)

---

## 1. Module overview

| Item | Value |
|------|--------|
| Machine name | `ticket_management` |
| Path | `web/modules/custom/ticket_management` |
| Type | Custom module (not a profile) |
| Dependencies | `drupal:user`, `drupal:system`, `drupal:serialization`, `drupal:rest`, `jsonapi` (core), `drupal:basic_auth` and/or cookie/session for Twig UI |
| Namespace | `Drupal\ticket_management` |

Commands always via DDEV: `ddev drush en ticket_management -y`, `ddev composer …`, `ddev exec …`.

---

## 2. Content entities (custom — not core Node/Comment)

### 2.1 Ticket (`ticket`)

- Extends `ContentEntityBase`; entity type id `ticket`.
- Base fields defined in `Ticket::baseFieldDefinitions()` (see [`data-model.md`](../../data-model.md)).
- Storage: SQL content entity storage; table prefix `ticket` / `ticket__*`.
- Handlers: list builder, access control handler, form handlers (add/edit), view builder, route provider as needed.
- **Forbidden:** modeling tickets as `node` bundles, Paragraphs, or Media.

### 2.2 Ticket comment (`ticket_comment`)

- Extends `ContentEntityBase`; entity type id `ticket_comment`.
- Entity reference `ticket_id` → `ticket` (required, cardinality 1).
- Append-only in MVP (create + read); no edit/delete UI/API unless later expanded.
- **Forbidden:** Drupal core `comment` entity / Comment module field on Node.

### 2.3 User (core)

- Reuse `user` entity only for `created_by` / `assigned_to` / comment author.
- Seeded via install hook / Drush; no user CRUD in this module.

---

## 3. State machine service

Standalone injectable service — **not** inline in controllers, forms, or hooks.

| Item | Value |
|------|--------|
| Service id | `ticket_management.state_machine` |
| Class | `Drupal\ticket_management\Service\TicketStateMachine` |
| Interface | `Drupal\ticket_management\Service\TicketStateMachineInterface` |

### Responsibilities

- Define allowed transitions map (source → list of targets).
- `canTransition(string $from, string $to): bool`
- `assertTransition(string $from, string $to): void` — throws domain exception on illegal move
- `getAllowedTargets(string $from): array`
- `apply(TicketInterface $ticket, string $to): TicketInterface` — load **current** status from entity, validate, set status, leave save to caller (or save if designed as unit of work)

### Transition matrix

| From | Allowed to |
|------|------------|
| `open` | `in_progress`, `cancelled` |
| `in_progress` | `resolved`, `cancelled` |
| `resolved` | `closed` |
| `closed` | _(none — terminal)_ |
| `cancelled` | _(none — terminal)_ |

Initial status on create: `open` (set in entity `preSave` / form/API create path — never client-chosen on create).

Status changes go through this service only (REST transition endpoint + form submit). General ticket PATCH/update **must not** accept arbitrary `status` bypassing the machine.

---

## 4. API design (JSON:API + custom REST)

### 4.1 JSON:API

Enable/expose custom entities for standard resource operations where it fits:

- `GET /jsonapi/ticket/ticket` — collection (may use sparse fieldsets; filters limited)
- `GET /jsonapi/ticket/ticket/{uuid}` — item
- `POST /jsonapi/ticket/ticket` — create (status forced to `open` server-side)
- `PATCH /jsonapi/ticket/ticket/{uuid}` — update non-status fields
- `GET/POST` for `ticket_comment` similarly

JSON:API filtering alone is **not** the primary search implementation for keyword + status (see §6). Prefer custom REST list/search for that UX.

### 4.2 Custom REST / controller routes

Canonical HTTP API for assessment features (see full schemas in [`api-contract.md`](../../api-contract.md)):

| Concern | Approach |
|---------|----------|
| List + keyword + status filter | Custom collection endpoint using Database API query |
| Status transition | `POST …/tickets/{id}/transition` calling state machine |
| Validation errors | Consistent JSON error envelope (422/404/403) |
| Twig UI AJAX (optional) | Same REST endpoints with CSRF / session auth |

Serialization: `application/json` for custom REST; JSON:API media type for `/jsonapi/*`.

---

## 5. Permissions

Defined in `ticket_management.permissions.yml` (example machine names):

| Permission | Purpose |
|------------|---------|
| `access ticket overview` | View list / search UI and read APIs |
| `view ticket` | View ticket detail + comments |
| `create ticket` | Create tickets |
| `edit ticket` | Update non-status fields / assignee |
| `transition ticket status` | Call state machine / transition endpoint |
| `create ticket comment` | Add comments |
| `administer ticket_management` | Admin / bypass (optional) |

**Access control handler** on `ticket` / `ticket_comment` maps entity operations to these permissions. Seeded role (e.g. `ticket_agent`) granted the operational perms; authenticated users may get a subset if product requires.

Mutations require authenticated user. Anonymous: deny mutations; read access = deny by default (configurable later).

---

## 6. Search / filter (Database API)

Service: `ticket_management.ticket_query` → `TicketQueryService`.

Use Drupal Database API (`\Drupal::database()` / injected `Connection`), **not** Views as the primary path:

```text
SELECT base fields FROM {ticket_field_data} (or entity base table)
WHERE (title LIKE :kw OR description LIKE :kw)   -- if keyword non-empty
  AND status = :status                           -- if status filter set
ORDER BY created DESC
LIMIT :limit OFFSET :offset
```

Rules:

- Empty keyword + no status → all tickets (paginated).
- Empty keyword + status → filter by status only.
- Keyword: case-insensitive `LIKE %…%` on `title` and `description` only (not comment bodies).
- Escape LIKE wildcards in user input.
- Pagination: default page size 25; expose `page` + `limit` (cap max limit).

Entity Query may be used if it maps cleanly; specification preference for assessment: explicit Database API in the query service for transparency and testability.

---

## 7. Twig frontend routing

Routes in `ticket_management.routing.yml` (HTML, `_format: html`):

| Path | Controller / form | Permission |
|------|-------------------|------------|
| `/tickets` | List + search/filter form | `access ticket overview` |
| `/tickets/add` | Create form | `create ticket` |
| `/tickets/{ticket}` | Detail view + comment list + comment form + transition controls | `view ticket` |
| `/tickets/{ticket}/edit` | Edit form (non-status fields) | `edit ticket` |

- Parameter conversion: `{ticket}` → `entity:ticket`.
- Templates under `templates/` (`ticket-list.html.twig`, `ticket.html.twig`, etc.).
- Theme library: vanilla JS + CSS only (`ticket_management/tickets`); **no npm**.
- Forms use Drupal Form API; errors via form state + messenger; Twig auto-escape for XSS.

Optional: progressive enhancement calling custom REST with `fetch` + CSRF token for transitions/comments without full page reload — still vanilla JS.

---

## 8. Validation summary

| Layer | Rules |
|-------|--------|
| Entity constraints | Required title; priority enum; status enum; max lengths; valid user refs |
| State machine | Only allowed edges; re-read current status before apply |
| Comment | Non-empty message; ticket must exist |
| API | Map violations → 422 body; missing entity → 404; authz → 403 |

---

## 9. Module file map (target)

```text
web/modules/custom/ticket_management/
  ticket_management.info.yml
  ticket_management.services.yml
  ticket_management.permissions.yml
  ticket_management.routing.yml
  ticket_management.libraries.yml
  src/Entity/Ticket.php
  src/Entity/TicketComment.php
  src/Service/TicketStateMachine.php
  src/Service/TicketStateMachineInterface.php
  src/Service/TicketQueryService.php
  src/Controller/… (HTML + REST as needed)
  src/Form/…
  src/Access/…
  src/Plugin/rest/resource/… (if using REST plugins)
  templates/
  js/
  css/
  tests/src/Unit/TicketStateMachineTest.php
  tests/src/Kernel/…
```

---

## 10. Out of scope (MVP)

- User CRUD UI/API  
- Comment edit/delete  
- Soft delete  
- Modeling domain as Node / core Comment  
- Node.js / frontend bundlers  
- Full JSON:API filter dialect for keyword search (custom list endpoint instead)
