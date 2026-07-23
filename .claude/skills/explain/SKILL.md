# Nav — Explain Step

## Purpose

The invokable form of the **Explain Step** navigator intent. Explains why the current step exists and what its expected outcome is. Thin adapter over [`shared-core/navigator/NAVIGATOR_ENGINE.md`](../../shared-core/navigator/NAVIGATOR_ENGINE.md) (Explain Step).

## When to Use

- The user says: "bước này để làm gì" / "tại sao ở đây" / "kết quả mong đợi" / "why this step" — or invokes `/explain`.

## Prerequisites

- `.ai/runtime/project-state.yaml` (current `position` IDs)
- The relevant state-detail file (`CURRENT_*_STATE.md`) for the current state ID
- `shared-core/navigator/NAVIGATOR_ENGINE.md` (canonical behavior)

## Input

None (explains the current step).

## Steps

1. Read `project-state.yaml` → current `position` (init/workflow state ID + role + milestone).
2. Read the matching `CURRENT_*_STATE.md` for the entry/exit criteria + purpose of that state.
3. Render: why this state exists (its purpose), its expected outcome, and what satisfies it (exit criteria).
4. No state mutation.

## Output Format

> **Output language:** project `output_language` (default English); technical terms stay English.

```markdown
### Why this step — {milestone}
- **Purpose:** {why this state exists}
- **Expected outcome:** {what 'done' looks like}
- **Satisfied when:** {exit criteria}
```

## Quality Checklist

- [ ] Purpose grounded in the state-detail file, not invented
- [ ] Expected outcome is concrete and verifiable
- [ ] No state mutation

## Escalation

- If the state-detail file is missing → report it (validator-relevant defect).

## Example

**Input:** "bước này để làm gì" → `/explain`
**Output:** the purpose + expected outcome above for the current step.
