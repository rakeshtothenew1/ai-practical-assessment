# AI prompts — Documentation

Phase: README and Participant Guide deliverables for submission.

---

## Prompt — Final documentation pack (Step 15)

```text
Generate README.md: prerequisites (DDEV, Docker, PHP 8.3 via DDEV — no local
PHP/Node.js needed), setup (ddev config/start, ddev composer install,
ddev drush si, ddev drush updb, seed step, ddev launch), how to run tests
(ddev exec phpunit ...), API summary linking api-contract.md, known
limitations. Also produce pr-description.md, reflection.md,
final-ai-usage-summary.md, candidate-info.md, tool-workflow.md per the
Participant Guide templates.
```

**Outputs (under `ai-practical-assessment/`):**
- `README.md`
- `pr-description.md`
- `reflection.md`
- `final-ai-usage-summary.md`
- `candidate-info.md`
- `tool-workflow.md`

Also refresh root `README.md` and `web/modules/custom/ticket_management/README.md` for enablement, seed users, UI URLs, and test commands.

---

## Prompt — Capture prompts used (this folder)

```text
Generate the below files in ai-prompts folder

 ai-prompts/
    planning.md
    design.md
    implementation.md
    testing.md
    debugging.md
    code-review.md
    documentation.md

Each file should record the actual Step prompts used in this assessment,
plus a short reusable template for the same phase.
```

---

## Reusable documentation template

```text
Produce assessment documentation for [project] on Drupal 11 / DDEV:

1. README.md — prerequisites, setup (ddev start → composer → si → en/updb →
   seed → launch), phpunit via ddev exec, API summary → api-contract.md,
   known limitations
2. candidate-info.md — name, tool, dates, declaration
3. tool-workflow.md — how the AI tool was used with project rules
4. pr-description.md — summary, test plan checklist, out of scope
5. reflection.md — what went well / hard / next time
6. final-ai-usage-summary.md — where AI helped, where it needed correction

Base content on real artefacts already in the repo (do not invent test results
or credentials beyond documented local demo users).
```
