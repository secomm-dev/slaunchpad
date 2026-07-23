# record-lesson

> Function (VI guidance). Copy vào `.ai/functions/record-lesson.md`.

## Mục đích
Capture một lesson — append `LESSONS_LEARNED.md` (retro/incident) hoặc `CONTINUOUS_LEARNING.md` (in-flight). No blame; summarize; link evidence.

## Trigger
- Command: `/record_lesson`
- Khi: học được gì trong khi làm việc / retro / post-incident.

## Required inputs
- Lesson (what), source (task/incident/retro), type (repeat/avoid), action/prevention

## Required project files to read
- `LESSONS_LEARNED.md`, `CONTINUOUS_LEARNING.md` (tránh duplicate)
- `templates/lessons-learned-template.md`, `shared-core/memory/continuous-learning-template.md`

## Dependencies
- Agent: tl (curate), developer
- Rule: `memory-update.md`, `no-duplicate-knowledge.md`
- Instinct: #10

## Execution steps
1. Check existing lesson (tránh duplicate).
2. Phân loại: retro/incident → `LESSONS_LEARNED.md`; in-flight (convention/gotcha/client-rule) → `CONTINUOUS_LEARNING.md`.
3. Draft entry per template (no secret/PII/blame; summarize; link evidence).
4. Append.

## Expected output
Lesson entry append (`LESSONS_LEARNED.md` hoặc `CONTINUOUS_LEARNING.md`).

## Evidence required
Lesson entry + link (ticket/PR/RESEARCH_NOTES) nếu có.

## Memory files to update
- `LESSONS_LEARNED.md` hoặc `CONTINUOUS_LEARNING.md` (append — primary)

## Failure handling
- Lesson obsolete → mark deprecated (Status: deprecated), không xóa.
- Lesson chứa secret/PII/blame → sanitize.

## When to improve/update
- Khi lesson category miss → update template; record.
