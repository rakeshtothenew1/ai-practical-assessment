# AI prompts — Design

Phase: technical specification, data model, API contract, architecture, UI flow.  
Inputs: `requirements-analysis.md`.

---

## Prompt 1 — Specification + data model + API (Step 4)

```text
Based on requirements-analysis.md, write tool-specific/cursor-workflow/spec.md:
a technical spec for a custom Drupal 11 module `ticket_management` running
under DDEV/MySQL. Cover: Ticket and Comment as Content Entities, the state
machine service, JSON:API/REST resource design, permissions, routing for a
Twig frontend, and search/filter using Drupal's Database API. Produce
data-model.md (entities/fields/relationships) and api-contract.md (every
endpoint: method, path, request/response schema, validation rules, error
responses).
```

**Outputs:**
- `tool-specific/cursor-workflow/spec.md`
- `data-model.md`
- `api-contract.md`

---

## Prompt 2 — Architecture + UI flow (Step 5)

```text
Write design-notes.md: Architecture Overview, Backend Design (entity type,
base fields, access control handler, validation constraints), Database Design
(confirm: Content Entities map to MySQL tables automatically via Drupal's
entity schema — no manual SQL needed unless I choose custom tables), Frontend
Design (Twig + vanilla JS, no Node build step), Validation Strategy, Error
Handling Strategy, link to test-strategy.md. Also write ui-flow.md: ticket
list (search/filter), ticket detail (status controls + comments), create
form, and required states (loading/empty/success/error) per screen.
```

**Outputs:**
- `design-notes.md`
- `ui-flow.md`

---

## Reusable design template

```text
From requirements-analysis.md, produce design artefacts for Drupal 11 module
`[module_name]` under DDEV:

1. Technical spec (entities, services, permissions, routing, search approach)
2. data-model.md (fields, enums, relationships, ER notes)
3. api-contract.md (every endpoint: method/path/schema/validation/errors)
4. design-notes.md (architecture, backend, DB, frontend, validation, errors)
5. ui-flow.md (screens + loading/empty/success/error states)

Constraints: Twig + vanilla JS only; state machine as injectable service;
prefer Database API for keyword+status search; no Node/npm.
```
