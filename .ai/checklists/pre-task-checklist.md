# Pre-Task Checklist

Hook "start of task" lightweight cho developer (trước khi viết code). Confirm context đã load, scope rõ ràng, và có plan — [planning-first](../core/ai-operating-principles.md) gate trong action. Cho readiness check sâu hơn, dùng [requirement-readiness-checklist.md](requirement-readiness-checklist.md), [ticket-readiness-checklist.md](ticket-readiness-checklist.md), và [definition-of-ready.md](../core/definition-of-ready.md).

## Context Loaded

- [ ] Đã đọc AGENTS.md **theo task class** (xem `.ai/AGENTS.md` §4 table) — Mode C/D: chỉ §12 + ticket; Mode A/B: + §7, §9
- [ ] Đã đọc relevant project-context/ files (01, 02 business rules, 04 code areas, 06 risks)
- [ ] Nếu resume: đã chạy [continue](../skills-source/continue/SKILL.md) — confirm đang ở đâu
- [ ] Đã đọc ticket/spec + acceptance criteria của nó

## Scope Clear

- [ ] In scope explicit; out of scope explicit
- [ ] Acceptance criteria testable (không chỉ "as a user I want…")
- [ ] Affected files/modules/integrations đã identify

## Plan Exists

- [ ] Implementation plan đã review (Mode A/B) hoặc approach note đã viết (Mode C)
- [ ] Plan reference ticket/spec mà nó implement
- [ ] Emergency debug only: đã inspect logs/code, nhưng short plan được viết trước mọi change

## Risk & Dependency Check

- [ ] High-risk areas (payment/checkout/order/DB/security/auth) đã flag → escalation nếu touch
- [ ] Dependencies với tickets/teams/integrations khác đã identify
- [ ] New dependencies require TL approval (Hard Gate) — flag nếu cần

## Ready

- [ ] Tất cả above pass — proceed to development
- [ ] Bất kỳ blocker → escalate (không start coding quanh nó)
