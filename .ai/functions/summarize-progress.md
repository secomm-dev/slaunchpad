# summarize-progress

> Function (VI guidance). Copy vào `.ai/functions/summarize-progress.md`.

## Mục đích
Produce shareable project status từ memory + project-context — standup, TL report, client update (draft), handoff. Orchestrator cho `status` skill.

## Trigger
- Command: `/summarize` (alias)
- Prompt snippet: "Summarize progress cho {audience}: read memory + project-context. Output status (done/in-progress/next/risk)."

## Required inputs
- Audience (internal TL / PM / client) + time window

## Required project files to read
- `project-context/01_PROJECT_OVERVIEW.md`, `06`
- Memory: `CURRENT_STATE.md`, `NEXT_TASK.md`, `DECISIONS.md`, `LESSONS_LEARNED.md`
- `estimation-tracking.csv` (effort status)

## Dependencies
- Skill: `status`
- Agent: ba (client draft), tl (internal)
- Rule: `security-first.md` (no secret/PII/cross-client)

## Execution steps
1. Đọc memory + context + estimation.
2. Compose status theo audience altitude (internal = risk/estimate; client = outcome/next).
3. Flag uncertain; exclude secret/PII/cross-client.
4. Client draft → mark require PM/TL review.

## Expected output
Status report (objective / done / in-progress / next / risk / decision-needed).

## Evidence required
Report (internal: `.ai/evidence/status-{date}.md`; client: draft cho PM/TL review).

## Memory files to update
- `CURRENT_STATE.md` (refresh nếu drift)

## Failure handling
- Client draft gửi unreviewed → Hard Gate violation; phải PM/TL review.
- Status contradict memory → reconcile trước.

## When to improve/update
- Khi audience cần format khác → template variant; record.
