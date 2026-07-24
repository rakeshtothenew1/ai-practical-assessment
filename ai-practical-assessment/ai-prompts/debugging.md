# AI prompts — Debugging

Phase: diagnose real failures under DDEV / MySQL / PHP 8.3.  
Always paste actual error output — do not invent symptoms.

---

## Prompt template used (Step 14)

```text
[paste actual error / ddev logs -s web output / failing test output]
Diagnose this Drupal 11 issue running under DDEV/MySQL/PHP 8.3: [symptom].
Given TicketStateMachine and the REST controller wiring, propose the likely
cause and a fix, and how to verify it.
```

---

## Prompt as filled (CSRF 403 after code-review fix)

```text
PHPUnit TicketApiFunctionalTest failures:

✘ Valid status transition returns 200
   Failed asserting that 403 is identical to 200.
✘ Invalid status transition returns 422
   Failed asserting that 403 is identical to 422.
✔ List tickets returns 200
✘ Create ticket returns 201
   Failed asserting that 403 is identical to 201.

Diagnose this Drupal 11 issue running under DDEV/MySQL/PHP 8.3: all POST/PATCH
ticket API calls return 403 while GET /api/tickets still returns 200.

Given TicketStateMachine and the REST controller wiring, propose the likely
cause and a fix, and how to verify it. Write findings to debugging-notes.md.
```

**Root cause (documented):** write routes require `_csrf_request_header_token`; functional `jsonRequest()` omitted `X-CSRF-Token`. Not a state-machine bug (those return 422).

**Verify:**

```bash
ddev exec vendor/bin/phpunit -c web/core/phpunit.xml.dist \
  web/modules/custom/ticket_management/tests/src/Functional/TicketApiFunctionalTest.php --testdox
```

**Output:** `ai-practical-assessment/debugging-notes.md`

---

## Reusable debugging template

```text
Reproduce with:
  ddev exec vendor/bin/phpunit …   OR   ddev logs -s web

Paste the full failure. Then:
1. Separate access failures (401/403) from domain failures (422) from 5xx
2. Trace TicketStateMachine vs TicketApiController vs routing.yml requirements
3. Propose likely cause, minimal fix, and exact verification command
4. Log symptom / diagnosis / fix / verification in debugging-notes.md
Do not weaken security (e.g. do not remove CSRF) just to make tests pass.
```
