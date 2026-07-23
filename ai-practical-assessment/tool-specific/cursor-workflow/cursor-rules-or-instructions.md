# Cursor rules / instructions (human-readable)

This file mirrors the always-on project rules in [`.cursor/rules/drupal.mdc`](../../../.cursor/rules/drupal.mdc) for assessors and humans who are not reading Cursor rule files.

## Runtime (DDEV)

- Assume **all** PHP, Composer, Drush, MySQL, and PHPUnit commands run **inside DDEV**.
- Prefix shell suggestions with `ddev exec`, `ddev composer`, `ddev drush`, or `ddev ssh` where relevant.
- Never suggest a bare `php` or `mysql` (or host `composer` / `drush`) assuming host-level PHP/MySQL.
- Run PHPUnit via `ddev exec phpunit` or `ddev exec vendor/bin/phpunit`.

## Custom code location

- Custom code for this assessment lives under:

  `web/modules/custom/ticket_management`

## Coding standards

- Follow **Drupal** coding standards and **PSR-12**.
- Prefer dependency injection over static `\Drupal::service()` calls in new classes.

## Architecture

- **State machine logic must be a standalone service class** (registered in the module’s `services.yml` and injected where needed).
- Do not embed transition rules in controllers, forms, hooks, or Twig.

## Frontend / dependencies

- Frontend is Twig with vanilla JavaScript.
- **No Node.js / npm (or yarn/pnpm/webpack/Vite/Gulp) dependencies anywhere.**

## APIs

- Stack includes **JSON:API** plus a **custom REST** layer for ticket-management endpoints that need bespoke contracts.

## Related context

See [`project-context.md`](project-context.md) for stack and command cheat-sheet details.
