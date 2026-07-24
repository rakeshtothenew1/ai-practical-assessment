# Code review — `ticket_management`

Reviewed: `web/modules/custom/ticket_management/` (entities, services, controllers, access handlers, install, routing, Twig/JS).

Scope: Drupal coding standards, security (output escaping, API access checks, raw SQL), search-query indexing, naming.

Severity: **High** = fix before merge · **Medium** = should fix · **Low** = nice-to-have.

---

## Summary table

| # | Area | Severity | Finding |
|---|------|----------|---------|
| F0 | Security / CSRF | **High** | Session-authenticated write routes (`POST`/`PATCH` tickets, status, comments) declare **no `_csrf_request_header_token`**, so they are open to CSRF. The JS already fetches a token — the backend just doesn't enforce it. |
| F1 | Performance / DB | **Medium** | Search/sort columns (`status`, `created`, `title`) have **no indexes** on the `ticket` base table. |
| F2 | Security / access | **Medium** | `GET /api/tickets` reads via raw DB API and **bypasses per-entity `view` access** (only the coarse `access ticket overview` route permission applies). |
| F3 | Correctness | **Medium** | `TicketQueryService` hardcodes table name `'ticket'` instead of deriving it from entity storage — brittle if `base_table` changes. |
| F4 | Standards | **Low** | Several controller method docblocks are route strings (e.g. `GET /api/tickets`) instead of proper sentence summaries. |
| F5 | Standards | **Low** | `TicketQueryService::applyFilters()` param `$query` is untyped (uses docblock only); should type-hint `SelectInterface`. |
| F6 | Consistency | **Low** | `post()`/`patch()` re-check `isAuthenticated()` even though routes already require `_user_is_logged_in: 'TRUE'` — redundant (harmless). |
| F7 | Robustness | **Low** | `patch()` treats an unknown/only-`status` body as generic 422 `empty`; acceptable but could be clearer. |
| N1 | Note (no change) | — | `preSave()` uses `\Drupal::service()`. Entities cannot use constructor DI; this is the idiomatic exception to the DI rule. |
| N2 | Note (no change) | — | Output escaping is sound: JSON via `JsonResponse`, Twig auto-escape, JS `escapeHtml()`. No `|raw`. No raw SQL string concatenation; `escapeLike()` applied. |

---

## Details

### F0 — Missing CSRF protection on write endpoints (High)
The write routes (`api.tickets.post`, `api.tickets.patch`, `api.tickets.patch_status`, `api.tickets.comments.post`) rely on cookie/session auth (`_user_is_logged_in: 'TRUE'`) but do **not** require `_csrf_request_header_token: 'TRUE'`. A cross-origin page could POST/PATCH on behalf of a logged-in user. The frontend already retrieves the token from `/session/token` and sends `X-CSRF-Token`, so the fix is purely to enforce it server-side.

**Fix option:** add `_csrf_request_header_token: 'TRUE'` to all four write routes. (GET routes stay as-is — CSRF does not apply to safe methods.) Confirm the JS sends the `X-CSRF-Token` header on every mutating request.

### F1 — Missing indexes on search columns (Medium)
`TicketQueryService::search()` filters on `status`, does `LIKE` on `title`/`description`, and sorts by `created`. The `ticket` entity declares no indexes, so these become full scans as data grows.

**Fix option:** add a custom `storage_schema` handler (`TicketStorageSchema`) declaring indexes on `status` and `created`, plus an `update_9002` hook to apply on existing installs. (`description` LIKE `%…%` cannot use a normal index; leave as-is or document as out-of-scope full-text.)

### F2 — Raw DB list bypasses entity access (Medium)
`collection()` loads IDs via `Connection::select()` then `loadMultiple()`, returning all matching tickets to anyone with `access ticket overview`. Entity `view` grants are not consulted (unlike `loadCommentsForTicket()` which uses `accessCheck(TRUE)`).

For the MVP permission model (flat `view ticket`) this is consistent, but it silently diverges from entity access. **Fix option:** filter results through `$ticket->access('view', $account)` before returning, or document the coarse model explicitly.

### F3 — Hardcoded table name (Medium)
`->select('ticket', 't')` assumes the base table name. **Fix option:** resolve via `entityTypeManager->getStorage('ticket')->getBaseTable()` (injected) so the query follows the entity definition.

### F4 / F5 — Docblock + type-hint standards (Low)
Drupal standards want a short sentence summary ending in a period, and real type hints where possible. Route method summaries and the untyped `$query` param are the main nits.

### F6 / F7 — Redundant/again-checked auth & empty PATCH (Low)
Route-level `_user_is_logged_in` already guarantees auth; the in-controller check is defensive but redundant. Low priority.

---

## Recommendation

Apply **F0** (security, must-fix), **F1, F3, F4, F5** (clear wins, low risk). Treat **F2** as a decision (coarse vs per-entity access). **F6/F7** optional.
