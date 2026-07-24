# Pull request description

## Summary

- Implements a **Support Ticket Management System** as Drupal 11 custom module `ticket_management` (custom `ticket` + `ticket_comment` entities — not Node / core Comment).
- Delivers custom REST under `/api/tickets`, Twig + vanilla JS UI at `/tickets`, and a standalone `TicketStateMachine` enforced on API and entity `preSave()`.
- Includes PHPUnit Unit / Kernel / Functional coverage, DDEV PHPUnit env, seed users, CSRF on write routes, and DB indexes for list filters.

## What’s included

| Area | Detail |
|------|--------|
| Domain | Create / list / detail / update / status transition / comments / keyword+status search |
| State machine | `open → in_progress\|cancelled`; `in_progress → resolved\|cancelled`; `resolved → closed`; terminals `closed` / `cancelled` |
| Search | `TicketQueryService` via Database API (`LIKE` + status), indexes on `status` / `created` |
| UI | `/tickets`, `/tickets/add`, `/tickets/{id}` — fetch + CSRF, escaped output |
| Docs | Specs under `ai-practical-assessment/` (API contract, design, tests, review, debug, reflection) |

## Test plan

- [ ] `ddev start && ddev composer install`
- [ ] `ddev drush en ticket_management -y && ddev drush updb -y && ddev drush cr`
- [ ] Log in as seeded user (`agent_alice` / `TicketDemo!23`) and smoke `/tickets`
- [ ] Create ticket → appears in list; search + status filter work
- [ ] Valid transition (`open` → `in_progress`) succeeds; illegal (`open` → `closed`) shows error
- [ ] Add comment; detail shows chronological comments
- [ ] Mutating API without `X-CSRF-Token` returns **403**; with token succeeds
- [ ] `ddev exec vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/ticket_management/tests`

## Out of scope (intentional)

User CRUD, comment edit/delete, soft delete, Node-based modelling, Node.js/npm tooling, per-ticket ownership ACL.

## Related docs

- [README.md](README.md) · [api-contract.md](api-contract.md) · [acceptance-criteria.md](acceptance-criteria.md)
- [code-review-notes.md](code-review-notes.md) · [review-fixes.md](review-fixes.md) · [debugging-notes.md](debugging-notes.md)
