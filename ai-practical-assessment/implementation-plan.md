# Implementation plan — Support Ticket Management

Turn [`design-notes.md`](design-notes.md) and [`tool-specific/cursor-workflow/spec.md`](tool-specific/cursor-workflow/spec.md) into ordered development work for module `ticket_management`.

**Also see:** [`acceptance-criteria.md`](acceptance-criteria.md) · [`data-model.md`](data-model.md) · [`api-contract.md`](api-contract.md) · [`ui-flow.md`](ui-flow.md) · [`test-strategy.md`](test-strategy.md)

---

## Overview

| Item | Detail |
|------|--------|
| Goal | Ship a working Support Ticket Management System on Drupal 11 / DDEV |
| Module | `web/modules/custom/ticket_management` |
| Domain storage | Custom content entities `ticket` + `ticket_comment` (not Node / core Comment) |
| Key service | `TicketStateMachine` (standalone DI service) |
| APIs | Custom REST (`/api/tickets…`) + JSON:API exposure |
| UI | Twig + Form API + vanilla JS (no Node) |
| DB | Entity schema → MySQL automatically; no manual SQL for MVP |
| Commands | Always `ddev exec` / `ddev drush` / `ddev composer` |

**Outcome:** Authenticated seeded users can create, list (search/filter), view, update, transition, and comment on tickets with backend validation and visible frontend error states.

---

## Task breakdown

### T1 — Scaffold module

- [ ] Create `web/modules/custom/ticket_management/`
- [ ] Add `ticket_management.info.yml` (core_version_requirement: `^11`, dependencies)
- [ ] Add empty `services.yml`, `permissions.yml`, `routing.yml`, `libraries.yml`
- [ ] Enable: `ddev drush en ticket_management -y`
- [ ] Confirm module appears in `ddev drush pm:list --status=enabled`

### T2 — Define Ticket entity

- [ ] `src/Entity/Ticket.php` + `TicketInterface` (`ContentEntityBase`)
- [ ] Base fields per [`data-model.md`](data-model.md): title, description, priority, status, assigned_to, created_by, created, changed
- [ ] Access control handler, list builder, view builder, form handlers (stubs OK)
- [ ] Permissions wired for view/create/update
- [ ] Install schema via entity definitions; verify tables with `ddev mysql` / entity query
- [ ] **Do not** use `node` type/bundle

### T3 — Define Comment entity

- [ ] `src/Entity/TicketComment.php` + interface
- [ ] Fields: `ticket_id` → ticket, `message`, `created_by`, `created`
- [ ] Access handler (create/view; no update/delete in MVP)
- [ ] Verify FK-style reference and load-by-ticket

### T4 — Seed users via update/install hook

- [ ] `ticket_management.install`: `hook_install` and/or `hook_update_N` to create seeded users + `ticket_agent` role with module permissions
- [ ] Idempotent: skip if users already exist (guard by username/uuid)
- [ ] Document credentials in module README (local only)
- [ ] Run: `ddev drush updb -y` / reinstall module as needed
- [ ] No user CRUD UI

### T5 — Build TicketStateMachine service

- [ ] Interface + class; register `ticket_management.state_machine` in `services.yml`
- [ ] Transition matrix from spec; `canTransition`, `assertTransition`, `getAllowedTargets`, `apply`
- [ ] Throw domain exception on illegal transition
- [ ] Unit tests (can start here in parallel with T11)

### T6 — Add presave validation

- [ ] Ticket `preSave`: on insert force `status = open`; set `created_by` from current user if empty
- [ ] Block arbitrary status changes on save unless applied through state machine path
- [ ] Field constraints: required, max length, allowed values, valid `assigned_to` user
- [ ] Comment: require non-empty message; validate parent ticket exists
- [ ] Map violations to forms and API 422

### T7 — Build REST / JSON:API endpoints

- [ ] Custom REST per [`api-contract.md`](api-contract.md):
  - `GET/POST /api/tickets`
  - `GET/PATCH /api/tickets/{id}`
  - `POST /api/tickets/{id}/transition`
  - `GET/POST /api/tickets/{id}/comments`
- [ ] JSON error envelope; 401/403/404/422
- [ ] Enable JSON:API for `ticket` / `ticket_comment`; disallow status via PATCH (use transition)
- [ ] Auth: session + CSRF for browser; document Basic Auth if used for API tests

### T8 — Build search / filter

- [ ] `TicketQueryService` using Database API (`Connection`)
- [ ] Params: `q`, `status`, `page`, `limit` (defaults/caps per contract)
- [ ] Empty `q` = no keyword predicate; escape LIKE wildcards
- [ ] Wire into `GET /api/tickets` and Twig list form

### T9 — Build Twig templates

- [ ] Routes: `/tickets`, `/tickets/add`, `/tickets/{ticket}`, `/tickets/{ticket}/edit`
- [ ] Forms: list filters, create, edit, comment, transition controls
- [ ] Templates for list/detail; status badge labels
- [ ] Loading / empty / success / error per [`ui-flow.md`](ui-flow.md)
- [ ] Twig auto-escape only (no `|raw` on user text)

### T10 — Wire JS fetch layer

- [ ] Library `ticket_management/tickets` — vanilla JS + CSS only
- [ ] Optional progressive enhancement: transition + comment via `fetch` to REST with CSRF
- [ ] Disable buttons while pending; show error banner from API `message` / `errors`
- [ ] Full page form POST remains fallback (no JS required for MVP core)

### T11 — Write Kernel / Functional tests

- [ ] Unit: state machine matrix ([`test-strategy.md`](test-strategy.md))
- [ ] Kernel: entity CRUD, constraints, query service, illegal transition leaves DB unchanged, comment on missing ticket
- [ ] Functional (stretch): list empty/success; create validation; XSS escaped on detail
- [ ] Run: `ddev exec vendor/bin/phpunit … ticket_management/tests`

### T12 — Write README

- [ ] Module README under `web/modules/custom/ticket_management/README.md` **and/or** update assessment [`README.md`](README.md)
- [ ] Cover: enable module, seed users, URLs, API examples, test command, DDEV-only tooling, no Node

---

## Milestones

| Milestone | Tasks | Exit criteria |
|-----------|-------|----------------|
| **M1 — Foundation** | T1–T4 | Module enabled; entities install; seeded users/role exist |
| **M2 — Domain + API** | T5–T8 | Transitions enforced; REST list/create/get/patch/transition/comments work; search/filter works |
| **M3 — UI** | T9–T10 | Twig screens usable; error/empty/loading states; optional JS fetch |
| **M4 — Quality** | T11–T12 | PHPUnit green via DDEV; README complete; [`acceptance-criteria.md`](acceptance-criteria.md) checkable |

Suggested order: **T1 → T2 → T3 → T5 → T6 → T4 → T8 → T7 → T9 → T10 → T11 → T12** (T4 can follow T2 once permissions exist; T11 unit tests can start as soon as T5 lands).

---

## AI usage plan

| Phase | Use Cursor / AI for | Human verifies |
|-------|---------------------|----------------|
| Scaffold | Generate info.yml, entity stubs, services.yml from spec | Paths, dependencies, Drupal 11 attributes vs annotations |
| Entities | Base field definitions from data-model | Field types, cardinality, handlers |
| State machine | Matrix + unit tests | Every edge allowed/denied |
| REST | Controllers/resources from api-contract | Status not patchable; error codes |
| Query | Database API service | LIKE escaping; empty `q` behavior |
| Twig/JS | Templates + fetch helpers | XSS escaping; UI states |
| Tests | Kernel test skeletons | Fixtures, permissions, DDEV phpunit config |
| Docs | README / acceptance ticks | Manual smoke on `ddev launch` |

**Guardrails (from `.cursor/rules/drupal.mdc`):** only suggest `ddev …` commands; custom code under `ticket_management`; no Node/npm; state machine stays a service class.

Log notable prompts/outcomes in `tool-specific/cursor-workflow/` and later `final-ai-usage-summary.md`.

---

## Risks

| ID | Risk | Impact |
|----|------|--------|
| R1 | Accidental use of Node/core Comment instead of custom entities | Fails assignment constraint |
| R2 | Status changed via PATCH/entity form bypassing state machine | Illegal transitions possible |
| R3 | Entity schema / field update confusion on DDEV | Install broken; tests fail |
| R4 | JSON:API filter used instead of Database API search | Misses “search via DB API” expectation |
| R5 | Host PHPUnit/Composer used instead of DDEV | PHP version / plugin failures |
| R6 | XSS via `|raw` or unescaped JS `innerHTML` | Security defect |
| R7 | Non-idempotent user seed hook | Duplicate users / update failures |
| R8 | CSRF missing on JS fetch mutations | 403 in UI; insecure patterns |
| R9 | Scope creep (delete, comment edit, roles matrix) | Misses MVP deadline |

---

## Mitigation

| Risk | Mitigation |
|------|------------|
| R1 | Code review checklist; acceptance criteria item; Cursor rule forbids Node/core Comment for domain |
| R2 | Only `TicketStateMachine::apply` writes status; PATCH rejects `status`; form shows read-only status |
| R3 | Rely on entity definitions; `ddev drush cr` + `updb`; document enable order in README |
| R4 | Implement `TicketQueryService` first; list UI/API call it exclusively for `q`/`status` |
| R5 | README + Cursor rules: `ddev exec` / `ddev composer` / `ddev drush` only |
| R6 | Twig default escape; ban `|raw` on user fields; prefer `textContent` in JS |
| R7 | Seed by unique username; `user_load_by_name` guard; update hook no-op if present |
| R8 | Use `drupalSettings` CSRF / `X-CSRF-Token`; keep form POST fallback |
| R9 | Stick to task list T1–T12; defer stretch Functional tests if time-boxed |

---

## Tracking

After implementation, tick items in [`acceptance-criteria.md`](acceptance-criteria.md) and record evidence in `test-results.md`.
