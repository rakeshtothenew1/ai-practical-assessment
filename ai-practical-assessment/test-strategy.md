# Test strategy — Support Ticket Management

How we verify `ticket_management` against [`requirements-analysis.md`](requirements-analysis.md), [`api-contract.md`](api-contract.md), and [`design-notes.md`](design-notes.md).

**Runner (always in DDEV):**

```bash
ddev exec vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/ticket_management/tests
# or module-local phpunit.xml if provided
ddev exec phpunit web/modules/custom/ticket_management/tests
```

No Node-based front-end test runner in MVP.

---

## Test pyramid

| Level | Tool | Focus |
|-------|------|--------|
| **Unit** | PHPUnit | `TicketStateMachine` transition matrix; pure helpers (LIKE escape) |
| **Kernel** | PHPUnit + Drupal kernel | Entity save/load, constraints, query service against DB, access handler permissions |
| **Functional** (optional stretch) | BrowserTestBase | Twig list/detail/create happy paths + error messengers |

---

## Unit — state machine

Cover every **allowed** and representative **disallowed** edge:

| From | To | Expect |
|------|-----|--------|
| open → in_progress | allow |
| open → cancelled | allow |
| in_progress → resolved | allow |
| in_progress → cancelled | allow |
| resolved → closed | allow |
| open → closed / resolved | deny |
| closed → * | deny |
| cancelled → * | deny |
| same status → same | deny |

Assert `canTransition` / `assertTransition` / `getAllowedTargets`. No Drupal bootstrap required if matrix is pure PHP.

---

## Kernel — entities, validation, query, API-ish services

| Area | Cases |
|------|--------|
| Ticket create | Defaults `status=open`; `created_by` set; rejects invalid priority |
| Ticket update | Non-status fields change; status via machine only |
| Assignee | Valid uid OK; invalid uid → violation |
| Comment | Requires existing ticket; empty message fails; missing ticket fails |
| TicketQueryService | Empty `q` returns pages; status filter; keyword on title/description; invalid status rejected |
| Access | User without permission denied; with permission allowed |

Use DDEV MySQL (kernel tests’ DB connection as configured in phpunit.xml).

---

## Edge cases → tests

| Edge case | Test type | Assertion |
|-----------|-----------|-----------|
| Invalid transition | Unit (+ kernel apply) | Exception / 422 path; DB status unchanged |
| Empty search | Kernel | Full (paginated) result set; not error |
| Comment on nonexistent ticket | Kernel / HTTP | 404; no `ticket_comment` row |
| XSS in comment | Functional or render kernel | Escaped output in Twig (`<script>` not executed) |
| Orphaned assignee | Kernel | Invalid assign on save fails; display placeholder if fixture orphan |
| Concurrent status update | Kernel | Second transition against new status denied if illegal |

---

## Manual / exploratory (Twig UI)

Walk [`ui-flow.md`](ui-flow.md) states per screen:

- List: loading → success / empty / error  
- Create: validation errors vs redirect success  
- Detail: transitions only showing allowed targets; comment empty error; terminal tickets  

Commands: `ddev start`, `ddev drush en ticket_management -y`, `ddev launch /tickets`.

---

## Out of scope for automated MVP

- Full JSON:API compliance suite  
- Load / performance testing  
- Cross-browser matrix beyond one modern browser smoke check  
- Visual regression  

---

## Definition of done (testing)

- [ ] All state-machine unit tests green via `ddev exec`  
- [ ] Kernel coverage for create/list filter/comment/transition happy + fail paths  
- [ ] Edge cases in the table above either automated or explicitly manual-checked and noted in `test-results.md`  
