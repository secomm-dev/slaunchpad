# prepare-pr

> Function (VI guidance). Copy vào `.ai/functions/prepare-pr.md`. Prepare PR — validate Review standards, attach pre-review + evidence.

## Mục đích
Chuẩn bị PR hoàn chỉnh: validate Review standard, attach AI pre-review summary + evidence + regression risk, scope clean.

## Trigger
- Command: `/pr` (alias)
- Prompt snippet: "Prepare PR cho {ticket}: validate Review standard, attach pre-review + evidence + regression risk + scope check. Use pull_request_template."

## Required inputs
- Merged-ready change + pre-review result + evidence

## Required project files to read
- `.ai/project-context/engineering-standards/REVIEW_STANDARD.md`, `templates/pull_request_template.md`
- `AGENTS.md` §13 (PR rules), §12 (high-risk)

## Required Engineering Standards (load trước)
ENGINEERING_PRINCIPLES → REVIEW, CODING, DEVELOPMENT + `technologies/{tech}`.

## Dependencies
- Agent: developer, tl
- Skill: `review-code` (+ `security-review` nếu sensitive)
- Rule: `evidence-required.md`, `engineering-standards-enforcement.md`, `security-first.md`
- Hook: `before-pr`

## Execution steps
1. Load REVIEW standard; run/confirm pre-review (no critical).
2. Fill PR template: changes summary, review-code summary, regression risk, files changed, scope check, tests.
3. Attach evidence (`pre-review.md` + test-result).
4. Confirm one-concern + reference ticket; submit cho TL review.

## Expected output
PR (template filled) + pre-review summary + evidence attached.

## Evidence required
`.ai/evidence/{task}/`: pre-review.md + test-result (referenced trong PR).

## Memory files to update
- `CURRENT_STATE.md` (status: in review)

## Failure handling
- Critical finding chưa fix → không submit; fix trước.
- Scope mixed → split PR (one concern).

## When to improve/update
- Khi PR template miss field recurrent → update template; record.
