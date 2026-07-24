# API contract — Support Ticket Management

Base URL (DDEV): `https://ai-practical-assessment.ddev.site`

**Custom REST** under `/api/tickets` (routing.yml + `TicketApiController` → `JsonResponse`).

Auth: authenticated session (logged-in user) for mutating endpoints. Permissions enforced per route.

Content-Type: `application/json` for request/response bodies unless noted.

**Enums**

| Field | Values |
|-------|--------|
| `priority` | `low`, `medium`, `high` |
| `status` | `open`, `in_progress`, `resolved`, `closed`, `cancelled` |

---

## Common schemas

### Ticket

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

`assignedTo` may be `null`. Timestamps are ISO-8601 UTC.

### Comment

```json
{
  "id": 44,
  "uuid": "b2c3d4e5-f6a7-8901-bcde-f12345678901",
  "ticketId": 12,
  "message": "Looking into mail logs.",
  "createdBy": 3,
  "createdAt": "2026-07-23T15:00:00+00:00"
}
```

### Error body

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
| 400 | Malformed / missing JSON body |
| 401 | Unauthenticated (mutating routes) |
| 403 | Missing permission |
| 404 | Ticket not found |
| 422 | Validation failed or **invalid status transition** |
| 500 | Unexpected server error |

---

## Endpoints

### 1. List / search tickets

`GET /api/tickets`

**Permission:** `access ticket overview`

**Query parameters**

| Param | Type | Required | Rules |
|-------|------|----------|--------|
| `search` | string | no | Keyword; trimmed; `LIKE` on title OR description; empty/omitted = no keyword filter |
| `status` | string | no | One of status enum; invalid → **422** |
| `page` | int | no | Default `1`; min 1 |
| `limit` | int | no | Default `25`; max `100` |

**Response `200`**

```json
{
  "data": [ /* Ticket, … */ ],
  "meta": {
    "page": 1,
    "limit": 25,
    "total": 128
  }
}
```

Empty `search` with no `status` returns the full paginated list (not an error).

**Errors:** `403`, `422` (invalid `status`)

---

### 2. Get ticket

`GET /api/tickets/{id}`

**Permission:** `view ticket`

**Path:** `id` — positive integer

**Response `200`**

```json
{
  "data": { /* Ticket */ },
  "comments": [ /* Comment[] chronological oldest→newest */ ]
}
```

**Errors:** `403`, `404`

---

### 3. Create ticket

`POST /api/tickets`

**Permission:** `create ticket` (authenticated)

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
| `description` | yes | non-empty |
| `priority` | yes | `low` \| `medium` \| `high` |
| `assignedTo` | no | omit/null or existing user id |
| `status` | — | **Ignored**; always stored as `open` |
| `createdBy` | — | Set from current user |

**Response `201`** — Ticket object; header `Location: /api/tickets/{id}`

**Errors:** `400`, `401`, `403`, `422`

---

### 4. Update ticket (non-status fields)

`PATCH /api/tickets/{id}`

**Permission:** `edit ticket` (authenticated)

**Request** (at least one field)

```json
{
  "title": "Updated title",
  "description": "Updated body",
  "priority": "medium",
  "assignedTo": null
}
```

| Field | Validation |
|-------|------------|
| `title` | if present: 1–255 |
| `description` | if present: non-empty |
| `priority` | if present: enum |
| `assignedTo` | if present: null or existing user id |
| `status` | **Not allowed** → **422** (use status endpoint) |

**Response `200`** — Ticket (bumped `updatedAt`)

**Errors:** `400`, `401`, `403`, `404`, `422`

---

### 5. Transition ticket status

`PATCH /api/tickets/{id}/status`

**Permission:** `transition ticket status` (authenticated)

**Must call** `TicketStateMachine` (service `ticket_management.state_machine`). Validates against the **current persisted** status before applying.

**Request**

```json
{
  "status": "in_progress"
}
```

| Field | Required | Validation |
|-------|----------|------------|
| `status` | yes | Target status enum; must be an allowed transition |

**Allowed transitions**

| From | To |
|------|-----|
| `open` | `in_progress`, `cancelled` |
| `in_progress` | `resolved`, `cancelled` |
| `resolved` | `closed` |
| `closed` | _(none)_ |
| `cancelled` | _(none)_ |

**Response `200`** — Ticket with new status

**Response `422` (invalid transition)** — JSON error body, no status change:

```json
{
  "message": "Transition from open to closed is not allowed.",
  "errors": [
    {
      "field": "status",
      "code": "invalid_transition",
      "message": "Transition from open to closed is not allowed."
    }
  ]
}
```

**Errors:** `400`, `401`, `403`, `404`, `422`

---

### 6. Add comment

`POST /api/tickets/{id}/comments`

**Permission:** `create ticket comment` (authenticated)

**Request**

```json
{
  "message": "Looking into mail logs."
}
```

| Field | Required | Validation |
|-------|----------|------------|
| `message` | yes | 1–5000 chars |

**Response `201`** — Comment

**Errors:** `400`, `401`, `403`, `404` (nonexistent ticket — no comment created), `422`

---

## Implementation map

| Method | Path | Controller method |
|--------|------|-------------------|
| `GET` | `/api/tickets` | `TicketApiController::collection` |
| `POST` | `/api/tickets` | `TicketApiController::post` |
| `GET` | `/api/tickets/{id}` | `TicketApiController::get` |
| `PATCH` | `/api/tickets/{id}` | `TicketApiController::patch` |
| `PATCH` | `/api/tickets/{id}/status` | `TicketApiController::patchStatus` |
| `POST` | `/api/tickets/{id}/comments` | `TicketApiController::postComment` |

Routes: `web/modules/custom/ticket_management/ticket_management.routing.yml`  
Services: `ticket_management.state_machine`, `ticket_management.ticket_query`

---

## Out of scope

- `DELETE` ticket or comment  
- Changing status via `PATCH /api/tickets/{id}`  
- User CRUD  
- Search of comment bodies  
