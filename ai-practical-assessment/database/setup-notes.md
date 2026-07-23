# Database setup notes — Ticket Management

## Engine

| Item | Value |
|------|--------|
| Local DB | **MySQL 8.0** by default (MariaDB switchable in `.ddev/config.yaml`) |
| Hosted by | DDEV `db` container |
| App access | Host `db`, user/pass/db name `db` / `db` / `db` (see `settings.ddev.php`) |
| Client | `ddev mysql` or `ddev exec mysql …` — not host `mysql` |

Switch to MariaDB (example):

```yaml
# .ddev/config.yaml
database:
  type: mariadb
  version: "10.11"
```

Then `ddev restart` (use `ddev delete -Oy && ddev start` only if you need a clean empty database after engine change).

---

## How entity schema is applied (`ddev drush updb`)

Ticket and Ticket Comment are **custom content entities**. Drupal maps `baseFieldDefinitions()` to MySQL tables via the **entity schema API**. You do **not** run hand-written `CREATE TABLE` for these entities.

| Situation | What to run |
|-----------|-------------|
| Fresh module enable | `ddev drush en ticket_management -y` — entity types install automatically |
| Module already enabled, entities added later | `ddev drush updb -y` runs `ticket_management_update_9001()` which installs `ticket` / `ticket_comment` if missing and seeds demo users |
| Cache stale after code change | `ddev drush cr` |

Verify tables (names follow entity base tables `ticket` and `ticket_comment`):

```bash
ddev mysql -e "SHOW TABLES LIKE 'ticket%';"
```

Schema field reference (documentation export): [`schema-or-migrations/`](schema-or-migrations/).

---

## Bootstrap from scratch (`ddev drush si`)

To rebuild Drupal from zero inside DDEV:

```bash
ddev drush si standard -y \
  --account-name=admin \
  --account-pass=admin \
  --site-name="AI Practical Assessment"

ddev drush en ticket_management -y
ddev drush cr
```

`hook_install()` seeds demo users when the module is enabled. If you only need the update path on an existing site:

```bash
ddev drush updb -y
```

---

## Persistence across `ddev restart`

- Drupal content (users, tickets, comments) lives in the **database container’s Docker volume**.
- `ddev restart` **keeps** that volume — data persists.
- Data is **lost** only on destructive operations such as `ddev delete` / `ddev delete -Oy`, or a full site reinstall (`drush si`), or dropping the DB manually.

Confirm after restart:

```bash
ddev restart
ddev drush sql:query "SELECT uid, name, mail FROM users_field_data WHERE name LIKE 'agent_%' OR name LIKE 'ticket_%' OR name LIKE 'requester_%';"
```

---

## Demo users (seed)

Created by `ticket_management_seed_demo_users()` in `web/modules/custom/ticket_management/ticket_management.install` (also invoked from `hook_install` and `update_9001`).

Default password for all demo users: `TicketDemo!23` (local/DDEV only — change in real environments).

| Username | Email | Role(s) |
|----------|-------|---------|
| ticket_admin | ticket.admin@example.com | administrator (fallback: ticket_agent) |
| agent_alice | alice.agent@example.com | ticket_agent |
| agent_bob | bob.agent@example.com | ticket_agent |
| requester_carol | carol.requester@example.com | ticket_agent |
| requester_dave | dave.requester@example.com | ticket_agent |

UIDs are assigned by Drupal (auto-increment); they are not hard-coded. Seed is **idempotent** (skips existing usernames).

Logic reference: [`seed-data/`](seed-data/).

---

## Useful commands

```bash
ddev drush en ticket_management -y
ddev drush updb -y
ddev drush cr
ddev mysql
ddev drush php:eval "print_r(ticket_management_seed_demo_users());"
```
