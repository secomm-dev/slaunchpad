# Checklists — Hook Sequence Index

Các checklist này là "hooks" lightweight ở mỗi điểm trong delivery lifecycle — chạy nhanh, không cần runtime. Chạy theo sequence khi work tiến triển. Các checklist existing cover readiness, review, và release; các additions ECC-inspired fill các gap lifecycle phía developer (pre-task, post-task, pre-commit) và thêm security + client-handoff hooks.

## Lifecycle Sequence

| # | Hook | Checklist | Owner |
|---|------|-----------|-------|
| 0 | Join a project | [onboarding-checklist.md](onboarding-checklist.md) | New joiner |
| 0 | Bootstrap a project | [project-bootstrap-checklist.md](project-bootstrap-checklist.md) | TL/SA |
| 0 | Set up context pack | [project-context-pack-checklist.md](project-context-pack-checklist.md) | TL/SA |
| 1 | Requirement ready? | [requirement-readiness-checklist.md](requirement-readiness-checklist.md) | PM/BA |
| 1 | Ticket ready? | [ticket-readiness-checklist.md](ticket-readiness-checklist.md) | PM/BA/TL |
| 1 | Technical solution sound? | [technical-solution-checklist.md](technical-solution-checklist.md) | SA/TL |
| 2 | **Start a task** | [pre-task-checklist.md](pre-task-checklist.md) | Developer |
| — | *(development)* | — | Developer |
| 3 | **Wrap a task** | [post-task-checklist.md](post-task-checklist.md) | Developer |
| 4 | AI pre-review | [ai-pre-review-checklist.md](ai-pre-review-checklist.md) | Developer/AI |
| 4 | Security-sensitive change | [security-review-checklist.md](security-review-checklist.md) | Developer/AI |
| 5 | **Before commit** | [pre-commit-checklist.md](pre-commit-checklist.md) | Developer |
| 6 | TL code review | [code-review-checklist.md](code-review-checklist.md) | TL |
| 7 | QC testing | [qc-checklist.md](qc-checklist.md) | QC |
| 8 | Release / deploy + post-deploy | [release-checklist.md](release-checklist.md) | TL/DevOps |
| 9 | Post-release context update | [post-release-context-update-checklist.md](post-release-context-update-checklist.md) | Developer/TL |
| 10 | **Client handoff** | [client-handoff-checklist.md](client-handoff-checklist.md) | TL/PM |

> Các mục **bold** là additions ECC-inspired fill các gap lifecycle. Phần còn lại đã existing.

## When to run which (quick map)

- **Mọi task**: pre-task → (dev) → post-task → ai-pre-review → pre-commit → code-review
- **Security-sensitive task**: thêm security-review trước pre-commit
- **Mọi release**: release-checklist (cover pre-deploy + deploy + post-deploy + rollback) → post-release-context-update
- **Go-live / closeout**: thêm client-handoff
- **Periodic (Auditor)**: dùng audit workflows trong [audit-workflows.md](../workflow-guides/audit-workflows.md) thay vì các per-task checklist này

## Cross-References

- Governance đằng sau các gates: [delivery-governance.md](../core/delivery-governance.md), [quality-gates.md](../core/quality-gates.md)
- Definition of Ready / Done: [definition-of-ready.md](../core/definition-of-ready.md), [definition-of-done.md](../core/definition-of-done.md)
- Security standard: [production-ai-security.md](../core/production-ai-security.md)
