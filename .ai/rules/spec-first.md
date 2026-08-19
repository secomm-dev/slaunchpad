# Rule: Spec-First — NO SPEC → NO IMPLEMENTATION

> **Ngôn ngữ:** Vietnamese (rule). Copy vào `.ai/rules/spec-first.md`.
> Canonical invariant (DEC-SL018-001): **Every code change starts from an explicit behavioral specification. A small task may use an embedded Mini-Spec; a larger or higher-risk feature requires a Full Spec. NO VALID SPECIFICATION = NO IMPLEMENTATION.**
> Không weaken cho speed, quick fixes, bug fixes, direct commands, hay agent delegation.

## Rule

**NO SPEC → NO PLAN FOR IMPLEMENTATION → NO CODE CHANGE.**

Mọi executable task PHẢI có MỘT trong hai trước khi planning/implementation:

1. **Full Spec reference** — file spec riêng (canonical cho feature), hoặc
2. **Embedded Mini-Spec** — nằm trực tiếp trong ticket.

"Không có file spec riêng" ≠ "không cần spec". Task nhỏ vẫn phải có Mini-Spec làm behavioral contract.

### Mini-Spec (embedded — minimum sections)

```text
## Mini Spec
### Goal                  — task giải quyết vấn đề gì
### Expected Behavior     — hệ thống behave thế nào sau khi hoàn thành   [CRITICAL]
### Constraints / Rules   — rule không được phá
### Out of Scope          — những gì không làm
### Acceptance Criteria   — điều kiện xác nhận hoàn thành               [CRITICAL]
```

Thiếu `Expected Behavior` hoặc `Acceptance Criteria` ⇒ task **chưa executable**.

### Full Spec (bắt buộc khi)

reusable feature · feature nhiều ticket · cross-module · architecture change · integration có lifecycle/state phức tạp · state machine/workflow · payment/shipping/fulfillment · backward compatibility quan trọng · shared framework/core capability · nhiều developer/agent cùng implement · high-risk · nhiều business rules.

**Full Spec đứng TRƯỚC ticket decomposition** — mọi ticket con reference cùng canonical spec (một spec cho cả feature; không duplicate business rules ra nhiều spec).

## Classification trước artifacts

```text
Requirement → Classify complexity/risk → Specification Level (MINI | FULL)
  → Spec → Validate spec → Plan → Implement → Verify against spec
```

## SpecificationGuard (defense in depth — 4 layer)

1. **Rule:** file này + `planning-first.md` (sequence yêu cầu *valid specification*, không phải "ticket với AC").
2. **Gates/state:** Requirement Gate (Gate 1) + Code Gate (Gate 3) + Definition of Ready + WORKFLOW_STATE_MODEL — `spec_status = VALID` cần cho Task Created → Planning → Implementing.
3. **Entry points:** `implement-task`, `analyze-ticket`, `fix-bug`, `refactor-code`, developer agent, `task`/`spec` skills, pre-task checklist — check spec trước, block khi thiếu:

```text
IMPLEMENTATION BLOCKED
Reason: No valid specification found.
Required: Full Spec reference, or Embedded Mini-Spec (with Expected Behavior + Acceptance Criteria).
Create/complete the specification before planning or implementation.
```

4. **Machine validator (SpecReadinessGuard):** `bin/project-ai-validate --check-specs` — scan canonical records (`specification_level` / `spec_status` / `specification_ref` frontmatter) + legacy tickets + plans. Record ở trạng thái executable (ready/planned/in_progress/active…) thiếu spec hợp lệ ⇒ **FAIL**; trạng thái non-active ⇒ WARN (legacy — enforce khi activate, xem "Legacy tickets"); plan KHÔNG có dòng `| Specification | … |` ⇒ FAIL. Được gọi trực tiếp để implement? Agent vẫn phải check spec (rule này) — validator không thay thế prompt-side check.

## Executable task — definition

```text
[ ] Requirement/ticket exists
[ ] Specification level determined (MINI/FULL)
[ ] Valid Full Spec OR embedded Mini-Spec exists
[ ] Acceptance Criteria exists
[ ] Out of Scope / constraints sufficiently clear
[ ] Plan references specification (dòng "Specification:" trong plan)
```

Thiếu ⇒ `task.executable = false`.

## Plan derive từ Spec

- Plan phải chứa: `Specification: <path> | Mini-Spec (embedded) — <ticket>`.
- Plan KHÔNG redefine business requirements trái spec. Implementation analysis thấy spec không khả thi ⇒ raise spec conflict ⇒ update/spec review ⇒ revalidate — không silently đổi behavior trong plan.
- Conflict resolution: **Spec > Plan > implementation assumptions**. Requirement canonical mới hơn spec ⇒ update spec trước khi continue.

## Legacy tickets

Không tự động coi ticket cũ là compliant. Khi một existing ticket được chọn để execute: check spec → thiếu ⇒ generate/propose Mini-Spec hoặc Full Spec (AI được bổ sung spec từ evidence: existing code, established architecture, explicit project rules, canonical requirements — **ghi rõ nguồn**; không đoán) ⇒ validate ⇒ continue. Không migrate toàn bộ backlog upfront.

## Không over-document

Small task → Mini-Spec embedded (KHÔNG file riêng). Feature → MỘT shared Full Spec. Không sinh ticket.md + spec.md + mini-spec.md + plan.md + requirement.md cho một bug 30 phút. Mục tiêu là **behavioral clarity**, không phải document volume.

## Verification quay lại spec

Definition of Done không chỉ check plan completion — mỗi ticket phải xác nhận: spec/mini-spec nào được implement · AC nào pass · constraints/invariants nào được verify (post-task checklist + testcase skill).

## Spec Naming (canonical — DEC-SL019-001)

Spec **identity đến từ Feature/Ticket owner**; slug chỉ là suffix dễ đọc — KHÔNG phải identity.

**Generic pattern:** `SPEC-<OWNER-ID>-<slug>.md` — OWNER-ID = FEATURE-ID (spec thuộc feature) hoặc TICKET-ID (standalone: SL-/BUG-/TASK-/REL-).

- **Full Spec thuộc Feature** (nhiều ticket, canonical — tối đa 1 spec/feature):
  - Filename: `SPEC-<FEATURE-ID>-<slug>.md` (vd `SPEC-FEAT-006-ghtk-shipping-carrier.md`)
  - Specification ID: `SPEC-FEAT-006`
- **Full Spec thuộc standalone Ticket**: `SPEC-<TICKET-ID>-<slug>.md` (vd `SPEC-BUG-042-fix-cod-calculation.md`, `SPEC-SL-015-shippingcore-origin-contract.md`); ID = `SPEC-BUG-042`.
- **Mini-Spec**: embedded trong ticket — **KHÔNG file riêng, KHÔNG ID riêng**: `Specification Level: MINI` + `Specification ID: <TICKET-ID>` (placeholder `MINI-{NNN}` deprecated).
- **Metadata header của Full Spec** (khớp filename): `Specification ID: SPEC-<OWNER-ID>` · `Feature ID: <FEATURE-ID> | NONE` · `Specification Level: FULL`.
- **Slug**: lowercase kebab-case, ngắn, descriptive, tiếng Anh kỹ thuật (vd `ghtk-shipping-carrier`, `fix-cod-calculation`). Đổi slug KHÔNG đổi Specification ID.
- **Machine-resolvable**: `^SPEC-(FEAT|BUG|REL|SL|TASK)-[0-9]{3,}-[a-z0-9]+(-[a-z0-9]+)*\.md$`
- **Cấm** khi đã có owner ID: `SPEC.md`, `SPEC-001.md`, `SPEC-{NNN}` global, `feature-spec.md`, `specification.md`, `final-spec-v2.md`, file Mini-Spec riêng.
- **Legacy**: file slug-only hiện có giữ nguyên (grandfathered); rename-on-touch qua safe workflow khi spec được sửa/activate + update refs — không bulk rename.

## Liên kết

- Rule kèm: `planning-first.md` (plan gate nằm sau spec gate) · `no-duplicate-knowledge.md` (một spec cho feature)
- Gate: `core/quality-gates.md` (Gate 1/3) · `core/delivery-governance.md` · `core/definition-of-ready.md`
- State: `shared-core/workflows/WORKFLOW_STATE_MODEL.md`
- Validator: `bin/project-ai-validate --check-specs`
- Decision: DEC-SL018-001
