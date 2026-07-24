# Support Ticket Management — Assessment README

Drupal 11 custom module (`ticket_management`) for creating, listing, updating, transitioning, and commenting on support tickets. Local runtime is **DDEV only** (PHP 8.3, MySQL 8.0, Nginx). No host PHP and **no Node.js / npm**.

Full assessment package: this directory (`ai-practical-assessment/`). Application code: `web/modules/custom/ticket_management/`.

---

## Prerequisites

| Tool | Notes |
|------|--------|
| [Docker](https://docs.docker.com/get-docker/) | Docker Desktop or compatible runtime |
| [DDEV](https://ddev.com/get-started/) | v1.24+ recommended |
| Git | Clone / PR workflow |

**Not required on the host:** PHP, Composer, Drush, MySQL client, Node.js, npm, yarn, or any frontend bundler. PHP 8.3 runs **inside** the DDEV web container.

---

## Setup

From the **repository root** (sibling of `web/`, `.ddev/`, `composer.json`):

```bash
# 1. Configure / start DDEV (config already present under .ddev/)
ddev start

# 2. Install PHP dependencies
ddev composer install

# 3. Install Drupal (Standard profile) — skip if the site already exists
ddev drush site:install standard \
  --account-name=admin \
  --account-pass=admin \
  --site-name="AI Practical Assessment" \
  -y

# 4. Enable the module and apply entity schema / index updates
ddev drush en ticket_management -y
ddev drush updb -y
ddev drush cr

# 5. Seed demo users (idempotent; also runs on install / update_9001)
ddev drush php:eval "print_r(ticket_management_seed_demo_users());"

# 6. Open the site
ddev launch
```

| Item | Value |
|------|--------|
| Site URL | https://ai-practical-assessment.ddev.site |
| Tickets UI | https://ai-practical-assessment.ddev.site/tickets |
| Admin (site install) | `admin` / `admin` |
| Demo users | See [database/seed-data/README.md](database/seed-data/README.md) — password `TicketDemo!23` |

If you ever need a clean DDEV project name/config from scratch:

```bash
ddev config --project-type=drupal11 --docroot=web --php-version=8.3
ddev start
```

---

## How to run tests

PHPUnit runs **inside DDEV**. Functional tests use `SIMPLETEST_BASE_URL=http://web` (set in `.ddev/config.yaml`).

```bash
# Entire ticket_management suite
ddev exec vendor/bin/phpunit -c web/core/phpunit.xml.dist \
  web/modules/custom/ticket_management/tests

# By layer
ddev exec vendor/bin/phpunit -c web/core/phpunit.xml.dist \
  web/modules/custom/ticket_management/tests/src/Unit

ddev exec vendor/bin/phpunit -c web/core/phpunit.xml.dist \
  web/modules/custom/ticket_management/tests/src/Kernel

ddev exec vendor/bin/phpunit -c web/core/phpunit.xml.dist \
  web/modules/custom/ticket_management/tests/src/Functional
```

After schema changes (e.g. indexes): `ddev drush updb -y && ddev drush cr` before re-testing.

Results summary: [test-results.md](test-results.md) · Strategy: [test-strategy.md](test-strategy.md).

---

## API summary

Custom JSON REST under `/api/tickets`. Auth: session cookie for mutations; send `X-CSRF-Token` from `/session/token` on POST/PATCH.

| Method | Path | Purpose |
|--------|------|---------|
| `GET` | `/api/tickets` | List + keyword (`search`) + `status` filter |
| `GET` | `/api/tickets/{id}` | Ticket + comments |
| `POST` | `/api/tickets` | Create (status always `open`) |
| `PATCH` | `/api/tickets/{id}` | Update non-status fields |
| `PATCH` | `/api/tickets/{id}/status` | State-machine transition |
| `POST` | `/api/tickets/{id}/comments` | Add comment |

**Full request/response schemas, validation, and error codes:** [api-contract.md](api-contract.md).

Status transitions are enforced by `TicketStateMachine` (and again in `Ticket::preSave()`). Illegal moves return **422** with `invalid_transition`.

---

## UI routes

| Path | Screen |
|------|--------|
| `/tickets` | List, search, status filter |
| `/tickets/add` | Create ticket |
| `/tickets/{id}` | Detail, status actions, comments |

Twig + vanilla JS (`ticket_management/ticket_app`). No npm.

---

## Architecture (short)

- Custom content entities: `ticket`, `ticket_comment` (not Node / core Comment)
- Services: `ticket_management.state_machine`, `ticket_management.ticket_query`
- Indexes on `ticket.status` / `ticket.created` via `TicketStorageSchema` + `update_9002`

Docs: [design-notes.md](design-notes.md) · [data-model.md](data-model.md) · [acceptance-criteria.md](acceptance-criteria.md).

---

## Known limitations

- **MVP scope:** no user CRUD UI/API; comments are append-only (no edit/delete); no soft delete.
- **Permissions are flat:** list/query uses route permission + Database API; per-ticket ownership visibility is not modelled (see code-review F2 deferred).
- **Keyword search** is SQL `LIKE %…%` on title/description only — not full-text; description column is not indexed for leading-wildcard LIKE.
- **JSON:API** is enabled as a dependency; the assessment UX and primary HTTP contract use the **custom REST** layer above.
- **Demo passwords** (`TicketDemo!23`, site `admin`/`admin`) are for local DDEV only — never for production.
- Functional tests require DDEV `web_environment` (`SIMPLETEST_*`); host PHPUnit against this project is unsupported.

---

## Assessment deliverables index

| Doc | Purpose |
|-----|---------|
| [candidate-info.md](candidate-info.md) | Candidate / tool / timebox |
| [tool-workflow.md](tool-workflow.md) | How Cursor was used |
| [requirements-analysis.md](requirements-analysis.md) | Requirements |
| [implementation-plan.md](implementation-plan.md) | Tasks / milestones |
| [code-review-notes.md](code-review-notes.md) / [review-fixes.md](review-fixes.md) | Review |
| [debugging-notes.md](debugging-notes.md) | CSRF 403 diagnosis |
| [pr-description.md](pr-description.md) | PR body |
| [reflection.md](reflection.md) | What went well / hard |
| [final-ai-usage-summary.md](final-ai-usage-summary.md) | AI usage summary |
