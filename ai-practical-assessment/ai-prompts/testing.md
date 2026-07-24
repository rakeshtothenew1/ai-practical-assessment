# AI prompts — Testing

Phase: test strategy + PHPUnit Unit / Kernel / Functional for `ticket_management`.

---

## Prompt — Test strategy + PHPUnit suite (Step 12)

```text
Write test-strategy.md, then generate PHPUnit tests under
web/modules/custom/ticket_management/tests/src/{Unit,Kernel,Functional}/:
TicketStateMachineUnitTest.php (pure unit), TicketStateMachineKernelTest.php
(entity save rejects invalid transitions), TicketApiFunctionalTest.php (hits
the REST endpoints, asserts 200 for valid transitions and 422 for invalid
ones — the mandatory Core test tier). Tell me the exact `ddev exec phpunit`
command to run them scoped to this module.
```

**Outputs:**
- `ai-practical-assessment/test-strategy.md`
- `tests/src/Unit/TicketStateMachineUnitTest.php`
- `tests/src/Kernel/TicketStateMachineKernelTest.php`
- `tests/src/Functional/TicketApiFunctionalTest.php`
- Run record → `test-results.md`

**Canonical run command:**

```bash
ddev exec vendor/bin/phpunit -c web/core/phpunit.xml.dist \
  web/modules/custom/ticket_management/tests
```

---

## Follow-up prompts that were needed (env / deps)

```text
My Ticket entity's baseFieldDefinitions() use setType('list_string') for the
priority and status fields, but tests fail with "The field_item:list_string
plugin does not exist" because the options core module isn't declared as a
dependency. Fix by adding drupal:options to ticket_management.info.yml and
'options' to $modules in Kernel/Functional tests. Show the diff.
```

```text
Add a web_environment section to .ddev/config.yaml that sets:
SIMPLETEST_DB=mysql://db:db@db/db
SIMPLETEST_BASE_URL=http://web
BROWSERTEST_OUTPUT_DIRECTORY=/var/www/html/web/sites/simpletest/browser_output
(use the actual DDEV project hostname for documentation; functional tests
inside the container must use http://web). Also gitignore web/sites/simpletest/.
```

---

## Reusable testing template

```text
For Drupal 11 module [module] under DDEV:
1. Write test-strategy.md (layers, what each covers, how to run via ddev exec)
2. Unit tests for pure services (no bootstrap)
3. Kernel tests for entity save / service integration
4. Functional BrowserTestBase tests for REST (200 valid / 422 invalid transitions)
5. List required $modules (including transitive deps like options, field, file)
6. Give the exact ddev exec phpunit command scoped to the module
```
