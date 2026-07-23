# Rule: Backward-Compatibility

> **Ngôn ngữ:** Vietnamese (rule). Copy vào `.ai/rules/backward-compatibility.md`.

## Rule

**Không break thứ đang hoạt động.** Interface/API/data/behavior đang dùng phải giữ backward-compatible trừ khi có plan + risk review + Tier 2 escalation. Change breaking (API contract, DB schema, order state machine, event payload) → version hóa, migration path, deprecation notice — không silent break.

## Khi nào apply

- Khi change API endpoint/contract, DB schema, integration payload, event/webhook, order state, public method.
- Upgrade platform (Magento/Shopify/Laravel version).

## Enforce

- High-risk area change → Tier 2 escalation — [`core/escalation-rules.md`](../../core/escalation-rules.md).
- API contract breaking change → `headless-api-contract-review` skill.
- DB schema → migration script test trên staging — `release-checklist.md`, `deploy` skill.

## Liên kết

- Instinct: #4 — [`shared-core/instincts/instincts.md`](../instincts/instincts.md)
- Skill: `headless-api-contract-review`, `magento-upgrade-review`
- Rule kèm: `security-first.md`, `production-readiness.md`
