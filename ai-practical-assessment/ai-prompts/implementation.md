# AI prompts — Implementation

Phase: module scaffold, entities/seed, REST API, state machine, Twig/JS UI.  
Code location: `web/modules/custom/ticket_management/`.  
Commands: only `ddev drush` / `ddev composer` / `ddev exec` (never host PHP).

---

## Prompt 1 — Module scaffold (Step 7)

```text
Scaffold web/modules/custom/ticket_management/ with ticket_management.info.yml
(core_version_requirement: ^11), .module file, ticket_management.routing.yml,
ticket_management.permissions.yml, ticket_management.services.yml, and a
PSR-4 src/ directory (Drupal\ticket_management\...). Give me the exact
`ddev drush` command to enable it.
```

**Enable:** `ddev drush en ticket_management -y`

---

## Prompt 2 — Entities + seed users (Step 8)

```text
Define Ticket and Comment as ContentEntityBase classes with
baseFieldDefinitions(): Ticket(title[string,required], description[text_long],
priority[list_string:low/medium/high], status[list_string:open/in_progress/
resolved/closed/cancelled, default 'open'], assigned_to[entity_reference:user],
created_by[entity_reference:user], created[timestamp], changed[timestamp]);
Comment(ticket_id[entity_reference:ticket,required], message[text_long,
required], created_by[entity_reference:user], created[timestamp]). Also write
an update hook (ticket_management_update_9001 or a hook_install seed) that
creates 5 demo users (id/name/email/role) via user creation API. Write
database/setup-notes.md documenting: MySQL/MariaDB via DDEV, how
`ddev drush updb` applies entity schema, how `ddev drush si` relates.
```

---

## Prompt 3 — Backend REST APIs (Step 9)

```text
Implement REST endpoints in ticket_management: GET /api/tickets
(?search=&status=), GET /api/tickets/{id}, POST /api/tickets, PATCH
/api/tickets/{id}, PATCH /api/tickets/{id}/status (must call
TicketStateMachine, return 422 with JSON error body on invalid transition),
POST /api/tickets/{id}/comments. Use a Drupal routing.yml + controller
class returning JsonResponse. Update api-contract.md to match exactly.
```

---

## Prompt 4 — State machine (Step 10)

```text
Implement src/Service/TicketStateMachine.php: transition map
open=>[in_progress,cancelled], in_progress=>[resolved,cancelled],
resolved=>[closed], closed=>[], cancelled=>[]. Methods: canTransition(string
$from, string $to): bool, assertTransition(string $from, string $to): void
throwing InvalidTicketTransitionException. Register it in
ticket_management.services.yml. Wire it into entity preSave() so it's
enforced on every save path, not just the REST controller.
```

---

## Prompt 5 — Frontend (Step 11)

```text
Build a Twig-rendered controller/theme for: ticket list (search box + status
dropdown, fetches GET /api/tickets), ticket detail (fields, status buttons
showing only valid next-states, comments + add-comment form), create-ticket
form. Add web/modules/custom/ticket_management/js/ticket-app.js using fetch()
with loading/empty/success/error DOM states. No Node.js/npm build step —
plain JS/CSS only, enqueued via the module's libraries.yml.
```

---

## Reusable implementation checklist prompt

```text
Implement only what is listed in implementation-plan.md task [Tn] for module
ticket_management. Follow .cursor/rules/drupal.mdc:
- DDEV-only CLI suggestions
- Custom code under web/modules/custom/ticket_management
- State machine stays a standalone service
- Twig + vanilla JS; no npm
Show exact ddev commands to enable / updb / cr after changes.
```
