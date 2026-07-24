# AI prompts — Planning

Phase: requirement analysis, task breakdown, acceptance criteria.  
Tool: Cursor Agent · Stack constraints: Drupal 11 / DDEV / no Node.js.

---

## Prompt 1 — Requirement analysis (Step 3)

```text
Read the assignment: Support Ticket Management System. Entities: User (seeded
only), Ticket (id, title, description, priority, status, assignedTo, createdBy,
createdAt, updatedAt), Comment (id, ticketId, message, createdBy, createdAt).
Required features: create, list, detail, update, status state machine, add
comments, keyword search + status filter, persistence, backend validation,
frontend error states. State machine: Open->In Progress, In Progress->Resolved,
Resolved->Closed, Open->Cancelled, In Progress->Cancelled.

Produce requirements-analysis.md with: Selected Project Option, My Understanding,
Functional Requirements, Non-Functional Requirements, Assumptions, Clarifications,
Edge Cases (invalid transition, empty search, etc.).

Constraint: Ticket and Comment must be custom content entities — not Drupal core
entity types (Node, core Comment, etc.).
```

**Output:** `ai-practical-assessment/requirements-analysis.md`

---

## Prompt 2 — Development tasks & acceptance criteria (Step 6)

```text
Turn design-notes.md and spec.md into implementation-plan.md: Overview, Task
Breakdown (scaffold module, define Ticket entity, define Comment entity, seed
users via update hook, build TicketStateMachine service, add presave
validation, build REST/JSON:API endpoints, build search/filter, build Twig
templates, wire JS fetch layer, write Kernel/Functional tests, write README),
Milestones, AI Usage Plan, Risks, Mitigation. Also produce acceptance-criteria.md
as a checklist mirroring the assignment's Core Acceptance Criteria.
```

**Outputs:**
- `ai-practical-assessment/implementation-plan.md`
- `ai-practical-assessment/acceptance-criteria.md`

---

## Reusable planning template

```text
Given [assignment / product brief], produce:
1. requirements-analysis.md (understanding, FRs, NFRs, assumptions, edge cases)
2. implementation-plan.md (ordered tasks, milestones, risks, AI usage plan)
3. acceptance-criteria.md (checkbox list tied to Core Acceptance Criteria)

Hard constraints: [custom entities / DDEV-only / no Node / state machine as service].
Do not start coding until these three docs exist.
```
