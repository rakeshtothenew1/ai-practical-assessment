# API contract — Support Ticket Management

Base URL (DDEV): `https://ai-practical-assessment.ddev.site`

Two surfaces:

1. **Custom REST** under `/api/tickets` — primary for list/search, transitions, and assessment clarity  
2. **JSON:API** under `/jsonapi` — entity CRUD aligned with Drupal conventions  

Auth: session cookie (Twig UI) or Basic Auth / OAuth as configured. CSRF required for cookie-authenticated unsafe methods on custom routes that use Drupal’s form/API CSRF patterns.

Unless noted, request/response bodies are `application/json`.

Status/priority values: see [`data-model.md`](data-model.md) (`open`, `in_progress`, `resolved`, `closed`, `cancelled`; `low`|`medium`|`high`|`critical`).

---

## Common schemas

### Ticket (response)

```json
{
  "id": 12,
  "uuid": "a1b2c3d4-e5f6-7890-abcd-ef1234567890",
  "title": "Cannot reset password",
  "description": "Reset email never arrives.",
  "priority": "high",
  "status": "open",
  "assignedTo": 3,
  "createdBy": 2,
  "createdAt": "2026-07-23T14:30:00+00:00",
  "updatedAt": "2026-07-23T14:30:00+00:00"
}
```

`assignedTo` may be `null`. Timestamps ISO-8601 UTC (API layer formats from Unix `created`/`changed`).

### Comment (response)

```json
{
  "id": 44,
  "uuid": "…",
  "ticketId": 12,
  "message": "Looking into mail logs.",
  "createdBy": 3,
  "createdAt": "2026-07-23T15:00:00+00:00"
}
```

### Error envelope

```json
{
  "message": "Human-readable summary",
  "errors": [
    { "field": "title", "code": "required", "message": "Title is required." }
  ]
}
```

| HTTP | When |
|------|------|
| 400 | Malformed JSON |
| 401 | Unauthenticated |
| 403 | Missing permission |
| 404 | Ticket/comment not found |
| 422 | Validation / illegal transition |
| 500 | Unexpected server error |

---

## Custom REST endpoints

### 1. List / search tickets

`GET /api/tickets`

**Permission:** `access ticket overview`

**Query parameters**

| Param | Type | Required | Rules |
|-------|------|----------|--------|
| `q` | string | no | Keyword; trimmed; match title OR description (`LIKE`); empty = no keyword filter |
| `status` | string | no | One of status enum; invalid → 422 |
| `page` | int | no | Default `1`; min 1 |
| `limit` | int | no | Default `25`; max `100` |

**Response `200`**

```json
{
  "data": [ /* Ticket */ ],
  "meta": {
    "page": 1,
    "limit": 25,
    "total": 128
  }
}
```

**Validation / behavior**

- Empty `q` and omitted `status` → paginated full list (not an error).  
- Empty `q` with `status` → filter by status only.  
- Escape `%` / `_` in `q` for LIKE.

**Errors:** `401`, `403`, `422` (bad `status` / pagination)

---

### 2. Create ticket

`POST /api/tickets`

**Permission:** `create ticket`  
**Content-Type:** `application/json`

**Request**

```json
{
  "title": "Cannot reset password",
  "description": "Reset email never arrives.",
  "priority": "high",
  "assignedTo": 3
}
```

| Field | Required | Validation |
|-------|----------|------------|
| `title` | yes | 1–255 chars |
| `description` | yes | 1–10000 chars |
| `priority` | yes | enum |
| `assignedTo` | no | null or existing user id |
| `status` | — | **Ignored if sent**; always stored as `open` |
| `createdBy` | — | Set from current user |

**Response `201`** — Ticket body; `Location: /api/tickets/{id}`

**Errors:** `401`, `403`, `422` (field errors; invalid assignee)

---

### 3. Get ticket (detail)

`GET /api/tickets/{id}`

**Permission:** `view ticket`

**Path:** `id` — integer entity id

**Response `200`**

```json
{
  "data": { /* Ticket */ },
  "comments": [ /* Comment[] chronological */ ]
}
```

**Errors:** `401`, `403`, `404`

---

### 4. Update ticket (non-status fields)

`PATCH /api/tickets/{id}`

**Permission:** `edit ticket`

**Request** (all fields optional; at least one required)

```json
{
  "title": "Updated title",
  "description": "Updated body",
  "priority": "critical",
  "assignedTo": null
}
```

| Field | Validation |
|-------|------------|
| `title` | if present: 1–255 |
| `description` | if present: 1–10000 |
| `priority` | if present: enum |
| `assignedTo` | if present: null or existing user |
| `status` | **Must not be accepted** → `422` with message to use transition endpoint |

**Response `200`** — Ticket (with bumped `updatedAt`)

**Errors:** `401`, `403`, `404`, `422`

---

### 5. Transition ticket status

`POST /api/tickets/{id}/transition`

**Permission:** `transition ticket status`

**Request**

```json
{
  "status": "in_progress"
}
```

| Field | Required | Validation |
|-------|----------|------------|
| `status` | yes | Target status enum; must be allowed from **current persisted** status via state machine |

**Behavior**

1. Load ticket; 404 if missing.  
2. Read current `status`.  
3. `TicketStateMachine::assertTransition($current, $target)`.  
4. Set status; save; bump `changed`.  

**Response `200`** — Ticket with new status

**Error `422` examples**

```json
{
  "message": "Transition from open to closed is not allowed.",
  "errors": [
    { "field": "status", "code": "invalid_transition", "message": "Transition from open to closed is not allowed." }
  ]
}
```

**Errors:** `401`, `403`, `404`, `422` (illegal transition / missing status)

---

### 6. List comments for ticket

`GET /api/tickets/{id}/comments`

**Permission:** `view ticket`

**Response `200`**

```json
{
  "data": [ /* Comment[] oldest→newest */ ]
}
```

**Errors:** `401`, `403`, `404` (ticket)

---

### 7. Add comment

`POST /api/tickets/{id}/comments`

**Permission:** `create ticket comment`

**Request**

```json
{
  "message": "Looking into mail logs."
}
```

| Field | Required | Validation |
|-------|----------|------------|
| `message` | yes | 1–5000 chars; stored as plain text |

**Response `201`** — Comment

**Errors:** `401`, `403`, `404` (nonexistent ticket — no row created), `422` (empty/too long message)

---

## JSON:API endpoints (supplementary)

Entity type / bundle: `ticket` / `ticket`, `ticket_comment` / `ticket_comment`.  
UUIDs in paths. Media type: `application/vnd.api+json`.

| Method | Path | Maps to | Notes |
|--------|------|---------|--------|
| `GET` | `/jsonapi/ticket/ticket` | List | Prefer custom `GET /api/tickets` for `q`+`status` |
| `GET` | `/jsonapi/ticket/ticket/{uuid}` | Detail | Attributes mirror base fields |
| `POST` | `/jsonapi/ticket/ticket` | Create | Server forces `status=open`; reject client status override |
| `PATCH` | `/jsonapi/ticket/ticket/{uuid}` | Update | Disallow `status` attribute change; use custom transition |
| `GET` | `/jsonapi/ticket_comment/ticket_comment` | List comments | Filter by `ticket_id` relationship |
| `POST` | `/jsonapi/ticket_comment/ticket_comment` | Create comment | Require `ticket_id` relationship; 404/422 if ticket missing |

**JSON:API error** objects per spec (`errors[].status`, `detail`, `source.pointer`).

**Transition:** not expressed as generic PATCH — use `POST /api/tickets/{id}/transition` only.

---

## Twig UI ↔ API mapping

| UI route | Primary backend |
|----------|-----------------|
| `GET /tickets` | Form/DB list or `GET /api/tickets` |
| `POST /tickets/add` | Entity form → same rules as `POST /api/tickets` |
| `GET /tickets/{ticket}` | Entity view + comments |
| `POST` comment on detail | Same rules as `POST /api/tickets/{id}/comments` |
| Transition control | Same rules as transition endpoint / state machine |

Frontend must surface `message` / field `errors` for 422/404 responses (messenger or inline).

---

## Validation rules (global)

| Rule | Applied on |
|------|------------|
| Authenticated for all mutating endpoints | POST/PATCH |
| Permission checks | Every endpoint |
| Enum membership | `priority`, `status` |
| Max lengths | title, description, message |
| User existence | `assignedTo`, authors set server-side |
| State machine | Transition endpoint only |
| XSS | Output escaping; no raw HTML responses for message/title |
| Concurrent transition | Re-load status before assert; stale illegal target → 422 |

---

## Out of scope (API)

- `DELETE` ticket or comment  
- User CRUD  
- Changing status via PATCH/JSON:API attributes  
- Search including comment body  
