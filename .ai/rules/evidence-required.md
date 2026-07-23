# Rule: Evidence-Required

> **Ngôn ngữ:** Vietnamese (rule). Copy vào `.ai/rules/evidence-required.md`. Nguồn: [`shared-core/evidence/evidence-policy.md`](../evidence/evidence-policy.md).

## Rule

**Done requires evidence.** Không mark task "done" nếu không có ít nhất một evidence artifact verify được (command output / test result / screenshot / log excerpt / diff summary / deployment checklist / QA signoff / client approval / TL approval). Evidence phải mask secret/PII; excerpt không full dump; trace về ticket.

## Khi nào apply

- Mọi task trước khi mark complete (Definition of Done).
- Đặc biệt risky change → lưu evidence đầy đủ.

## Enforce

- Definition of Done thêm checkbox evidence — [`core/definition-of-done.md`](../../core/definition-of-done.md).
- Auditor check evidence trong AI Output Audit + Deployment Readiness Audit — [`workflow-guides/audit-workflows.md`](../../workflow-guides/audit-workflows.md).
- Evidence lưu `.ai/evidence/{ticket_id}/` (project-owned runtime).

## Liên kết

- Instinct: #7, #11 — [`shared-core/instincts/instincts.md`](../instincts/instincts.md)
- Evidence policy + 9 loại: [`shared-core/evidence/evidence-policy.md`](../evidence/evidence-policy.md)
- No AI Blind Trust: [`core/no-ai-blind-trust.md`](../../core/no-ai-blind-trust.md)
