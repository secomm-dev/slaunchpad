# Rule: Memory-Update

> **Ngôn ngữ:** Vietnamese (rule). Copy vào `.ai/rules/memory-update.md`. Nguồn: [`core/project-memory-standard.md`](../../core/project-memory-standard.md) + [`core/context-management-standard.md`](../../core/context-management-standard.md).

## Rule

**Giữ memory current.** Compact tại milestone → cập nhật `CURRENT_STATE.md`/`NEXT_TASK.md` (replace stale); append `DECISIONS.md`/`LESSONS_LEARNED.md`/`CONTINUOUS_LEARNING.md` khi có decision/lesson; resume từ memory ở session start. Risk → `project-context/06` (single source). KHÔNG store secret/PII/log/scratch.

## Khi nào apply

- Session start: continue.
- Milestone (research/debugging/implementation sub-task/handoff): compact-context.
- Decision made / lesson learned / risk found: record-*.

## Enforce

- Tracked Expectation (context update sau release) — [`core/delivery-governance.md`](../../core/delivery-governance.md).
- Skill: `compact-context`, `continue`, `status`, `update-memory`.
- Command shims: `/compact-context`, `/resume-work`, `/update-memory`, `/record-decision`, `/record-risk`, `/record-lesson`.

## Liên kết

- Instinct: #10 — [`shared-core/instincts/instincts.md`](../instincts/instincts.md)
- Memory set + ownership: [`core/project-memory-standard.md`](../../core/project-memory-standard.md)
- Compact/resume discipline: [`core/context-management-standard.md`](../../core/context-management-standard.md)
