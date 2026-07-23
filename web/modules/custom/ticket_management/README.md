# Ticket Management

Custom Drupal 11 module for the Support Ticket Management System.

## Enable

```bash
ddev drush en ticket_management -y
ddev drush cr
```

## Structure

- Custom entities, services, REST, and Twig UI will live under `src/` and module root.
- Namespace: `Drupal\ticket_management\` (PSR-4 via Drupal core conventions).
