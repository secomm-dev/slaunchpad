# prepare-deployment-checklist

> Function (VI guidance). Copy vào `.ai/functions/prepare-deployment-checklist.md`.

## Mục đích
Generate deployment checklist cho release — pre-deploy, deploy step, post-deploy verify, rollback trigger + step. Orchestrator cho `deploy` skill.

## Trigger
- Prompt snippet: "Generate deployment checklist cho release {version}: pre-deploy, deploy step, post-deploy verify, rollback (specific trigger). Read docs/deployment.md."

## Required inputs
- Release change list / merged PR
- Rollback plan (nếu có)

## Required project files to read
- `docs/deployment.md`
- `project-context/06` (deploy risk), `SECURITY_BASELINE.md` (§3 shell)
- `templates/deployment-checklist-template.md`, `rollback-plan-template.md`

## Dependencies
- Skill: `deploy`
- Agent: devops, tl
- Rule: `production-readiness.md`, `security-first.md`
- Hook: `before-deploy`, `after-deploy`

## Execution steps
1. Đọc deployment doc + change list.
2. Chạy `deploy` skill.
3. Generate: pre-deploy, deploy step (order), post-deploy verify (smoke test), rollback (specific trigger + step + verify).
4. DB migration → note test staging.
5. TL/SA review trước deploy.

## Expected output
Deployment checklist + rollback plan (specific trigger, không generic).

## Evidence required
Checklist lưu `.ai/evidence/{release}/deploy.md` + TL signoff.

## Memory files to update
- `project-context/06` (deploy risk mới)
- `LESSONS_LEARNED.md` (deploy gotcha sau release)

## Failure handling
- Rollback trigger generic → specific hóa (error rate >X%, checkout drop).
- Migration untested → block deploy cho đến khi test staging.

## When to improve/update
- Khi deploy incident reveal miss step → thêm checklist + record lesson.
