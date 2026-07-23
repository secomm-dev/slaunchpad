# Nav — Approve Decision (MUTATING)

## Purpose

The invokable form of the **Approve Decision** navigator intent. **Mutates** project state: marks a decision `decided`, appends an ADR to `memory/DECISIONS.md`, and re-evaluates the transition. Thin adapter over [`shared-core/navigator/NAVIGATOR_ENGINE.md`](../../shared-core/navigator/NAVIGATOR_ENGINE.md) (Approve Decision) + [`APPROVAL_TAXONOMY.md`](../../shared-core/navigator/APPROVAL_TAXONOMY.md).

> ⚠️ **Mutating + hard-to-reverse.** This skill enforces a DEC-ID target and **confirm-before-mutate**. It never approves a whole document — only a single decision (DEC-ID).

## When to Use

- The user says: "tôi đồng ý" / "chấp nhận" / "đồng ý dùng" / "approve DEC-001 opt-b" — or invokes `/approve [DEC-ID] [option]`.

## Prerequisites

- `.ai/runtime/project-state.yaml` (`decisions[]`, `next_step`, `auto_continue_when`, `pause_when`)
- `.ai/memory/DECISIONS.md` (ADR store — append-only)
- `shared-core/navigator/TRANSITION_POLICY.md` (re-evaluation rules)

## Input

- **Required:** DEC-ID **or** top open decision (if DEC-ID absent, target the top open decision and **confirm**).
- **Optional:** option id (defaults to `recommended_option`).

## Steps

1. Resolve target DEC-ID. If absent, select the **top open decision** and state which one you will approve.
2. **Confirm with the human before mutating** (decisions are hard to reverse / outward-facing). Show: DEC-ID, title, the option being approved, and the consequence.
3. On confirmation:
   a. Set the decision `status: decided`, record `decided_option`, stamp `adr_ref`.
   b. Append an ADR to `memory/DECISIONS.md` (Context / Decision / Rationale / Consequences / Status: Accepted). Format: `templates/decisions-template.md`.
   c. Re-evaluate `TRANSITION_POLICY`: does `auto_continue_when` now hold? If yes, advance and re-render; if `pause_when` holds, stop and surface the next blocker.
4. Decided decisions **leave** the open queue (per the index-only principle).

## Output Format

> **Output language:** project `output_language` (default English); technical terms stay English.

```markdown
### Approved — {DEC-ID}
- Decision: {title}
- Option: {decided_option}
- Recorded as: memory/DECISIONS.md#ADR-NNNN
- Next: {advanced to {state} | still blocked by {blocker}}
```

## Quality Checklist

- [ ] DEC-ID resolved (no blanket approval)
- [ ] Human confirmed before the mutation
- [ ] ADR appended with rationale (not just the outcome)
- [ ] Transition re-evaluated (advance or surface blocker)
- [ ] Decided decision removed from the open queue

## Escalation

- Level 3 (client-business) decisions → require explicit client approval; do not auto-approve even if the human says "yes" in chat unless they are the decision owner.
- If approval contradicts a recorded decision → surface the conflict; do not silently override `DECISIONS.md`.

## Example

**Input:** "approve DEC-001 opt-b" → `/approve DEC-001 opt-b`
(confirm) → mutate → **Output:** the approved + next-step card above.
