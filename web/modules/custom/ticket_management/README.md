# Ticket Management

Custom Drupal 11 module for the Support Ticket Management System.

**Assessment docs:** [`ai-practical-assessment/README.md`](../../../../ai-practical-assessment/README.md) · [`api-contract.md`](../../../../ai-practical-assessment/api-contract.md)

## Enable

```bash
ddev drush en ticket_management -y
ddev drush updb -y
ddev drush cr
```

Demo users (password `TicketDemo!23`): seeded on install / `update_9001`. Re-seed:

```bash
ddev drush php:eval "print_r(ticket_management_seed_demo_users());"
```

## UI

| Path | Screen |
|------|--------|
| `/tickets` | List + search/status filter |
| `/tickets/add` | Create form |
| `/tickets/{id}` | Detail, status buttons, comments |

Assets: `js/ticket-app.js`, `css/ticket-app.css` via library `ticket_management/ticket_app` (no Node/npm).

## API (summary)

| Method | Path |
|--------|------|
| `GET` | `/api/tickets` |
| `GET` | `/api/tickets/{id}` |
| `POST` | `/api/tickets` |
| `PATCH` | `/api/tickets/{id}` |
| `PATCH` | `/api/tickets/{id}/status` |
| `POST` | `/api/tickets/{id}/comments` |

Mutations require session auth + `X-CSRF-Token`. Full schemas: assessment `api-contract.md`.

## Tests

```bash
ddev exec vendor/bin/phpunit -c web/core/phpunit.xml.dist \
  web/modules/custom/ticket_management/tests
```
