# resume-work

> Function (VI guidance). Copy vào `.ai/functions/resume-work.md`. Orchestrator cho `continue` skill.

## Mục đích
Reconstruct working state từ memory file ở session start — confirm nơi đang ở, next là gì, trước khi code.

## Trigger
- Command: `/resume-work`
- Khi: session start continue existing work / sau context reset / handoff.

## Required inputs
- Task/ticket đang resume

## Required project files to read
- `AGENTS.md §7, §9–§12` (rules, mode, gates, escalation, high-risk) + relevant `project-context/`
- Memory: `CURRENT_STATE.md`, `NEXT_TASK.md`, `DECISIONS.md`, `LESSONS_LEARNED.md`, `RESEARCH_NOTES.md`

## Dependencies
- Skill: `continue`
- Rule: `planning-first.md`, `memory-update.md`

## Execution steps
1. Đọc `AGENTS.md §7, §9–§12` + relevant project-context (skip nếu đã load trong run này).
2. Đọc memory file (CURRENT_STATE → NEXT_TASK → DECISIONS → LESSONS → RESEARCH_NOTES).
3. Cross-check `NEXT_TASK` vs ticket/spec/06 (còn valid không).
4. Reconstruct + confirm với human trước khi code.

## Expected output
Resume summary: where we are / done-verified / blocked / decision made / risk / immediate next step / confirmation needed.

## Evidence required
Resume confirm (human acknowledge trước code).

## Memory files to update
- `RESEARCH_NOTES.md` (nếu phát hiện gap), `CURRENT_STATE.md` (nếu reconcile)

## Failure handling
- Memory missing/contradict codebase → flag, không invent state; refresh memory.
- Next step touch high-risk → confirm Tier 2 awareness.

## When to improve/update
- Khi resume miss recurring (e.g., quên đọc DECISIONS) → tighten step; record.
