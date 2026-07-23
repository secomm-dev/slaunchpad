# Rule: No-Duplicate-Knowledge

> **Ngôn ngữ:** Vietnamese (rule). Copy vào `.ai/rules/no-duplicate-knowledge.md`. Nguyên tắc xuyên suốt toolkit (single source of truth).

## Rule

**Một sự thật ở một chỗ. Link, không copy.** Khi thông tin đã có ở đâu đó (project-context, AGENTS.md, DECISIONS.md, LESSONS_LEARNED), reference nó — không viết lại. Risk → `project-context/06` (không tạo `KNOWN_RISKS.md` riêng). Business rule → `02_BUSINESS_RULES.md`. Coding standard → `CODING_RULES.md`. Convention phát hiện → `CONTINUOUS_LEARNING.md`.

## Khi nào apply

- Mỗi khi AI sắp viết lại thông tin đã có.
- Khi tạo memory/rule/instinct mới — check trùng trước.

## Enforce

- Verification: grep no-duplicate trong audit (Code Quality, AI Output) — [`workflow-guides/audit-workflows.md`](../../workflow-guides/audit-workflows.md).
- Rule/instinct file PHẢI reference (không restate) nguồn `core/`.

## Liên kết

- Instinct: #6 — [`shared-core/instincts/instincts.md`](../instincts/instincts.md)
- Single-source design: [`core/project-memory-standard.md`](../../core/project-memory-standard.md) (KNOWN_RISKS = 06)
- AGENTS.md SSOT: `stack-templates/_base/AGENTS.base.md`
