---
id: SPIKE-W273TB
type: spike
title: 'Audit ShippingCore + đề xuất orchestration canonical VN address resolution (Phase E design, pre-implementation)'
project_code: SLP
parent: {type: feature, id: FEAT-YA2C0W}
mode: A
specification_level: MINI
spec_status: VALID            # user-directed audit request 2026-09-04 (scope + 10 câu hỏi + DoD trong request); TL review report pending
specification_ref: Embedded Mini-Spec
risk: medium
status: in_progress
created: 2026-09-04
updated: 2026-09-04
decisions: [DEC-FEATYA2C0W-003, DEC-FEATYA2C0W-004]
decision_assessment: none-material   # audit VALIDATE DEC-004 D1-D10 trên code thật; contract shapes là đề xuất cho Phase E — TL approve trước khi implement
components:
  - CMP-SHIPPING
  - CMP-VNADDR
source_areas:
  - app/code/Secomm/ShippingCore/
  - app/code/Secomm/GiaoHangNhanh/
  - app/code/Secomm/GhnAddressMapper/
  - app/code/Secomm/Ghtk/
  - app/code/Secomm/Ahamove/
changes_project_state: true
changes_architecture: false   # analysis/design only — 0 production code
changes_integration: false
changes_known_limitations: true
last_verified: 2026-09-04
supersedes: []
---

# [SLP][FEAT-YA2C0W][SPIKE-W273TB] Audit ShippingCore + đề xuất orchestration canonical VN address resolution

## Mini Spec

### Goal

Audit code thật của `Secomm_ShippingCore` + carriers (GHN/GHTK/Ahamove), trả lời 10 câu hỏi architecture, đề xuất contract lean cho Phase E (orchestration resolution trước carrier API) — **analysis/design only, 0 production code**.

### Expected Behavior

1. Report 10 sections theo expected output của request: current state thực tế (không assume), responsibility boundary, proposed contracts (PHP shapes, không implement), runtime flow per status, external resolver strategy, carrier capability model (GHN/GHTK/Ahamove asymmetric), performance/caching, failure behavior (no first-candidate fallback), follow-up plan A-G, risks/open decisions.
2. GHN: xác định nơi lấy district/ward IDs + hardcoded fallbacks (1456/21511/'Phường 17') còn tồn tại không + migration cần gì (report-only).
3. GHTK: xác định cần provider IDs hay text; Ahamove: text/geocode.
4. Chỉ tạo DEC khi phát hiện decision mới GỐC không được DEC-004 bao phủ.

### Constraints / Rules

- KHÔNG implement code/DTO/config/cache; KHÔNG refactor carrier; KHÔNG migration.
- ShippingCore KHÔNG own mapping data; VietNamAddress giữ scheme registry/units/graph/cardinality/status detection.
- AMBIGUOUS không bao giờ auto-pick candidate[0]; row ordering vô nghĩa.

### Out of Scope

Implementation của mọi contract đề xuất; VietMap/Google; carrier migration; new DB/config/cache.

### Acceptance Criteria

- AC-001: report file `.ai/research/SPIKE-W273TB-shippingcore-address-orchestration.md` đủ 10 sections, mọi claim về current state có file:line.
- AC-002: contracts đề xuất lean (≤ ~5 methods/interface), có lý do "tối thiểu hữu ích" từ carrier thật.
- AC-003: follow-up plan A-G có thứ tự + dependency.
- AC-004: unresolved decisions chỉ liệt kê cái THẬT sự chưa chốt sau khi đọc code.

## Approach

Read-only audit: ShippingCore (25 files) + GiaoHangNhanh (data builders, GHN carrier) + GhnAddressMapper (LocationResolver, schema) + Ghtk (Address/*: WardIdBridge, DestinationAddressResolver, PickupAddress) + Ahamove (carrier address payload) + module sequences + DEC-003/004 đối chiếu.

## Verification

- [x] AC-001..004 — 2026-09-04: report hoàn thành với file:line evidence; contracts ≤5 methods; fallback GHN còn live (config-gated `is_develop_mode`); GHTK text-based + mapping-first; Ahamove text/geocode. Evidence report + file:line trong `.ai/research/SPIKE-W273TB-shippingcore-address-orchestration.md`.

## Related records

- Parent FEAT-YA2C0W; DEC-FEATYA2C0W-003 (canonical layer) + DEC-FEATYA2C0W-004 (D1-D10 — dependency direction, capability, ambiguity, no unnecessary abstraction); TASK-J9AVGK (mapping/resolver); TASK-NDSZ7V (mapping seed 10.064 edges).
