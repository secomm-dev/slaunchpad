# compact-context

> Function (VI guidance). Copy vào `.ai/functions/compact-context.md`. Orchestrator cho `compact-context` skill.

## Mục đích
Compact session hiện tại vào memory file — `CURRENT_STATE.md`/`NEXT_TASK.md` (replace stale), `DECISIONS.md`/`LESSONS_LEARNED.md`/`CONTINUOUS_LEARNING.md` (append), risk diff `06`.

## Trigger
- Command: `/compact-context`
- Khi: sau research sweep, sau major debugging, sau implementation milestone, trước review/handoff, trước khi context window at risk.

## Required inputs
- Working session (đã làm gì, decide gì, change gì, còn gì)

## Required project files to read
- Memory file hiện tại (tránh duplicate/contradict)
- `project-context/06` (risk cross-check)

## Dependencies
- Skill: `compact-context`
- Rule: `memory-update.md`, `no-duplicate-knowledge.md`
- Instinct: #10

## Execution steps
1. Đọc memory hiện tại.
2. Summarize session → state/next/decision/lesson.
3. Generate diff: replace `CURRENT_STATE`/`NEXT_TASK`; append `DECISIONS`/`LESSONS`/`CONTINUOUS_LEARNING`; risk diff `06`.
4. No secret/log/scratch.

## Expected output
Memory diff (cho human review + commit) + chat summary (5–10 dòng).

## Evidence required
Memory diff commit (chính nó là evidence).

## Memory files to update
- `CURRENT_STATE.md`, `NEXT_TASK.md` (replace); `DECISIONS.md`, `LESSONS_LEARNED.md`, `CONTINUOUS_LEARNING.md` (append); `project-context/06` (risk).

## Failure handling
- High-risk change chưa escalate → flag trước next step.
- Contradict recorded decision → surface, không silently override.

## When to improve/update
- Khi compact miss field recurrent → update template; record.
