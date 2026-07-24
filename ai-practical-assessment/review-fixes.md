# Review fixes — `ticket_management`

Source review: `code-review-notes.md`. Approved set: **F0, F1, F3, F4, F5**.
Deferred/rejected: **F2, F6, F7** (rationale at the bottom).

Post-fix steps required (schema change): run migrations inside DDEV.

```bash
ddev drush updb -y      # applies ticket_management_update_9002 (indexes)
ddev drush cr           # rebuild routing + services caches
```

---

## F0 — Enforce CSRF on write routes (High) — APPLIED

**File:** `ticket_management.routing.yml`

Added `_csrf_request_header_token: 'TRUE'` to all four session-authenticated write routes. Safe because `js/ticket-app.js` already fetches `/session/token` and sends `X-CSRF-Token` on every non-GET request.

**Before** (representative — `post`):

```yaml
  requirements:
    _permission: 'create ticket'
    _user_is_logged_in: 'TRUE'
```

**After:**

```yaml
  requirements:
    _permission: 'create ticket'
    _user_is_logged_in: 'TRUE'
    _csrf_request_header_token: 'TRUE'
```

Same one-line addition applied to `api.tickets.patch`, `api.tickets.patch_status`, and `api.tickets.comments.post`. GET routes left unchanged (CSRF does not apply to safe methods).

---

## F1 — Indexes on search/sort columns (Medium) — APPLIED

**New file:** `src/TicketStorageSchema.php` — custom storage-schema handler adding indexes on `status` and `created`.

```php
protected function getEntitySchema(ContentEntityTypeInterface $entity_type, $reset = FALSE): array {
  $schema = parent::getEntitySchema($entity_type, $reset);
  $base_table = $this->storage->getBaseTable();
  if ($base_table !== NULL && isset($schema[$base_table])) {
    $schema[$base_table]['indexes'] += [
      'ticket__status' => ['status'],
      'ticket__created' => ['created'],
    ];
  }
  return $schema;
}
```

**File:** `src/Entity/Ticket.php` — registered the handler.

Before:

```php
  handlers: [
    'access' => TicketAccessControlHandler::class,
    'view_builder' => EntityViewBuilder::class,
    'list_builder' => TicketListBuilder::class,
  ],
```

After:

```php
  handlers: [
    'access' => TicketAccessControlHandler::class,
    'view_builder' => EntityViewBuilder::class,
    'list_builder' => TicketListBuilder::class,
    'storage_schema' => TicketStorageSchema::class,
  ],
```

**File:** `ticket_management.install` — update hook to apply indexes on existing installs.

```php
function ticket_management_update_9002(): void {
  \Drupal::entityDefinitionUpdateManager()
    ->updateEntityType(\Drupal::entityTypeManager()->getDefinition('ticket'));
}
```

Note: `description` uses `LIKE '%…%'`, which cannot use a standard B-tree index; left out of scope (full-text search would be a separate feature).

---

## F3 — Derive table name from entity storage (Medium) — APPLIED

**File:** `src/Service/TicketQueryService.php`

Before — hardcoded table name, `Connection` only:

```php
public function __construct(
  private readonly Connection $database,
) {}
// ...
$count_query = $this->database->select('ticket', 't');
$query = $this->database->select('ticket', 't')
```

After — resolved from entity storage via injected `EntityTypeManagerInterface`:

```php
public function __construct(
  private readonly Connection $database,
  private readonly EntityTypeManagerInterface $entityTypeManager,
) {}

private function ticketTable(): string {
  return $this->entityTypeManager->getStorage('ticket')->getBaseTable() ?? 'ticket';
}
// ...
$table = $this->ticketTable();
$count_query = $this->database->select($table, 't');
$query = $this->database->select($table, 't')
```

**File:** `ticket_management.services.yml` — added the new argument:

```yaml
  ticket_management.ticket_query:
    class: Drupal\ticket_management\Service\TicketQueryService
    arguments: ['@database', '@entity_type.manager']
```

---

## F4 — Docblock summaries (Low) — APPLIED

**File:** `src/Controller/TicketApiController.php`

Replaced route-string docblocks with proper sentence summaries, keeping the route as a secondary line. Example:

Before:

```php
  /**
   * GET /api/tickets?search=&status=
   */
  public function collection(Request $request): JsonResponse {
```

After:

```php
  /**
   * Lists tickets, optionally filtered by search keyword and status.
   *
   * Route: GET /api/tickets?search=&status=&page=&limit=
   */
  public function collection(Request $request): JsonResponse {
```

Same treatment applied to `get()`, `post()`, `patch()`, `patchStatus()`, `postComment()`.

---

## F5 — Type-hint `applyFilters()` param (Low) — APPLIED

**File:** `src/Service/TicketQueryService.php`

Before:

```php
  /**
   * @param \Drupal\Core\Database\Query\SelectInterface $query
   *   The query.
   */
  private function applyFilters($query, string $search, ?string $status): void {
```

After:

```php
  private function applyFilters(SelectInterface $query, string $search, ?string $status): void {
```

(Added `use Drupal\Core\Database\Query\SelectInterface;`.)

---

## Deferred / not applied

### F2 — Per-entity view access on the list — DEFERRED
The list query returns tickets to anyone with `access ticket overview` without consulting per-entity `view` grants. **Not applied** because the current permission model is intentionally flat (a single `view ticket` permission, no per-ticket ownership/visibility rules), so per-entity filtering would add cost without changing the outcome. Revisit if ownership-based or private tickets are introduced. Documented as a known, intentional divergence.

### F6 — Remove redundant in-controller `isAuthenticated()` — REJECTED
The route already enforces `_user_is_logged_in`, so the controller check is redundant. **Kept** as defence-in-depth: it guarantees a valid author id even if a route requirement is ever loosened, and it is harmless. Removing it trades robustness for a negligible cleanup.

### F7 — Clearer empty-PATCH handling — REJECTED
Current behaviour (422 with `code: empty` when no updatable fields are sent) is already explicit and testable. **No change** — the suggested rewording is cosmetic and would churn tests for no functional gain.
