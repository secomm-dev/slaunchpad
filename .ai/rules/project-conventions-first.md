# Rule: Project-Conventions-First

> **Ngôn ngữ:** Vietnamese (rule). Copy vào `.ai/rules/project-conventions-first.md`. Nguồn: [`core/ai-operating-principles.md`](../../core/ai-operating-principles.md) (Development Rules).

## Rule

**Prefer convention của project hơn generic best practice.** Follow pattern đang có: naming, structure, design pattern, coding style (PSR-12/Airbnb/theo project standard). Khi rule im lặng → theo code xung quanh. Inspect existing implementation trước khi tạo mới (đã có plugin/module/function chưa).

## Khi nào apply

- Mỗi khi viết/sửa code.
- Khi AI sắp đề xuất pattern "best practice" generic → check codebase có pattern khác không.

## Enforce

- AGENTS.md §7.1 (General Rules: "follow existing code patterns") + §7.2 (Coding Standards) — [`stack-templates/_base/AGENTS.base.md`](../../stack-templates/_base/AGENTS.base.md).
- `CODING_RULES.md` (merged shared + stack).
- Code Gate review check convention compliance.

## Liên kết

- Instinct: #3, #5 — [`shared-core/instincts/instincts.md`](../instincts/instincts.md)
- Rule kèm: `backward-compatibility.md`, `no-duplicate-knowledge.md`
- Research-first: phải inspect conventions (bước 3).
