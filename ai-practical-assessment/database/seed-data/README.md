# Seed data reference — demo users

**Authoritative implementation:**  
`web/modules/custom/ticket_management/ticket_management.install` → `ticket_management_seed_demo_users()`

Invoked from:

- `ticket_management_install()`
- `ticket_management_update_9001()`

## Demo users

| # | username | email | role |
|---|----------|-------|------|
| 1 | ticket_admin | ticket.admin@example.com | administrator (fallback ticket_agent) |
| 2 | agent_alice | alice.agent@example.com | ticket_agent |
| 3 | agent_bob | bob.agent@example.com | ticket_agent |
| 4 | requester_carol | carol.requester@example.com | ticket_agent |
| 5 | requester_dave | dave.requester@example.com | ticket_agent |

- Password (local/DDEV): `TicketDemo!23`
- UIDs: assigned by Drupal user entity API (not hard-coded)
- Idempotent: existing usernames are skipped

## Apply

```bash
ddev drush en ticket_management -y
# or, if module already enabled:
ddev drush updb -y
# re-run seed only:
ddev drush php:eval "print_r(ticket_management_seed_demo_users());"
```

See also [`demo-users.yml`](demo-users.yml).
