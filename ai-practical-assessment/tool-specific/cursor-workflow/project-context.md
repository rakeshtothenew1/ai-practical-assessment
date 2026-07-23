# Project context — Cursor workflow

## Stack

| Layer | Choice |
|-------|--------|
| CMS | Drupal 11 |
| Local runtime | DDEV |
| PHP | 8.3 (inside DDEV) |
| Database | MySQL 8.0 by default (MariaDB supported via `.ddev/config.yaml`) |
| API | JSON:API + custom REST layer |
| Frontend | Twig + vanilla JavaScript |
| Build tooling | None — **no Node.js / npm / yarn / pnpm / webpack / Vite / Gulp** |
| Tests | PHPUnit |

## Command conventions

All PHP, Composer, Drush, MySQL, and PHPUnit work runs **inside DDEV**. Prefer:

| Task | Command pattern |
|------|-----------------|
| Shell / one-off PHP | `ddev exec …` or `ddev ssh` |
| Composer | `ddev composer …` |
| Drush | `ddev drush …` |
| PHPUnit | `ddev exec phpunit …` (or `ddev exec vendor/bin/phpunit …`) |
| DB client | `ddev mysql` / `ddev exec mysql …` — never host `mysql` |

Do **not** assume host-level PHP 8.3 or MySQL. Do not suggest bare `php`, `composer`, `drush`, or `mysql` against the host.

## Application surface

- **JSON:API** for entity-oriented HTTP APIs.
- **Custom REST** resources/controllers for ticket-management domain endpoints that JSON:API does not cover cleanly.
- **Twig** templates for server-rendered UI; enhance with **vanilla JS** only (no bundler, no npm packages).

## Custom code location

Primary custom module path (when implemented):

`web/modules/custom/ticket_management`

## Related docs

- Cursor project rules: [`.cursor/rules/drupal.mdc`](../../../.cursor/rules/drupal.mdc)
- Human-readable rules copy: [`cursor-rules-or-instructions.md`](cursor-rules-or-instructions.md)
