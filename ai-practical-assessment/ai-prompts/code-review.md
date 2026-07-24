# AI prompts — Code review

Phase: review `ticket_management`, propose fixes, apply only approved items, log before/after.

---

## Prompt — Review + approve-then-fix (Step 13)

```text
Review web/modules/custom/ticket_management/ for Drupal coding standards,
security (unescaped output, missing access checks on API routes, raw SQL),
missing indexes on the search query, unclear naming. Summarize in
code-review-notes.md, then apply fixes I approve and log before/after in
review-fixes.md, noting rejected suggestions and why.
```

**Outputs:**
- `code-review-notes.md` (findings with severity)
- `review-fixes.md` (before/after + deferred/rejected)

---

## Approved set (this assessment)

```text
Apply the recommended set: F0 (CSRF on write routes), F1 (indexes via
storage_schema + update hook), F3 (derive table name from entity storage),
F4 (controller docblock summaries), F5 (SelectInterface type hint).

Defer/reject as documented:
- F2 per-entity view access on list (flat permission model by design)
- F6 remove redundant isAuthenticated() (keep defence-in-depth)
- F7 empty-PATCH wording (cosmetic)
```

---

## Reusable code-review template

```text
Review [path] against:
- Drupal coding standards / PSR-12
- Security: XSS (Twig/JS escaping), CSRF on session-auth mutations, access
  checks on routes, no raw SQL string concat
- Performance: indexes for search/filter/sort columns
- Naming clarity

Produce code-review-notes.md with id, severity, finding, fix option.
Do NOT change code until I approve which finding IDs to apply.
Then apply only those and write review-fixes.md with before/after snippets
and a "Deferred / not applied" section with rationale.
```
