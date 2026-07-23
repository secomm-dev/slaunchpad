# update-project-ai-tool

> Function (VI guidance). Copy vào `.ai/functions/update-project-ai-tool.md`. Self-update function — update project AI tool (agent/skill/rule/hook/prompt/memory/workflow) trong khi delivery. Governance: `.ai/rules/ai-tool-self-update.md`.

## Mục đích
Cập nhật project AI tool khi team phát hiện pattern mới — thêm/sửa agent, skill, rule, hook, prompt, memory file, workflow — một cách có kiểm soát (version, changelog, decision, không overwrite memory blind).

## Trigger
- Command: `/update-project-ai-tool` (hoặc `/update-tool`)
- Prompt snippet: "Update project AI tool: {what change + why}. Follow ai-tool-self-update rule. before/after hook. Record CHANGELOG + DECISIONS + CONTINUOUS_LEARNING."

## Required inputs
- Change đề xuất (what: agent/skill/rule/hook/prompt/memory/workflow)
- Reason (pattern phát hiện / miss case / convention mới)
- Scope (project-specific vs reusable cross-project)

## Required project files to read
- `.ai/rules/ai-tool-self-update.md` (governance)
- `.ai/CHANGELOG_AI_TOOL.md` (change history; next version)
- Artifact đang sửa (function/agent/rule/hook)
- `DECISIONS.md`, `CONTINUOUS_LEARNING.md`

## Dependencies
- Rule: `ai-tool-self-update.md` (bắt buộc), `no-duplicate-knowledge.md`, `memory-update.md`, `evidence-required.md`, `security-first.md`
- Hook: `before-ai-tool-update`, `after-ai-tool-update`
- Agent: tl (approve change), project-auditor (review)
- (Backport) Skill: `sync-toolkit-updates` (toolkit-level, nếu reusable)

## Execution steps
1. **Hook `before-ai-tool-update`**: confirm reason recorded; memory không overwrite blind; nếu change affect security → run `audit-security` TRƯỚC.
2. **Draft change**: thêm/sửa/supersede artifact (KHÔNG xóa rule mà không ADR).
3. **Mark version/date**: update artifact header (`<!-- vN, YYYY-MM-DD -->`).
4. **Project-specific vs reusable**: project-specific → giữ trong project; reusable cross-project → note "propose backport" (không tự sửa toolkit).
5. **Record**: append `CHANGELOG_AI_TOOL.md` (version/date/category/file/reason); append `DECISIONS.md` (ADR nếu significant); append `CONTINUOUS_LEARNING.md` (pattern).
6. **Hook `after-ai-tool-update`**: verify CHANGELOG updated, version marked, DECISIONS + CONTINUOUS_LEARNING recorded.
7. **TL review** change trước commit.

## Expected output
- Updated artifact (version/date marked)
- `CHANGELOG_AI_TOOL.md` entry
- `DECISIONS.md` ADR (nếu significant)
- `CONTINUOUS_LEARNING.md` lesson
- (Nếu reusable) backport proposal note

## Evidence required
- Change diff + `CHANGELOG_AI_TOOL.md` entry + (security) audit-security result.

## Memory files to update
- `CHANGELOG_AI_TOOL.md` (primary log)
- `DECISIONS.md` (ADR), `CONTINUOUS_LEARNING.md` (pattern)
- (KHÔNG overwrite `CURRENT_STATE`/`NEXT_TASK`/`06` blind — chỉ qua diff + review)

## Failure handling
- Overwrite memory blind → REJECT; phải diff + review.
- Xóa rule không ADR → REJECT; phải record decision.
- Change affect security chưa audit → block; run `audit-security` trước.
- Reusable change tự sửa toolkit → REJECT; propose backport.

## When to improve/update
- Khi self-update rule miss guard → update rule `ai-tool-self-update.md` (+ ADR).
- Khi backport process unclear → clarify trong function này.
