---
id: SPIKE-A1DGPY
type: spike
title: 'GHTK API contract audit sau ShippingCore v5 — operation matrix (RATE/CREATE/CANCEL/TRACK/WEBHOOK/PICKUP), status/COD/idempotency semantics, delta Secomm_Ghtk hiện tại'
project_code: SLP
parent: {type: feature, id: FEAT-YA2C0W}
mode: A
specification_level: MINI
spec_status: VALID            # Embedded Mini-Spec — user-directed audit task 2026-09-14 (30 mục directive + DoD); analysis-only, 0 production code
specification_ref: Embedded Mini-Spec
risk: medium                  # analysis only; probe = read-only staging curl khi có token
status: in_progress
priority: high
decision_assessment: none-material # SPIKE — findings là ĐỀ XUẤT chờ review; supersede/amend DEC chỉ khi user accept report
decisions: [DEC-FEATYA2C0W-004, DEC-TASK7AJ3K8-001, DEC-TASK7AJ3K8-002]
components:
  - CMP-SHIPPING
source_areas:
  - app/code/Secomm/Ghtk/
  - app/code/Secomm/ShippingCore/
  - .ai/project-context/architecture/address-shipping.md
changes_project_state: true
changes_architecture: false   # analysis/design only — 0 production code
changes_integration: true     # external API contract knowledge (docs-verified) — recorded in evidence
changes_known_limitations: true
created: 2026-09-14
updated: 2026-09-14
owner: [dev]
related_tickets: [TASK-7AJ3K8, TASK-Y3X6H5, TASK-STC3NB]
---

# [SLP][FEAT-YA2C0W][SPIKE-A1DGPY] GHTK API contract audit sau ShippingCore v5

## Mini Spec

### Goal

Audit GHTK official API theo OPERATION (RATE/CREATE/CANCEL/TRACK/WEBHOOK/PICKUP) từ official
docs (api.ghtk.vn), đối chiếu 3 lớp: official API ↔ architecture v5
(`.ai/project-context/architecture/address-shipping.md`) ↔ implementation `Secomm_Ghtk` hiện
tại (r1). Đúng delta đến class/file; KHÔNG sửa production code; probe staging chỉ khi có token.

### Expected Behavior

1. Operation matrix đầy đủ (method/endpoint/auth/fields/address/error/retry/idempotency) theo
   official docs — VERIFIED_FROM_OFFICIAL_DOCS; mọi điểm chưa đủ evidence → NEEDS_RUNTIME_VERIFICATION.
2. Status table chính thức vs `GhtkStatusMapper` — classify P0 nếu semantic sai.
3. COD: `pick_money`/`pick_option`/`is_freeship` semantics chính xác; đối chiếu COD identification
   ownership (v5 §4.1 — ShippingCore `CodPaymentMethodResolverInterface`).
4. CREATE idempotency: `ORDER_ID_EXIST` → FAILURE hay RECOVERABLE_EXISTING_ORDER.
5. Error classification + retry matrix per operation (semantic-based, không GET/POST simplistic).
6. ShippingCore v5 compatibility: MATCH / CARRIER CODE CHANGE / POSSIBLE GAP (gap chỉ khi
   concrete evidence + không thể carrier-side).
7. Deliverables: contract matrix, per-operation capability (chỉ freeze khi evidence đủ),
   P0/P1/P2, implementation backlog theo production risk.

### Constraints / Rules

- KHÔNG sửa production code / ShippingCore; KHÔNG test production GHTK; KHÔNG invent endpoint;
  KHÔNG assume RATE=CREATE; KHÔNG trust mapper cũ hơn docs; KHÔNG conflate pick_option với COD
  payment; KHÔNG classify 4xx/5xx theo HTTP code trơn.
- Probe: read-only staging (`services-staging.ghtklab.com` — official), chỉ khi token khả dụng.

### Out of Scope

Implementation của bất kỳ delta nào · sửa architecture doc (§32 update chờ findings accepted) ·
module carrier khác.

### Acceptance Criteria

DoD 8 mục của directive: RATE/CREATE semantics tách riêng ✓; status audit đủ ✓; COD 3 fields ✓;
ORDER_ID_EXIST đánh giá ✓; error/retry matrix ✓; delta đến class/file ✓; 0 production refactor ✓;
mọi đề xuất ShippingCore change có concrete evidence ✓.
