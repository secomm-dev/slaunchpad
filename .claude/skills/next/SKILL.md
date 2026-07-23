# Nav — Next Step

## Purpose

The invokable form of the **Next Step** navigator intent. Reads the project navigation index and returns the **one** next action the human/AI should take, plus the file/section to read now. This is the live counterpart to `continue` (which reconstructs state at session start).

This skill is a **thin adapter** — it renders the canonical behavior defined in [`shared-core/navigator/NAVIGATOR_ENGINE.md`](../../shared-core/navigator/NAVIGATOR_ENGINE.md) (Next Step). It adds no new logic.

## When to Use

- The user says: "tiếp theo làm gì" / "bước tiếp theo" / "tiếp tục" / "what next" — or invokes `/next`.
- The user is uncertain what to do next.
- After an auto-continued advance, to re-orient.

> Per `execution-policy/STATUS_RESPONSE_POLICY.md`: `/next` only **re-renders the current position** — it does **not** run additional workflow steps. Do not use it to force progress.

## Prerequisites

- `.ai/runtime/project-state.yaml` — the navigation index (must exist; if missing → tell the user the Navigator has not been initialized and point to `.ai/WELCOME.md`).
- `shared-core/navigator/NAVIGATOR_ENGINE.md` (canonical behavior).
- `shared-core/navigator/VI_PHRASE_MAP.md` (phrase → intent).

## Input

Optional DEC-ID or context. No input = render the current position.

## Steps

1. Read `.ai/runtime/project-state.yaml`.
2. Read `position`, `reading_pointer`, `next_step`, and `decisions[]` (open only).
3. Render a single ACTION_CARD: current phase + the one next action (with owner + outcome) + the reading pointer (file + section + why) + any blocking decision.
4. Do **not** mutate state. Do **not** advance the workflow.

## Output Format

> **Output language:** project `output_language` (default English); technical terms stay English.

```markdown
### Next step — {milestone}
- **Do next:** {next_step.action} — owner: {owner}
- **Outcome that completes it:** {next_step.outcome}
- **Read now:** {reading_pointer.file} § {reading_pointer.section} — {reading_pointer.why}
- **Blocking:** {DEC-ID title (level)} or "nothing blocking"
```

## Quality Checklist

- [ ] Reads `project-state.yaml` (does not guess state from memory)
- [ ] Exactly one next action (not a list)
- [ ] Reading pointer includes section + why
- [ ] No state mutation

## Escalation

- If `project-state.yaml` is missing → instruct user to initialize (`.ai/WELCOME.md`), do not fabricate a position.
- If `next_step.blocks_advance` is true and a Level ≥2 decision is open → also point to `/decisions`.

## Example

**Input:** "tiếp theo làm gì" → `/next`
**Output:** the ACTION_CARD above for the current position.
