# review-code

> Function (VI guidance). Copy vào `.ai/functions/review-code.md`.

## Mục đích
AI pre-review code change trước TL review — scope, security, business rule, standard, regression. Orchestrator cho `review-code` (+`security-review`) skill.

## Trigger
- Command: `/review`
- Prompt snippet: "Pre-review change {diff} cho {ticket}. Check plan match, scope, security, business rule (02), §12 high-risk. Output Critical/Warning/Note + verdict."

## Required inputs
- Code change (diff/files) + implementation plan

## Required project files to read
- `AGENTS.md` (§7.2 standard, §12 high-risk)
- `project-context/02_BUSINESS_RULES.md`, `CODING_RULES.md`

## Dependencies
- Skill: `review-code` (+ `security-review` nếu sensitive)
- Agent: tl, security-reviewer (nếu sensitive)
- Rule: `security-first.md`, `evidence-required.md`
- Hook: `before-pr`

## Required Engineering Standards (load trước)
`.ai/project-context/engineering-standards/`: ENGINEERING_PRINCIPLES → REVIEW, CODING, DEVELOPMENT, SOLID, ARCHITECTURE + `technologies/{tech}`. (Compare code vs standards; produce violations + fix recommendations.)

## Execution steps
1. Đọc plan + AGENTS standard.
2. Chạy `review-code` skill: plan match, scope, hardcoded value, error handling, business rule, security, performance, regression.
3. Nếu security-sensitive → chạy `security-review` skill (8 topic).
4. Produce finding (Critical/Warning/Note) + verdict.

## Expected output
Pre-review report: finding (file+line), scope check, regression risk, verdict (PASS / PASS WITH WARNINGS / NEEDS FIX).

## Evidence required
Pre-review report lưu `.ai/evidence/{task}/pre-review.md` + attach vào PR.

## Memory files to update
- `CONTINUOUS_LEARNING.md` (recurring finding pattern)

## Failure handling
- Critical finding → fix trước TL review; không tạo PR với NEEDS FIX.
- High-risk change chưa escalate → flag Tier 2.

## When to improve/update
- Khi pre-review miss loại issue recurrent → update `review-code` skill checklist + record.
