# Tool workflow — Cursor

How Cursor was used for the Support Ticket Management assessment. See also [tool-specific/cursor-workflow/](tool-specific/cursor-workflow/) and [final-ai-usage-summary.md](final-ai-usage-summary.md).

---

## Tooling setup

| Item | Choice |
|------|--------|
| Product | Cursor IDE + Agent |
| Project rules | `.cursor/rules/drupal.mdc` (DDEV-only CLI, custom module path, no Node, state machine as service) |
| Mirrored rules | [tool-specific/cursor-workflow/cursor-rules-or-instructions.md](tool-specific/cursor-workflow/cursor-rules-or-instructions.md) |
| Context | [project-context.md](tool-specific/cursor-workflow/project-context.md), [spec.md](tool-specific/cursor-workflow/spec.md) |

---

## Typical loop

1. **Spec first** — Human prompt defined the step (requirements → design → tasks → implement → test → review → debug → docs).
2. **Agent implements** under `web/modules/custom/ticket_management` following Drupal/DDEV rules.
3. **Human verifies** with `ddev drush`, `ddev composer`, `ddev exec vendor/bin/phpunit`, and browser (`ddev launch`).
4. **Iterate** on failures (module deps in tests, `SIMPLETEST_BASE_URL`, CSRF after review, etc.).

---

## What AI did well

- Scaffolding entities, services, routing, Twig/JS, and PHPUnit skeletons from the written specs.
- Keeping state-machine logic in an injectable service and wiring `preSave()` enforcement.
- Producing review notes with severity, then applying only approved fixes.
- Diagnosing CSRF **403** vs state-machine **422** from real PHPUnit output.

## What stayed human-owned

- Product choices (custom entities not Node; flat permissions; MVP scope).
- Approving which review findings to fix (F0/F1/F3–F5 vs deferred F2).
- Running DDEV commands and interpreting site behaviour.
- Final documentation accuracy and credential hygiene.

## Prompt style that worked

- Step-numbered prompts with explicit deliverable paths (`code-review-notes.md`, `review-fixes.md`).
- Constraints restated in-prompt (“custom entity, not core”; “Twig + vanilla JS only”).
- “Paste real error / propose cause and fix” for debugging rather than guessing.

## Guardrails enforced via rules

- Never suggest host `php` / `composer` / `drush` / `mysql`.
- No npm / frontend build tools.
- State machine not inlined in controllers.
- Secrets not invented for production; demo passwords documented as local-only.

---

## Session artefacts

| Artefact | Location |
|----------|----------|
| Spec / context | `tool-specific/cursor-workflow/` |
| Review trail | `code-review-notes.md`, `review-fixes.md` |
| Debug trail | `debugging-notes.md` |
| Tests | `test-strategy.md`, `test-results.md` |
