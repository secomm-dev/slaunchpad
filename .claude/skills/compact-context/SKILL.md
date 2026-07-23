# Compact Context

## Purpose

Condense the current AI working session into the project memory files so the working state survives a context reset or handoff. Produces a clean, current summary — and durable memory-file diffs — instead of letting state live only in chat history.

## When to Use

- After a research sweep (read several files / explored an unfamiliar area)
- After major debugging (root cause found, fix path chosen)
- After an implementation milestone (a task or sub-task done and verified)
- Before the context window is at risk of dropping earlier findings
- At handoff to another person or a fresh AI session
- Before requesting TL review (so the reviewer can resume quickly)

## Prerequisites

- `AGENTS.md` — workflow mode, high-risk areas
- Relevant `project-context/` files (especially `06_KNOWN_CONSTRAINTS_AND_RISKS.md`)
- The current memory files under `.ai/project-context/memory/` (`CURRENT_STATE.md`, `NEXT_TASK.md`, `DECISIONS.md`, `LESSONS_LEARNED.md`) — see [project-memory-standard.md](../../core/project-memory-standard.md)

## Input

The working session so far: what was investigated, decided, changed, verified, and what remains. Paste the ticket/spec reference and any in-progress notes if useful.

## Steps

1. Read the current memory files to avoid duplicating or contradicting them.
2. Summarize the session into four buckets:
   - **State**: what is done, in-progress, verified, blocked (→ `CURRENT_STATE.md`)
   - **Next**: the immediate next task + its AC + owner (→ `NEXT_TASK.md`)
   - **Decisions**: any architecture/business/technical decision made, with rationale (append → `DECISIONS.md`)
   - **Lessons**: what worked / failed / to repeat or avoid (append → `LESSONS_LEARNED.md` only if genuinely new)
3. Generate **diffs** for each affected memory file — never overwrite history files (`DECISIONS.md`, `LESSONS_LEARNED.md` are append-only); `CURRENT_STATE.md` / `NEXT_TASK.md` reflect *now* (replace stale content).
4. Cross-check risks against `project-context/06_KNOWN_CONSTRAINTS_AND_RISKS.md`; if a new risk surfaced, propose a diff for that file too.
5. Produce a short chat summary (5–10 lines) for the human, plus the memory-file diffs for review.
6. Do **not** store secrets, full logs, PII, or transient scratch (see project-memory-standard → "What not to store").

## Output Format

> **Output language:** Produce all prose in the project's `output_language` (see `.ai/AGENTS.md`; default English). Code, identifiers, file paths, and technical terms (Magento, plugin, GraphQL, checkout, etc.) ALWAYS stay English.

```markdown
## Context Compaction: {ticket_id / feature} — {date}

### Session Summary
{3–5 lines: what was investigated/changed and the current state}

### Memory File Diffs (for human review + commit)
#### CURRENT_STATE.md
- In progress: {task}
- Done/verified: {items}
- Blocked: {blocker + owner}

#### NEXT_TASK.md
- Next: {task} — AC: {criteria} — owner: {name}

#### DECISIONS.md (append)
- {decision} — context: {why} — consequences: {impact} — status: accepted

#### LESSONS_LEARNED.md (append, only if new)
- {lesson} — source: {incident/task}

### Risks (→ project-context/06, if changed)
- {new/changed risk}

### Chat Summary
{5–10 line handoff summary}
```

## Quality Checklist

- [ ] `CURRENT_STATE.md` reflects *now* (no stale "in progress")
- [ ] `NEXT_TASK.md` has a concrete next step with AC and owner
- [ ] Every decision includes rationale (not just the outcome)
- [ ] No duplication of standing `project-context/` content — linked, not copied
- [ ] No secrets, PII, full logs, or scratch stored
- [ ] History files (`DECISIONS.md`, `LESSONS_LEARNED.md`) appended, not overwritten

## Escalation Rules

- If compaction reveals an unresolved high-risk change (payment/checkout/order/DB/security) → flag for Tier 2 before next step
- If the session contradictied a recorded decision → surface the conflict; do not silently override `DECISIONS.md`

## Example

**Input**: Session implementing B2B tiered pricing; debugging found a cache key missing for customer groups; fix applied and verified on staging; one more test case (coupon + tier) pending.

**Output**:
```markdown
## Context Compaction: BUG-1234 — 2026-07-07

### Session Summary
Implemented B2B tiered pricing. Debugging located a missing customer-group cache key causing stale prices. Fix applied to Acme_B2BPricing/Model/Price.php:45 and verified on staging for Gold/Silver/Bronze.

### Memory File Diffs
#### CURRENT_STATE.md
- In progress: BUG-1234 B2B tiered pricing
- Done/verified: tiered price calc + cache key fix (staging Gold/Silver/Bronze)
- Blocked: none

#### NEXT_TASK.md
- Next: add regression test "coupon + tiered pricing" (BR-003) — AC: total = tier price − coupon, no double discount — owner: dev

#### DECISIONS.md (append)
- Tier values stored configurable, not hardcoded — context: hardcoded values failed pre-review — consequences: requires config migration — status: accepted

### Risks (→ project-context/06)
- Category page price display still stale under full-page cache — needs cache key for customer group on list blocks

### Chat Summary
B2B pricing fixed + verified on staging (3 tiers). Cache key bug root-caused and patched. One regression test pending (coupon + tier). Category page cache risk logged for follow-up.
```
