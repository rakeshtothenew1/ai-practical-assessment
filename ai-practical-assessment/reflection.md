# Reflection

## What went well

- **Spec-before-code** (requirements → data model → API → design → tasks) kept the module aligned with acceptance criteria and avoided Node/core Comment misuse.
- **State machine as a service** plus `Ticket::preSave()` made illegal transitions hard to bypass from API, forms, or direct entity saves.
- **DDEV-first rules** in Cursor prevented a class of “works on my host PHP” failures; PHPUnit env vars (`SIMPLETEST_BASE_URL=http://web`) were the key functional-test unlock.
- **Review → approve → fix** loop produced a clear trail (`code-review-notes.md` / `review-fixes.md`) and caught a real CSRF gap the UI already “assumed” was enforced.

## What was hard

- **Kernel/Functional module graph:** `jsonapi` pulled in `file`/`field` services; tests needed explicit `$modules` entries beyond `ticket_management.info.yml` alone.
- **Exception wrapping:** invalid transitions in `preSave()` surface as `EntityStorageException` with `InvalidTicketTransitionException` as previous — tests had to assert the chain, not only the domain exception.
- **CSRF after hardening:** write routes correctly returned **403**; functional helper lagged behind production JS until `/session/token` was wired into `jsonRequest()`.
- **Separating access failures from domain failures:** 403 (CSRF/permission) vs 422 (invalid transition) looks similar in a failing assertion until you notice GET still passes.

## What I would do differently next time

- Add `_csrf_request_header_token` and a CSRF-aware test helper **in the same commit** as the first mutating route.
- Declare Kernel/Functional `$modules` from a shared trait listing transitive deps early.
- Decide ownership/ACL (F2) at design time so list query either stays “coarse by design” or filters with `accessCheck` from day one.
- Keep a single debugging filename (`debugging-notes.md`) to avoid duplicate notes.

## Takeaways on AI-assisted Drupal work

AI accelerates scaffolding and boilerplate, but **runtime verification under DDEV** and careful reading of HTTP status / exception wrapping remain human-critical. The best prompts restated constraints (custom entities, no npm, state machine as service) and asked for artefacts assessors can audit.
