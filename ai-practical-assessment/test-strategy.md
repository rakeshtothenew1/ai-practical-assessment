# Test strategy — Support Ticket Management

How we verify `ticket_management` against [`requirements-analysis.md`](requirements-analysis.md), [`api-contract.md`](api-contract.md), and [`design-notes.md`](design-notes.md).

## Runner (always via DDEV)

Install PHPUnit tooling once (if `vendor/bin/phpunit` is missing):

```bash
ddev composer require --dev drupal/core-dev --with-all-dependencies
```

**Scoped to this module (Unit + Kernel + Functional):**

```bash
ddev exec 'vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/ticket_management/tests'
```

`SIMPLETEST_DB` and `BROWSERTEST_OUTPUT_DIRECTORY` are set in `.ddev/config.yaml` `web_environment`.

**Important:** `SIMPLETEST_BASE_URL` must be `http://web` (in-container hostname). Do **not** use `https://ai-practical-assessment.ddev.site` — that URL is for browsers on the host and is not reachable from PHPUnit inside the web container.

Restart after changing DDEV config: `ddev restart`.

```bash
ddev exec 'vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/ticket_management/tests'
```

Or override explicitly (matches what worked for Functional tests):

```bash
ddev exec 'SIMPLETEST_BASE_URL=http://web SIMPLETEST_DB=mysql://db:db@db/db vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/ticket_management/tests'
```

No Node-based front-end test runner.

---

## Test pyramid

| Level | Class | Focus |
|-------|--------|--------|
| **Unit** | `TicketStateMachineUnitTest` | Pure PHP transition matrix — no DB |
| **Kernel** | `TicketStateMachineKernelTest` | Entity `save()` rejects illegal transitions via `preSave` |
| **Functional** | `TicketApiFunctionalTest` | **Mandatory core tier** — HTTP REST: **200** valid status, **422** invalid |

---

## Unit — `TicketStateMachineUnitTest`

| From → To | Expect |
|-----------|--------|
| open → in_progress / cancelled | allow |
| in_progress → resolved / cancelled | allow |
| resolved → closed | allow |
| open → closed / resolved | deny (`InvalidTicketTransitionException`) |
| closed → * / cancelled → * | deny |
| same → same | deny |

Assert `canTransition()`, `assertTransition()`, `getAllowedTargets()`.

---

## Kernel — `TicketStateMachineKernelTest`

| Case | Assertion |
|------|-----------|
| Create ticket | Status forced to `open` |
| Valid transition then save | Persisted status updates |
| Invalid transition then save | Exception; reloaded status unchanged |

---

## Functional — `TicketApiFunctionalTest` (mandatory)

Hits custom REST with an authenticated user:

| Request | Expect |
|---------|--------|
| `PATCH /api/tickets/{id}/status` `{"status":"in_progress"}` from `open` | **200** |
| `PATCH /api/tickets/{id}/status` `{"status":"closed"}` from `open` | **422** + JSON error body (`invalid_transition`) |
| `GET /api/tickets` | **200** |
| `POST /api/tickets` | **201** |

---

## Edge cases coverage map

| Edge case | Covered by |
|-----------|------------|
| Invalid transition | Unit + Kernel + Functional (422) |
| Empty search | Manual / future kernel query test |
| Comment on missing ticket | Manual / future API test |
| XSS in comment | Manual Twig/JS (`escapeHtml`) |
| Concurrent stale transition | Kernel re-read + Functional 422 |

---

## Manual UI smoke

Walk [`ui-flow.md`](ui-flow.md): `/tickets`, `/tickets/add`, `/tickets/{id}` after `ddev launch`.

---

## Definition of done

- [ ] Unit tests green via `ddev exec` phpunit (module path)
- [ ] Kernel: invalid save rejected
- [ ] Functional: valid transition **200**, invalid **422**
- [ ] Results noted in `test-results.md`
