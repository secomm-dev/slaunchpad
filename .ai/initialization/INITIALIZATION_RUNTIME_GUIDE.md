# INITIALIZATION_RUNTIME_GUIDE

> **Conceptual** — NOT runtime software. The runtime sequence the AI follows during Project Initialization, mirroring [`../workflows/WORKFLOW_RUNTIME_GUIDE.md`](../workflows/WORKFLOW_RUNTIME_GUIDE.md) for the delivery engine. Markdown/config-driven only.

## Why this exists

The delivery engine has an explicit auto-advance guarantee ([`../workflows/WORKFLOW_RUNTIME_GUIDE.md`](../workflows/WORKFLOW_RUNTIME_GUIDE.md) step 11 + 11b). Initialization had states, transitions, and validation, but **no** equivalent runtime sequence — so the auto-advance behavior for init states 1–8 was unstated and a literal reader could hard-stop after each assessment sub-step. This guide closes that asymmetry and implements [`../../execution-policy/OUTCOME_ORIENTED_EXECUTION_POLICY.md`](../../execution-policy/OUTCOME_ORIENTED_EXECUTION_POLICY.md) + [`../../execution-policy/AUTO_CONTINUATION_POLICY.md`](../../execution-policy/AUTO_CONTINUATION_POLICY.md) for initialization.

## Runtime sequence

```
1. Request arrives (client wants a new/changed project)
2. → Step 0 preflight (project-selection-safety): resolve target project — uniquely → continue; ≥2 candidates → Human confirm (Hard Gate 6, resume not restart)
3. → Current State: where is initialization? (state ID from INITIALIZATION_STATES)
4. → Allowed Functions: what functions are permitted in this state?
5. → Execution: run the allowed function(s) for this state (e.g., audit-codebase, assess-business-capabilities, map-source-to-target-platform, generate-migration-gap-analysis, assess-migration-complexity)
6. → Validation: check the state's exit outcome (INITIALIZATION_VALIDATION) — all met?
7. → Evidence: collect evidence artifacts for this state
8. → Memory: update CURRENT_INITIALIZATION_STATE + WORKFLOW_HISTORY (append)
8b. → Navigator refresh: update runtime/project-state.yaml (position + open decisions + reading pointer + next step) and re-render PROJECT_NAVIGATOR + DECISION_QUEUE. Re-evaluate TRANSITION_POLICY (auto_continue_when / pause_when). Emit the ACTION_CARD only on a status moment (see ../../execution-policy/STATUS_RESPONSE_POLICY.md) — not on every transition.
9. → Next State: if exit outcome met AND no blocking decision → advance; if a pause_when fires → STOP and surface the blocking DEC-ID.
9b. → Chain across states (Outcome-Oriented Execution): when step 9 advanced because auto_continue_when fired, continue in the same response into the next state's primary function — loop back to steps 4–5. The entire assessment chain (audit → capability → mapping → gap → complexity → assessment → discovery prep) runs as ONE route until a pause_when (a Level ≥2/3 decision) or the Assessment/Draft milestone blocks it.
```

## How AI uses this at runtime

- The setup + migration-assessment chain (states 1–8) **auto-continues by default** — see the "Early-state transitions" table in [`INITIALIZATION_TRANSITIONS.md`](INITIALIZATION_TRANSITIONS.md). Do not stop after each assessment sub-step to ask "continue".
- Initialization **stops** only for: target-project ambiguity (Hard Gate 6), an open Level ≥2/3 Decision (e.g. `platform-selection`, `target-tier`, `business-unknown`), the blueprint approval gate, or a validation failure the AI cannot self-resolve. See [`../../execution-policy/ESCALATION_POLICY.md`](../../execution-policy/ESCALATION_POLICY.md).
- From `Current System Assessment Ready` onward, the per-transition policy table in [`INITIALIZATION_TRANSITIONS.md`](INITIALIZATION_TRANSITIONS.md) governs `pause_when` / `auto_continue_when`.

## State file updates

| File | When updated | Replace vs append |
|---|---|---|
| `CURRENT_INITIALIZATION_STATE.md` | On every state transition | Replace (reflects NOW) |
| `WORKFLOW_HISTORY.md` | On every state transition | Append (never overwrite) |
| `runtime/project-state.yaml` | On every transition + decision mutation | Replace (index of IDs/pointers) |
| `runtime/PROJECT_NAVIGATOR.md` + `DECISION_QUEUE.md` | On every Navigator refresh | Replace (rendered from the yaml) |

> `WORKFLOW_HISTORY.md` is append-only but rotates closed-phase entries to an archive after `Done` (audit trail intact; active file stays bounded). See [`../../execution-policy/CONTEXT_LOADING_POLICY.md`](../../execution-policy/CONTEXT_LOADING_POLICY.md) §C.

## Cross-References

- States: [`INITIALIZATION_STATES.md`](INITIALIZATION_STATES.md)
- Transitions + per-transition policy: [`INITIALIZATION_TRANSITIONS.md`](INITIALIZATION_TRANSITIONS.md)
- Validation: [`INITIALIZATION_VALIDATION.md`](INITIALIZATION_VALIDATION.md)
- Workflow: [`PROJECT_INITIALIZATION_WORKFLOW.md`](PROJECT_INITIALIZATION_WORKFLOW.md)
- Delivery equivalent: [`../workflows/WORKFLOW_RUNTIME_GUIDE.md`](../workflows/WORKFLOW_RUNTIME_GUIDE.md)
- Governing policies: [`../../execution-policy/`](../../execution-policy/)
