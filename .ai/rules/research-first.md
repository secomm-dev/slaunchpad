# Rule: Research-First

> **Ngôn ngữ:** Vietnamese (rule). Copy vào `.ai/rules/research-first.md`. Nguồn: [`shared-core/research/RESEARCH_FIRST_DEVELOPMENT.md`](../research/RESEARCH_FIRST_DEVELOPMENT.md) (mở rộng Planning-First).

## Rule

**Trước khi implement, phải research.** Đọc blueprint → inspect project files/conventions/dependencies → review specs/tasks → identify unknowns → ghi vào `RESEARCH_NOTES.md` → tạo plan → mới code. External/fast-changing info **phải** mark `[EXTERNAL: verify]` và verify qua web/vendor docs.

## Khi nào apply

- Mọi task non-trivial trước khi lập plan.
- Khi gặp version/API/config vendor cụ thể (Magento/Shopify/Laravel) — không tin nhớ, verify.

## Enforce

- Là giai đoạn đầu Planning-First Sequence (bước 1–2) → liên kết Hard Gate 3.
- `RESEARCH_NOTES.md` phải có ít nhất mục tiêu + verified/unknown/external trước khi code.

## Liên kết

- Instinct: #1 — [`shared-core/instincts/instincts.md`](../instincts/instincts.md)
- Quy trình đầy đủ: [`shared-core/research/RESEARCH_FIRST_DEVELOPMENT.md`](../research/RESEARCH_FIRST_DEVELOPMENT.md)
- Template: `shared-core/memory/research-notes-template.md`
- Rule kèm: `planning-first.md`, `evidence-required.md`
