# Final AI usage summary

## Overview

| Item | Detail |
|------|--------|
| Tool | Cursor Agent |
| Project | Support Ticket Management (`ticket_management`) on Drupal 11 / DDEV |
| Approx. span | 2026-07-23 → 2026-07-24 |
| Docs package | `ai-practical-assessment/` |

AI was used as a **pair programmer with project rules**, not as an unsupervised code dump. Human owned requirements choices, fix approvals, and DDEV verification.

---

## Where AI helped most

| Phase | Contribution |
|-------|----------------|
| Analysis / design | Drafted requirements, data model, API contract, design notes, UI flow, acceptance criteria, implementation plan |
| Scaffold | Module info, entities, access handlers, services.yml, routing, install seed |
| Business logic | `TicketStateMachine` + unit tests; `preSave` enforcement |
| API / UI | `TicketApiController`, Twig templates, vanilla JS fetch + CSRF client |
| Quality | PHPUnit (Unit/Kernel/Functional), code review notes, approved fixes (CSRF, indexes, query table resolution, docblocks) |
| Debugging | Diagnosed real 403 CSRF failure vs state-machine 422 from PHPUnit output |
| Docs | README, PR description, reflection, this summary, candidate/tool workflow |

## Where AI struggled / needed correction

| Issue | Correction |
|-------|------------|
| Missing `options` / `file` / `field` in test `$modules` | Added deps so `list_string` and JSON:API services resolve |
| Functional base URL used public DDEV hostname | Set `SIMPLETEST_BASE_URL=http://web` for in-container BrowserTestBase |
| Kernel test expected bare domain exception | Assert `EntityStorageException` → previous `InvalidTicketTransitionException` |
| Review CSRF fix broke functional writes | Extended `jsonRequest()` with session CSRF token |
| Deprecated `user_load_by_name()` in install | Switched to entity storage `loadByProperties` |

## Prompt patterns that worked

1. **Numbered steps** with explicit file outputs (`code-review-notes.md`, then approved fixes → `review-fixes.md`).
2. **Hard constraints** in-prompt: custom entities; Twig + vanilla JS; DDEV-only CLI; state machine as service.
3. **Approve-before-apply** for review findings (recommended set vs deferred/rejected).
4. **Reproduce first** for debugging (`ddev exec phpunit …`) then cause / fix / verify.

## Estimated effort split (qualitative)

| Activity | Human | AI-assisted |
|----------|-------|-------------|
| Requirements & acceptance | High | Drafting |
| Implementation | Review / steer | Majority of boilerplate |
| Test env / failures | Diagnosis ownership | Fix proposals |
| Security review | Approval decisions | Finding list + patches |
| Final docs | Edit / accuracy | First drafts |

## Integrity notes

- No production secrets were generated for real environments; demo passwords are local DDEV only.
- CSRF was **enforced** rather than disabled to make tests green.
- Out-of-scope items (user CRUD, comment edit, Node modelling, npm) were not added via AI scope creep.

## Related

- [tool-workflow.md](tool-workflow.md)
- [candidate-info.md](candidate-info.md)
- [debugging-notes.md](debugging-notes.md)
- [review-fixes.md](review-fixes.md)
