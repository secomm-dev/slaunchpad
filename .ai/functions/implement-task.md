# implement-task

> Function (VI guidance). Copy vào `.ai/functions/implement-task.md`.

## Mục đích
Implement code theo approved plan, trong scope. Orchestrator cho development phase — gọi dev skill, tuân rule, update memory, save evidence.

## Trigger
- (Sau `/plan` + research) Prompt snippet: "Implement task {ticket} theo plan. Trong scope; follow conventions; update memory + evidence."

## Required inputs
- Valid specification (`spec_status = VALID`) plus approved implementation plan (Mode A/B) hoặc approach note (Mode C) that contains `Specification:`

## Required project files to read
- `AGENTS.md` (§7 coding standard, §12 high-risk)
- `project-context/02_BUSINESS_RULES.md`, `04`, `06`
- `RESEARCH_NOTES.md`, plan, `NEXT_TASK.md`

## Dependencies
- Agent: developer
- Rule: `planning-first.md`, `project-conventions-first.md`, `backward-compatibility.md`, `security-first.md`
- Dev skills: `.claude/skills/dev/` (stack-specific)
- Hook: `before-task`, `after-task`, `before-commit`

## Required Engineering Standards (load trước)
`.ai/project-context/engineering-standards/`: ENGINEERING_PRINCIPLES → DEVELOPMENT, CODING, SOLID, SECURITY, PERFORMANCE + `technologies/{tech}`. (Rule `engineering-standards-enforcement.md` — load trước khi code; self-validate sau.)

## Execution steps
1. Run the shared SpecReadinessGuard: resolve MINI/FULL; if invalid, return `IMPLEMENTATION BLOCKED` from `rules/spec-first.md` and do not change code.
2. Hook `before-task` (context loaded, specification + plan tồn tại).
2. Code theo plan, trong scope, follow convention.
3. Không modify high-risk area không escalate (§12).
4. Hook `after-task` (pre-review done, memory updated).
5. Hook `before-commit` (no secret/scope-leak).

## Expected output
Code change (commit) trong scope + AI pre-review result.

## Evidence required
`.ai/evidence/{task}/`: diff summary + test result + pre-review output.

## Memory files to update
- `CURRENT_STATE.md`, `NEXT_TASK.md` (compact sau milestone)
- `CONTINUOUS_LEARNING.md` (convention/gotcha phát hiện)
- `project-context/06` (risk mới)

## Failure handling
- High-risk area touch → STOP, escalate Tier 2.
- Plan không match reality or conflicts with specification → pause, return to specification review; không code quanh.
- Scope creep → reject, giữ scope hẹp.

## When to improve/update
- Khi một step miss recurrent (e.g., quên update memory) → tighten hook. Record `CONTINUOUS_LEARNING.md`.
