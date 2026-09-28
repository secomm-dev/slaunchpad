---
id: TASK-R8WR1R
type: task
title: 'ShippingCore coverage runtime — DestinationScope ALL_EXCEPT_SELECTED_ZONES'
project_code: SLP
parent: null
external_refs:
  tickets: null
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: medium
status: in_review  # r2 2026-09-22 — fix merge blocker theo TL conditional approval: invalid non-empty scope KHÔNG còn coerce về ALL (fail-open) mà trả verbatim + warning; request không còn throw cho scope lạ — evaluator unknown-scope branch là điểm fail-closed (ineligible mọi destination, không TECHNICAL, không fallback). ShippingCore 462/462 OK. Chờ TL re-review
created: 2026-09-22
updated: 2026-09-22
ticket_ref:
affects_version: Magento 2.4.8-p5 + Hyvä 3.x (default theme 1.5.2)
decisions: []
decision_assessment: extension cộng thêm vào coverage model v10 §35 — thêm 1 giá trị domain + 1 nhánh evaluate; không đổi ownership orchestration, không chạm provider logic/admin UI; ShipCORE eligible-inversion là behavior MỚI chỉ kích hoạt khi config chọn scope mới
components:
  - app/code/Secomm/ShippingCore
source_areas:
  - shipping-carrier-eligibility
  - shipping-canonical-zones
changes_project_state: true
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-09-22
supersedes: []
---

# [SLP][TASK-R8WR1R] ShippingCore coverage runtime — DestinationScope ALL_EXCEPT_SELECTED_ZONES

## Ticket + AC

Business case: carrier phục vụ MỌI destination TRỪ các selected zones (vd phục vụ cả VN trừ HCM_INNER/DN_INNER/HN_INNER). Mở rộng shared coverage model v10 §35 một cách tối thiểu — không redesign orchestration.

- **AC-1**: `DestinationScope::ALL_EXCEPT_SELECTED_ZONES` tồn tại làm domain value (thứ 3); `DestinationScope::all()`/`exists()`/`assertKnown()` nhận giá trị mới.
- **AC-2**: Semantics evaluator — `ALL` → eligible; `SELECTED_ZONES` ≥1 enabled zone match → eligible, none → ineligible; `ALL_EXCEPT_SELECTED_ZONES`: ≥1 valid enabled zone match → ineligible, none match → eligible.
- **AC-3**: Empty-zone semantics lock — `SELECTED_ZONES` + list rỗng → ineligible (fail closed); `ALL_EXCEPT_SELECTED_ZONES` + list rỗng → eligible (tương đương ALL); `ALL` → bỏ qua zone list.
- **AC-4**: Unknown/disabled zone references KHÔNG tự loại carrier trong mode ALL_EXCEPT (chỉ valid enabled matching zone mới exclude); diagnostic seam hiện có (`CarrierDestinationScopeConfig`) log stale reference cho cả mode mới; SELECTED_ZONES giữ nguyên fail-closed unknown-zone semantics.
- **AC-5**: `CarrierDestinationScopeConfig` chấp nhận giá trị mới (không coerce về ALL), giữ fail-closed-to-ALL + warning cho giá trị không nhận diện được.
- **AC-6**: Runtime ordering không đổi (CarrierEligibility → RateSourceMode → AddressResolutionPolicy → CarrierRateExecution); không đụng ServiceLevelRateOrchestrator, fallback dispatch, PICK_PRIMARY, GHN/GHTK provider logic, admin UI.
- **AC-7**: Full test matrix theo ticket (10 case) + toàn bộ existing ShippingCore tests giữ nguyên pass.

## Mini Spec

### Goal
Carrier có thể khai báo coverage "mọi destination TRỪ các selected zones" qua shared DestinationScope model, tái dùng đúng CanonicalZoneRegistry/CanonicalZoneMatcher runtime mà không thêm layer/DSL/carrier-specific logic.

### Expected Behavior
Khi `carriers/<code>/destination_scope = ALL_EXCEPT_SELECTED_ZONES`:
- destination khớp ≥1 valid enabled zone trong `allowed_zone_codes` → carrier INELIGIBLE (`DESTINATION_NOT_IN_SCOPE`), đóng cả realtime lẫn fallback contribution (inversion đúng nghĩa "trừ").
- destination không khớp zone enabled nào (kể cả list rỗng, chỉ chứa unknown/disabled) → ELIGIBLE, xử lý như ALL.
- Unknown/disabled zone reference không tự loại ai cả; config reader log warning stale reference (hint "does not exclude").
- `SELECTED_ZONES` + list rỗng vẫn ineligible; `ALL` bỏ qua list. Giá trị scope lạ → ALL + warning (như cũ).

### Constraints / Rules
- Tái dùng `CanonicalZoneRegistry` + `CanonicalZoneMatcher`; exclusion xảy ra ở zone-assignment level, KHÔNG đụng `excludeWardCodes`, không DSL/GIS/origin-relative/carrier-specific matching.
- Không đổi runtime ordering, `ServiceLevelRateOrchestrator`, fallback policy, PICK_PRIMARY.
- Không đụng admin UI (system.xml) — value mới set bằng config CLI/config.php; UI option là follow-up riêng.
- Không đụng provider logic (Ghn/Ghtk chỉ pass-through generic — không cần sửa).
- Domain values là scope enums; zone identities (HCM_INNER…) vẫn là merchant data, không bao giờ hardcode.
- No active behavior change cho config ALL/SELECTED_ZONES hiện hữu.

### Out of Scope
- Admin UI option cho scope mới (system.xml ShippingCore/GHN) — follow-up riêng cần TL.
- Provider-side logic, webhook, orchestration ownership, fallback dispatch.
- Zone persistence schema, `CanonicalZone` fields, matcher precedence.

### Acceptance Criteria
Xem Ticket + AC-1…AC-7 ở trên. Evidence: unit tests ShippingCore pass toàn bộ (cũ + mới), `project-ai-validate` sạch, CHANGELOG + architecture doc §35.1 cập nhật.
