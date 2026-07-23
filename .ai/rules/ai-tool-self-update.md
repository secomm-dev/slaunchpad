# Rule: AI-Tool Self-Update

> **Ngôn ngữ:** Vietnamese (rule). Copy vào `.ai/rules/ai-tool-self-update.md`. Governance cho `update-project-ai-tool` function — update project AI tool (agent/skill/rule/hook/prompt/memory/workflow) trong khi delivery thật.

## Rule

Project AI tool có thể **tự cập nhật** khi team phát hiện pattern mới — nhưng dưới các ràng buộc sau (KHÔNG tùy tiện):

1. **Không bao giờ overwrite project memory blind.** `CURRENT_STATE.md`, `NEXT_TASK.md`, `DECISIONS.md`, `LESSONS_LEARNED.md`, `CONTINUOUS_LEARNING.md`, `RESEARCH_NOTES.md`, `SECURITY_BASELINE.md`, `project-context/06` là project-owned — update qua diff + human review, không silent overwrite.
2. **Không xóa rule/agent/function hiện có mà không record decision.** Mọi removal/supersede → ADR trong `DECISIONS.md` (context + lý do).
3. **Mark function/agent changed với version + date.** Mỗi artifact có header `<!-- vN, YYYY-MM-DD -->` (hoặc tương tự); update khi change.
4. **Update `CHANGELOG_AI_TOOL.md`** cho mỗi change (version/date/category/file/reason).
5. **Record reason trong `DECISIONS.md`** (ADR cho change significant).
6. **Record reusable lesson trong `CONTINUOUS_LEARNING.md`** (pattern phát hiện dẫn đến change).
7. **Project-specific → giữ trong project.** Không push lên toolkit trừ khi reusable cross-project.
8. **Reusable cross-project → propose backport** lên `secomm-production-ai-toolkit` (qua `sync-toolkit-updates` skill ngược / PR) — không tự sửa toolkit từ project.
9. **Change affect security → run security audit (`audit-security`) TRƯỚC** khi apply.

## Khi nào apply

- Khi team phát hiện function/agent/rule miss recurring case → update function/agent.
- Khi convention mới phát hiện → update rule/skill.
- Khi workflow change → update hook/workflow-guide.
- Luôn qua `update-project-ai-tool` function + hook `before-ai-tool-update` / `after-ai-tool-update`.

## Enforce

- Hook `before-ai-tool-update`: confirm reason recorded, memory không overwrite blind, security-audit nếu relevant.
- Hook `after-ai-tool-update`: `CHANGELOG_AI_TOOL.md` updated, version/date marked, `DECISIONS.md` + `CONTINUOUS_LEARNING.md` updated.
- Auditor (AI Output Audit) check `CHANGELOG_AI_TOOL.md` consistency định kỳ.

## Liên kết

- Function: `update-project-ai-tool.md`
- Hook: `before-ai-tool-update`, `after-ai-tool-update`
- Rule kèm: `no-duplicate-knowledge.md`, `memory-update.md`, `evidence-required.md`, `security-first.md`
- Backport: `skills-source/sync-toolkit-updates/SKILL.md` (toolkit-level)
