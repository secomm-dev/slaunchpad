# Rule: Planning-First

> **Ngôn ngữ:** Vietnamese (rule). Copy vào `.ai/rules/planning-first.md`. Đây là phiên bản cô đặc — nguồn chính thức: [`core/ai-operating-principles.md`](../../core/ai-operating-principles.md) (Planning-First Sequence).

## Rule

**Không modify code khi chưa có plan.** Follow sequence: đọc project context → check `PROJECT_AI_BLUEPRINT.md` → confirm spec/AC → plan (Mode A/B) hoặc approach note (Mode C) → chỉ khi đó code.

## Khi nào apply

- Mọi task trước khi viết code.
- Emergency debug: inspect log/code để reproduce được allow trước plan; nhưng short plan (fix/file/risk) phải có trước khi modify — dù Mode D.

## Enforce

- **Hard Gate 3** (No Code Without Plan) — [`core/delivery-governance.md`](../../core/delivery-governance.md). Violation → block commit/PR.
- Code Gate (Gate 3) verify plan tồn tại — [`core/quality-gates.md`](../../core/quality-gates.md).

## Liên kết

- Instinct: #1 (research trước), #3 (conventions) — [`shared-core/instincts/instincts.md`](../instincts/instincts.md)
- Rule kèm: `research-first.md`
- Skill: `task`, `spec`
