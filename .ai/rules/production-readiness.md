# Rule: Production-Readiness

> **Ngôn ngữ:** Vietnamese (rule). Copy vào `.ai/rules/production-readiness.md`. Nguồn: [`core/quality-gates.md`](../../core/quality-gates.md) (Release Gate) + [`core/delivery-governance.md`](../../core/delivery-governance.md) (Hard Gate 5).

## Rule

**Không deploy production khi chưa pass release gate.** Deployment checklist complete + rollback plan test + ít nhất một người không viết code đã review + QC signoff (Mode A/B) + smoke test pass. AI không bao giờ deploy. Change production-sensitive phải có risk review + evidence.

## Khi nào apply

- Trước mọi production deployment (mọi mode, gồm hotfix — dù abbreviated, rollback plan vẫn bắt buộc).
- Khi change vùng production-sensitive.

## Enforce

- **Hard Gate 5** (No Production Deploy Without Release Gate).
- Release Gate (Gate 5) — [`core/quality-gates.md`](../../core/quality-gates.md).
- Deployment Readiness Audit — [`workflow-guides/audit-workflows.md`](../../workflow-guides/audit-workflows.md).
- Hook: `before-deploy`, `after-deploy` → `release-checklist.md`.

## Liên kết

- Instinct: #8, #9, #11 — [`shared-core/instincts/instincts.md`](../instincts/instincts.md)
- DevOps agent: [`shared-core/agents/devops.md`](../agents/devops.md)
- Evidence: [`shared-core/evidence/evidence-policy.md`](../evidence/evidence-policy.md)
- Rule kèm: `evidence-required.md`, `backward-compatibility.md`
