# estimate-feature

> Function (VI guidance). Copy vào `.ai/functions/estimate-feature.md`.

## Mục đích

Estimate effort cho feature/task — range + driver + unknown widen range + escalation nếu vượt mode threshold.

## Trigger
- Command: `/estimate`
- Prompt snippet: "Estimate effort cho {task/feature}. Base trên task + similar pattern. Output range + driver + unknown."

## Required inputs
- Ticket / feature (đã analyze)

## Required project files to read
- `estimation-tracking.csv` (similar task actual)
- `LESSONS_LEARNED.md`, `CONTINUOUS_LEARNING.md` (recurring over/under-estimate)
- `project-context/06` (risk widen range)

## Dependencies
- Skill: `task` (effort field)
- Agent: ba, tl
- Rule: `evidence-required.md` (log actual sau)

## Execution steps
1. Base range từ complexity + affected area + risk.
2. Adjust theo historical actual (`estimation-tracking.csv`).
3. Identify unknown widen range (>50% variance → flag).
4. Escalation nếu vượt mode threshold (Mode C >4h, Mode B >16h).

## Expected output
Range (Xh–Yh) + driver + unknown + escalation flag.

## Evidence required
Estimate log `estimation-tracking.csv` (estimate); actual log sau task.

## Memory files to update
- `estimation-tracking.csv` (estimate; actual sau done)
- `CONTINUOUS_LEARNING.md` (estimate gotcha)

## Failure handling
- Unknown quá lớn → flag TL, request research/discovery trước commit.
- Range quá rộng → suggest split task.

## When to improve/update
- Khi estimate systemic off (e.g., checkout luôn +50%) → Auditor Estimation Audit; adjust baseline.
