# Nav — What Do I Do (Help)

## Purpose

The invokable form of the **What Do I Do** navigator intent. Synthesizes an orienting answer to "where am I / what am I doing / am I stuck" and offers up to 3 available actions. Thin adapter over [`shared-core/navigator/NAVIGATOR_ENGINE.md`](../../shared-core/navigator/NAVIGATOR_ENGINE.md) (What Do I Do).

## When to Use

- The user says: "tôi đang ở đâu" / "đang làm gì vậy" / "bị kẹt" / "help" — or invokes `/help-nav`.

## Prerequisites

- `.ai/runtime/project-state.yaml`
- `shared-core/navigator/NAVIGATOR_ENGINE.md` (canonical behavior)

## Input

None.

## Steps

1. Read `.ai/runtime/project-state.yaml`: `phase`, `position`, `reading_pointer`, `next_step`, `decisions[]` (open).
2. Render an orienting summary: which phase + state, what is in progress, what blocks progress.
3. Offer ≤3 concrete available actions (e.g. `/next`, `/decisions`, `/explain`, or the next delivery action).
4. No state mutation.

## Output Format

> **Output language:** project `output_language` (default English); technical terms stay English.

```markdown
### Where you are
- Phase: {phase} — {milestone}
- In progress: {task_ref content pointer}
- Blocking: {open Level≥2 decision or "nothing"}

### 3 things you can do now
1. {action 1}  ({command or NL})
2. {action 2}
3. {action 3}
```

## Quality Checklist

- [ ] Orienting summary grounded in `project-state.yaml`
- [ ] ≤3 actions, each actionable
- [ ] No state mutation

## Escalation

- If `project-state.yaml` missing → initialize via `.ai/WELCOME.md`.

## Example

**Input:** "tôi đang ở đâu" → `/help-nav`
**Output:** the orienting summary + 3 actions above.
