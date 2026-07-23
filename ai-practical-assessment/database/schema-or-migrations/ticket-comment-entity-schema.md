# Entity schema export — ticket_management

Documentation export of custom content entity base fields (code-defined).  
Authoritative source: `web/modules/custom/ticket_management/src/Entity/*.php`.

Drupal creates MySQL tables from these definitions via the entity schema API  
(`ddev drush en` / `ddev drush updb`) — no manual SQL migrations required for MVP.

---

## ticket

| Field | Drupal type | Required | Default | Notes |
|-------|-------------|----------|---------|--------|
| id | integer (entity key) | yes | auto | PK |
| uuid | uuid | yes | auto | |
| title | string (max 255) | yes | — | label |
| description | string_long | yes | — | plain long text (assignment “text_long”) |
| priority | list_string | yes | medium | low, medium, high |
| status | list_string | yes | open | open, in_progress, resolved, closed, cancelled |
| assigned_to | entity_reference → user | no | null | |
| created_by | entity_reference → user | yes | — | |
| created | created (timestamp) | yes | auto | |
| changed | changed (timestamp) | yes | auto | |

**base_table:** `ticket`

---

## ticket_comment

| Field | Drupal type | Required | Default | Notes |
|-------|-------------|----------|---------|--------|
| id | integer (entity key) | yes | auto | PK |
| uuid | uuid | yes | auto | |
| ticket_id | entity_reference → ticket | yes | — | parent ticket |
| message | string_long | yes | — | plain long text (assignment “text_long”) |
| created_by | entity_reference → user | yes | — | |
| created | created (timestamp) | yes | auto | |

**base_table:** `ticket_comment`

---

## YAML snapshot (logical)

```yaml
entity_types:
  ticket:
    base_table: ticket
    keys: { id: id, uuid: uuid, label: title }
    fields:
      title: { type: string, required: true, max_length: 255 }
      description: { type: string_long, required: true }
      priority:
        type: list_string
        required: true
        allowed_values: [low, medium, high]
        default: medium
      status:
        type: list_string
        required: true
        allowed_values: [open, in_progress, resolved, closed, cancelled]
        default: open
      assigned_to: { type: entity_reference, target: user, required: false }
      created_by: { type: entity_reference, target: user, required: true }
      created: { type: created }
      changed: { type: changed }
  ticket_comment:
    base_table: ticket_comment
    keys: { id: id, uuid: uuid }
    fields:
      ticket_id: { type: entity_reference, target: ticket, required: true }
      message: { type: string_long, required: true }
      created_by: { type: entity_reference, target: user, required: true }
      created: { type: created }
```
