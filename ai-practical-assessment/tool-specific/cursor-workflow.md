# Cursor workflow

Tool-specific workflow for building the Support Ticket Management System with **Cursor IDE + Agent** on Drupal 11 / DDEV.

This file is the entry point for assessors. Detailed artefacts live in [`cursor-workflow/`](cursor-workflow/). Assessment-wide summary: [`../tool-workflow.md`](../tool-workflow.md).

---

## Why Cursor

| Need | How Cursor was used |
|------|---------------------|
| Persistent project rules | `.cursor/rules/drupal.mdc` (always-on DDEV / module / no-Node / state-machine rules) |
| Stepwise delivery | Numbered Step prompts with explicit file outputs |
| Code + docs in one loop | Agent edits PHP/Twig/JS and markdown under `ai-practical-assessment/` |
| Review before change | Code-review findings listed first; fixes only after human approval |

---

## Folder map

```text
tool-specific/
├── cursor-workflow.md              ← this file (overview)
└── cursor-workflow/
    ├── project-context.md          # Stack + ddev command conventions
    ├── cursor-rules-or-instructions.md  # Human-readable copy of project rules
    └── spec.md                     # Technical spec for ticket_management
```

Related (outside this folder):

| Doc | Path |
|-----|------|
| Prompt log by phase | [`../ai-prompts/`](../ai-prompts/) |
| Root tool workflow | [`../tool-workflow.md`](../tool-workflow.md) |
| AI usage summary | [`../final-ai-usage-summary.md`](../final-ai-usage-summary.md) |
| Live Cursor rules | [`.cursor/rules/drupal.mdc`](../../.cursor/rules/drupal.mdc) |

---

## Setup (once per machine)

1. Open the repo root in Cursor.
2. Confirm `.cursor/rules/drupal.mdc` is active (DDEV-only CLI, custom code under `ticket_management`, no npm, state machine as service).
3. Ensure Docker + DDEV are running: `ddev start`.
4. Prefer Agent/Chat with step prompts from [`../ai-prompts/`](../ai-prompts/) rather than open-ended “build everything”.

---

## End-to-end workflow

```text
Planning  →  Design  →  Implementation  →  Testing  →  Code review  →  Debugging  →  Docs
   │            │              │               │             │              │         │
   └─ ai-prompts/planning.md … documentation.md (prompt archive)
```

| Phase | Cursor role | Human role |
|-------|-------------|------------|
| Planning | Draft requirements, plan, acceptance criteria | Confirm scope (custom entities, MVP) |
| Design | Spec, data model, API contract, design/UI notes | Approve contracts before coding |
| Implementation | Scaffold module, entities, REST, state machine, Twig/JS | Run `ddev drush` / smoke UI |
| Testing | Write Unit/Kernel/Functional tests + strategy | Run `ddev exec vendor/bin/phpunit …` |
| Code review | Produce `code-review-notes.md` | Approve finding IDs; agent applies only those |
| Debugging | Diagnose from real PHPUnit/logs | Confirm fix; re-run tests |
| Documentation | README, PR, reflection, AI summary | Fill candidate contact if required |

---

## Guardrails (enforced in rules + prompts)

- All PHP / Composer / Drush / MySQL / PHPUnit via **`ddev …`** only.
- Custom application code only under `web/modules/custom/ticket_management`.
- **TicketStateMachine** is a standalone service — not inline in controllers/forms.
- Frontend: Twig + vanilla JS — **no Node.js / npm / bundlers**.
- Mutations: session auth + **CSRF** (`_csrf_request_header_token` / `X-CSRF-Token`).
- Do not invent production secrets; demo passwords are local DDEV only.

---

## Prompt → artefact cheat sheet

| Prompt file | Primary outputs |
|-------------|-----------------|
| [`ai-prompts/planning.md`](../ai-prompts/planning.md) | `requirements-analysis.md`, `implementation-plan.md`, `acceptance-criteria.md` |
| [`ai-prompts/design.md`](../ai-prompts/design.md) | `spec.md`, `data-model.md`, `api-contract.md`, `design-notes.md`, `ui-flow.md` |
| [`ai-prompts/implementation.md`](../ai-prompts/implementation.md) | Module under `web/modules/custom/ticket_management/` |
| [`ai-prompts/testing.md`](../ai-prompts/testing.md) | PHPUnit suite, `test-strategy.md`, `test-results.md` |
| [`ai-prompts/code-review.md`](../ai-prompts/code-review.md) | `code-review-notes.md`, `review-fixes.md` |
| [`ai-prompts/debugging.md`](../ai-prompts/debugging.md) | `debugging-notes.md` |
| [`ai-prompts/documentation.md`](../ai-prompts/documentation.md) | README, PR, reflection, candidate/tool docs |

---

## Verification commands (always via DDEV)

```bash
ddev start
ddev composer install
ddev drush en ticket_management -y
ddev drush updb -y
ddev drush cr
ddev launch /tickets

ddev exec vendor/bin/phpunit -c web/core/phpunit.xml.dist \
  web/modules/custom/ticket_management/tests
```

---

## Outcomes from this assessment

- Custom entities `ticket` + `ticket_comment` (not Node / core Comment).
- REST under `/api/tickets` + Twig UI at `/tickets`.
- State machine enforced in service + `Ticket::preSave()`.
- Review fix: CSRF on write routes; debug fix: functional tests send CSRF token.
- Indexes on `status` / `created` via `TicketStorageSchema`.
