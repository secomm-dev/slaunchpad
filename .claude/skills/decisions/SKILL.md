# Nav — Show Decisions

## Purpose

The invokable form of the **Show Decisions** navigator intent. Renders the **open-decision queue** (read-only). Thin adapter over [`shared-core/navigator/NAVIGATOR_ENGINE.md`](../../shared-core/navigator/NAVIGATOR_ENGINE.md) (Show Decisions); renders `.ai/runtime/DECISION_QUEUE.md`.

> Read-only. This is **not** the same as `record-decision` (which appends an ADR — a write). To approve/defer a decision, use `/approve` or `/tbd`.

## When to Use

- The user says: "xem các quyết định" / "cho xem quyết định" / "phải approve gì" / "show decisions" — or invokes `/decisions`.

## Prerequisites

- `.ai/runtime/project-state.yaml` (`decisions[]`)
- `.ai/runtime/DECISION_QUEUE.md` (render target)

## Input

None.

## Steps

1. Read `.ai/runtime/project-state.yaml` → `decisions[]` where `status` is `open` or `info-needed`.
2. For each, render: DEC-ID, title, approval_level, recommended_option + recommendation, blocks_advance, evidence_ref.
3. Group by approval level (Level 2/3 first — these need a human).
4. No state mutation.

## Output Format

> **Output language:** project `output_language` (default English); technical terms stay English.

```markdown
### Decisions awaiting you
| DEC | Decision | Level | Recommended | Blocks? |
|---|---|---|---|---|
| DEC-001 | {title} | 2 (TL-SA) | opt-b | yes |
- To approve: `/approve DEC-001 opt-b` · to defer: `/tbd DEC-001`
```

## Quality Checklist

- [ ] Open decisions only (decided ADRs excluded)
- [ ] Level ≥2 surfaced first
- [ ] Evidence pointer shown where present
- [ ] No state mutation

## Escalation

- If no open decisions → say so explicitly and point to `/next`.

## Example

**Input:** "phải approve gì" → `/decisions`
**Output:** the open-decision queue above.
