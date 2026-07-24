# Ticket Management

Custom Drupal 11 module for the Support Ticket Management System.

## Enable

```bash
ddev drush en ticket_management -y
ddev drush updb -y
ddev drush cr
```

## UI

| Path | Screen |
|------|--------|
| `/tickets` | List + search/status filter (`GET /api/tickets`) |
| `/tickets/add` | Create form (`POST /api/tickets`) |
| `/tickets/{id}` | Detail, status buttons, comments |

Assets: `js/ticket-app.js`, `css/ticket-app.css` via library `ticket_management/ticket_app` (no Node/npm).

## API

See assessment `api-contract.md`. Status transitions: `PATCH /api/tickets/{id}/status`.
