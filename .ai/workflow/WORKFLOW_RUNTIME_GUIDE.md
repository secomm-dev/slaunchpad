# WORKFLOW_RUNTIME_GUIDE

> **Conceptual** — NOT runtime software. Describes the sequence AI follows when working on a task, using the Workflow Engine. Markdown/config-driven only.

## Runtime sequence

```
1. Task arrives (ticket/spec/bug report)
2. → Workflow Selection: which workflow applies? (role + project-type + mode)
3. → Current State: where is this task? (Task Created → first entry)
4. → Allowed Functions: what functions are permitted in this state? (see STATE_MODEL)
5. → Research: is research required? (trigger check; if mandatory → Researching state)
6. → Engineering Knowledge: load standards for this state (Planning → Architecture/Coding; Implementing → Development/Tech/Security/Perf)
7. → Execution: run the allowed functions (e.g., Implementing → implement-task)
8. → Validation: check exit criteria for this state (all met?)
9. → Evidence: collect evidence artifacts for this state
10. → Memory: update CURRENT_WORKFLOW_STATE + WORKFLOW_HISTORY (append) + relevant memory
10b. → Navigator refresh: update `runtime/project-state.yaml` (position + open decisions + reading pointer + next step) and re-render `PROJECT_NAVIGATOR.md` + `DECISION_QUEUE.md`. Re-evaluate `TRANSITION_POLICY` (`auto_continue_when` / `pause_when`). Emit the inline ACTION_CARD **only** when a `pause_when` is true, a Level ≥2 decision opens/closes, a major milestone crosses, or the outcome completes — **not** on every transition. Auto-continued steps stay silent (logged to `WORKFLOW_HISTORY.md` only). See `../../execution-policy/STATUS_RESPONSE_POLICY.md`.
11. → Next State: if exit criteria met AND no blocking decision → advance to next state; if a `pause_when` fires → STOP and surface the blocking DEC-ID; if exit not met → stay or go back.
11b. → **Chain across states (Outcome-Oriented Execution):** when step 11 advanced because `auto_continue_when` fired (not a `pause_when`, no open Level ≥2 decision), **continue in the same response** into the next state's primary function — loop back to steps 5–7 for that state. Repeat until one of: a `pause_when` fires, an open Level ≥2 decision blocks, the requested outcome is complete, or the route has no further licensed function. Do **not** present an intermediate artifact (research note, assessment, draft, plan, test result) and stop when nothing blocks — that is a policy violation. See `../../execution-policy/OUTCOME_ORIENTED_EXECUTION_POLICY.md` + `../../execution-policy/AUTO_CONTINUATION_POLICY.md`.
```

## How AI uses this at runtime

- **Same-response execution (minimum sufficient execution):** steps 5–10 of one state run in a **single response**; step 11b extends that across states (chaining). Do not split a state's research → execute → validate → evidence → memory into separate model turns unless an escalation condition fires. Batch the load steps.
- At the **start** of each AI session: read `CURRENT_WORKFLOW_STATE.md` → know where work is.
- Before **any function execution**: check the function's "Applicable Workflow States" → validate it's allowed in the current state.
- Before **state transition**: check exit criteria → validate all met → collect evidence → update memory → advance.
- If **exit criteria not met**: stay in current state; report what's missing.

## State file updates

| File | When updated | Replace vs append |
|---|---|---|
| `CURRENT_WORKFLOW_STATE.md` | On every state transition | Replace (reflects NOW) |
| `WORKFLOW_HISTORY.md` | On every state transition | Append (never overwrite — history) |
| `CURRENT_STATE.md` (memory) | On milestones (compact) | Replace (reflects NOW) |
| `NEXT_TASK.md` (memory) | After Planning → Implementing | Replace |
| `runtime/project-state.yaml` | On every transition + every decision mutation | Replace (index of IDs/pointers — detail stays in the files above) |
| `runtime/PROJECT_NAVIGATOR.md` + `DECISION_QUEUE.md` | On every Navigator refresh | Replace (rendered from the yaml — do not hand-edit) |

> `WORKFLOW_HISTORY.md` is **append-only** — never delete previous transitions. It's the audit trail of how work progressed.
