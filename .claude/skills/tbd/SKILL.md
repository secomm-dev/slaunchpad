# Nav — Mark TBD (MUTATING)

## Purpose

The invokable form of the **Mark TBD** navigator intent. **Mutates** project state: defers an open decision, records the pause reason, and re-evaluates whether to pause. Thin adapter over [`shared-core/navigator/NAVIGATOR_ENGINE.md`](../../shared-core/navigator/NAVIGATOR_ENGINE.md) (Mark TBD).

> ⚠️ **Mutating.** Enforces a DEC-ID target and **confirm-before-mutate**.

## When to Use

- The user says: "chưa quyết" / "để hỏi client" / "để sau" / "chưa rõ" / "tbd DEC-002" — or invokes `/tbd [DEC-ID]`.

## Prerequisites

- `.ai/runtime/project-state.yaml` (`decisions[]`, `pause_when`, `blocks_advance`)
- `shared-core/navigator/TRANSITION_POLICY.md`

## Input

- **Required:** DEC-ID **or** top open decision (if absent, target the top open decision and **confirm**).
- **Optional:** reason (who needs to answer, when).

## Steps

1. Resolve target DEC-ID. If absent, select the **top open decision** and state which one.
2. **Confirm with the human** which decision is being deferred.
3. On confirmation:
   a. Set the decision `status: info-needed` (or `open` with a note), record the reason, and set `blocks_advance` per policy.
   b. Re-evaluate `pause_when`: if the deferred decision blocks advance, the Navigator pauses and surfaces the next available non-blocked action; otherwise it continues.
4. The decision **stays** in the open queue (unlike approve).

## Output Format

> **Output language:** project `output_language` (default English); technical terms stay English.

```markdown
### Deferred — {DEC-ID}
- Decision: {title}
- Reason: {reason / who owes the answer}
- Effect: {paused — blocked advance | continuing — non-blocking}
- Next: {the next available action}
```

## Quality Checklist

- [ ] DEC-ID resolved (no blanket defer)
- [ ] Human confirmed before the mutation
- [ ] Pause reason recorded
- [ ] Transition re-evaluated (pause or continue stated honestly)

## Escalation

- If deferring a Level ≥2 decision that blocks the critical path → flag the schedule impact.

## Example

**Input:** "để hỏi client" → `/tbd DEC-002`
(confirm) → mutate → **Output:** the deferred + next-action card above.
