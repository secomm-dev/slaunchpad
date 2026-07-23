# Resume Work

## Purpose

Reconstruct the working state of a task or feature from the project memory files at the start of a new session — so work continues accurately without re-discovering what a previous session already found. The counterpart of [compact-context](../compact-context/SKILL.md).

## When to Use

- At the start of any session that continues existing work
- After a context reset / compaction
- When picking up a task another person or session started
- After a gap longer than a short break

## Prerequisites

- `AGENTS.md` — workflow mode, high-risk areas, escalation rules
- Relevant `project-context/` files
- The memory files under `.ai/project-context/memory/` (`CURRENT_STATE.md`, `NEXT_TASK.md`, `DECISIONS.md`, `LESSONS_LEARNED.md`) and `project-context/06_KNOWN_CONSTRAINTS_AND_RISKS.md`

## Input

The task/ticket being resumed (ID or description). If the memory files are empty or stale, say so rather than guessing.

## Steps

1. Read `AGENTS.md §7, §9–§12` (workflow mode, rules, gates, escalation, high-risk) + the memory files in step 2 — skip standing context already loaded this run.
2. Read the memory files: `CURRENT_STATE.md`, `NEXT_TASK.md`, then `DECISIONS.md` and `LESSONS_LEARNED.md`.
3. Cross-check `NEXT_TASK.md` against the ticket/spec and `project-context/06` — confirm the next step is still valid (requirements/risks may have changed).
4. Reconstruct and state: where the work is, what's done/verified, what's blocked, and the immediate next step.
5. **Proceed** — surface the resumed state as awareness and continue (Outcome-Oriented Execution / auto-continuation). Do **not** ask "is this where we are — correct?" as a blocking gate. **Stop only** if step 3 found a real contradiction (NEXT_TASK vs ticket / `06`) or the next step touches a high-risk area (§12) — those are genuine escalation points. The planning-first gate (§7.1: no code without a plan) still applies.
6. If memory is missing or contradicts the codebase, flag it — do not silently invent state. Propose refreshing the memory files via [compact-context](../compact-context/SKILL.md).

## Output Format

> **Output language:** Produce all prose in the project's `output_language` (see `.ai/AGENTS.md`; default English). Code, identifiers, file paths, and technical terms (Magento, plugin, GraphQL, checkout, etc.) ALWAYS stay English.

```markdown
## Resume: {ticket_id / feature}

### Where we are
- In progress: {task}
- Done / verified: {items}
- Blocked: {blocker + owner}

### Decisions already made (do not re-litigate)
- {decision} — {one-line rationale}

### Relevant risks
- {risk from project-context/06}

### Immediate next step
- {next task} — AC: {criteria} — owner: {name}
- Plan/approach: {3–5 steps, or "needs planning" flag}

### Confirmation needed
- {question for the human before proceeding}
```

## Quality Checklist

- [ ] Memory files actually read (not assumed)
- [ ] Next step cross-checked against ticket/spec + risks — still valid?
- [ ] No re-litigating recorded decisions
- [ ] Gaps/contradictions flagged, not papered over
- [ ] Proceed unless step 3 found a real contradiction or the next step is high-risk (§12) — no routine "confirm where we are"

## Escalation Rules

- If the next step touches a high-risk area (payment/checkout/order/DB/security) → confirm Tier 2 awareness before proceeding
- If memory contradicts the codebase and the gap is high-risk → escalate to TL to reconcile

## Example

**Input**: Resume BUG-1234 (B2B tiered pricing).

**Output**:
```markdown
## Resume: BUG-1234

### Where we are
- In progress: B2B tiered pricing
- Done / verified: tiered calc + customer-group cache key fix (staging Gold/Silver/Bronze)
- Blocked: none

### Decisions already made
- Tier values are configurable, not hardcoded — failed pre-review when hardcoded

### Relevant risks
- Category page price display may be stale under full-page cache (project-context/06)

### Immediate next step
- Add regression test "coupon + tiered pricing" (BR-003) — AC: total = tier price − coupon, no double discount — owner: dev
- Plan: (1) add test case, (2) run on staging, (3) update project-context/02 if rule clarifies

### Confirmation needed
- Confirm we're testing against the Gold tier only, or all three tiers + retail?
```
