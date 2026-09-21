---
id: SPIKE-9Z231Q
type: spike
title: 'Thiết kế kiến trúc Secomm_Ghn thay thế legacy GiaoHangNhanh trên ShippingCore canonical — audit legacy + GHN API compatibility matrix (pre-implementation)'
project_code: SLP
parent: {type: feature, id: FEAT-YA2C0W}
mode: A
specification_level: MINI
spec_status: VALID            # user-directed architecture task 2026-09-08 (scope + 14 sections + banned patterns + DoD trong request); TL/SA review report pending
specification_ref: Embedded Mini-Spec
risk: medium
status: in_progress
created: 2026-09-08
updated: 2026-09-08
decisions: [DEC-FEATYA2C0W-003, DEC-FEATYA2C0W-004, DEC-TASKNDASAD-001, DEC-TASK86NX9T-001, DEC-TASK3F6QWZ-001, DEC-TASK3F6QWZ-002]
decision_assessment: none-material   # audit/design VALIDATE decisions sẵn accepted; mọi contract adaptation là ĐỀ XUẤT — TL/SA approve trước khi code (user directive)
components:
  - CMP-SHIPPING
  - CMP-VNADDR
source_areas:
  - app/code/Secomm/GiaoHangNhanh/
  - app/code/Secomm/GhnAddressMapper/
  - app/code/Secomm/ShippingCore/
  - app/code/Secomm/VietNamAddress/
changes_project_state: true
changes_architecture: false   # analysis/design only — 0 production code
changes_integration: false
changes_known_limitations: true
last_verified: 2026-09-08
supersedes: []
work_items: [SPIKE-9Z231Q, FEAT-YA2C0W]
related_tickets: [SPIKE-W273TB, TASK-AQT7V3, BUG-JBX3H9, DEC-TASK3F6QWZ-001, DEC-TASK3F6QWZ-002]
---

# [SLP][FEAT-YA2C0W][SPIKE-9Z231Q] Thiết kế kiến trúc Secomm_Ghn thay thế legacy GiaoHangNhanh trên ShippingCore canonical — audit legacy + GHN API compatibility matrix (pre-implementation)

## Mini Spec

### Goal

Audit legacy `Secomm_GiaoHangNhanh` + `Secomm_GhnAddressMapper` (reference-only) và GHN API
docs hiện hành (developer.ghn.vn, post 01/07/2025 admin reform), thiết kế module mới
`Secomm_Ghn` trên contracts Phase E-A của `Secomm_ShippingCore` — **báo cáo kiến trúc
14 sections TRƯỚC, 0 production implementation**. TL/SA review capability-contract
adaptation trước khi coding.

### Expected Behavior

1. Report `.ai/research/SPIKE-9Z231Q-secomm-ghn-canonical-architecture.md` đúng 14 sections:
   legacy inventory → GHN API inventory → address representation matrix → reuse/discard
   matrix → module structure → capability decision → provider mapping model → rate/service
   flow → create-order flow → origin/ShopId flow → tracking flow → transition plan →
   phases GHN-A..F → risks/open decisions.
2. Address representation per-OPERATION (không hardcode global): Available Services
   (district-based) vs Calculate Fee (`to_district_id`+`to_ward_code`) vs Create Order
   (NAME-based + `is_new_to_address` flag 2-level/3-level) vs Origin (ShopId) — evidence
   từng endpoint.
3. Capability contract: đánh giá `CarrierAddressCapabilityInterface` (E-A) đủ dùng hay
   cần adaptation leanest — mismatch report cho TL/SA, KHÔNG tự đổi contract.
4. Provider mapping keyed `scheme_code + unit_code` (không region_id/city_id identity,
   không hardcode IDs, không first-match, fail closed theo thứ tự direct → translate →
   provider map → throw).
5. Báo blocker: Phase E-B orchestration CHƯA có implementation
   (`ShippingAddressResolutionManagerInterface` không có impl) — Secomm_Ghn design target
   E-A contracts, không temporary legacy resolution path.

### Constraints / Rules

- Legacy modules reference-only: KHÔNG refactor/rename/copy wholesale; KHÔNG sửa code
  trong SPIKE này.
- Banned: region_id/city_id làm mapping identity; name-based identity; hardcoded
  1456/21511/…; first-match; silent fallback; direct VietMap/Google dependency; scheme
  conversion trong Secomm_Ghn; duplicate canonical tables (VN unit data thuộc
  VietNamAddress).
- Không infer undocumented GHN request fields — chỉ dùng docs đã verify; ghi rõ gap.
- Đội tracking pipeline ShippingCore (DEC-TASK86NX9T-001); origin qua
  OriginProviderInterface (DEC-TASKNDASAD-001); không generalize cho carrier thứ 2
  (DEC-004 D10) — chỉ ghi extension direction.
- Tier-2 areas (shipping/order/db_schema): mọi thứ chỉ là ĐỀ XUẤT trong report.

### Out of Scope

Production code Secomm_Ghn (sau review) · E-B implementation · E-C data migration thực
 thi · GHTK/Ahamave refactor · carrier enablement trên store thật · admin UI mapping mới
 · xóa legacy module (cutover riêng GHN-F).

### Acceptance Criteria

- **AC-1**: Report 14 sections đầy đủ, mỗi claim về GHN API có URL nguồn; mỗi claim về
  legacy có file:line.
- **AC-2**: Capability decision tường minh: generic contract đủ/không đủ + leanest
  adaptation + lý do + danh sách TL/SA review items.
- **AC-3**: Provider mapping model keyed scheme_code+unit_code, resolution order
  direct→translate→map→fail-closed, không banned pattern nào trong thiết kế.
- **AC-4**: Create-order flow decision dựa trên documented contract thật (NAME-based +
  `is_new_to_address`), gap documentation được ghi nhận KHÔNG bị infer.
- **AC-5**: Transition/cutover plan tường minh về coexistence (carrier code, config path,
  DI, events, queue topics, routes, DB, method registration) + data migration KHÔNG
  migrate mù region_id/city_id.
- **AC-6**: SPIKE record + report + validator pass; evidence `.ai/evidence/SPIKE-9Z231Q/`;
  CURRENT_STATE.md cập nhật; 11-point completion report.

## Findings (summary — full report trong `.ai/research/SPIKE-9Z231Q-secomm-ghn-canonical-architecture.md`)

**Clarification TL/SA 2026-09-08 (v3) — exact old ward**: TL/SA **verified trực tiếp từ GHN
data/runtime**: GHN operational flow yêu cầu **exact old ward** — old province + old district +
current ward/name KHÔNG đủ. Rejected: same-district optimization + district-granular sufficient
+ old p/d + current ward(/name). Recommendation chốt **A — KEEP FULL PRE_2025 RESOLUTION**;
E-B v2 (pool invocation) MANDATORY; primary KPI `FULL_PRE_2025_ADDRESS_RESOLVED`;
NO_MATCH taxonomy: DATA_GAP 9 / STRUCTURAL_ADMIN_CHANGE 11 / UNKNOWN 18 (script
`classify_no_match.php`). External resolver = operationally required cho GHN khi internal
resolution không tìm được exact historical ward — `Secomm_VietMap` preferred candidate.

**Clarification v2 (cùng ngày)**: GHN operational = old-style province/district/ward cho fee +
create; PRE_2025 là intermediate scheme carrier-required; hai lớp identity tách biệt (PRE_2025 =
Secomm canonical historical; GHN p/d/w = provider-specific); không coi GHN identifiers là
identity PRE_2025; architecture "2025 → GHN current province + virtual district → current ward"
= **NOT SELECTED** (evidence giữ ở §16 report).

1. **Semantics `getRequiredScheme()` CONFIRMED** = carrier-required canonical scheme TRƯỚC
   provider mapping ⇒ GHN = `VN_ADMIN_PRE_2025`. Docblock hiện tại đủ rõ — không gap,
   không sửa contract.
2. **GHN API hiện hành transition state** (docs captured 2026-09-08): services + fee = ID
   old-style; create docs NAME-based + `is_new_to_address` (chuyển thành rejected alternative
   theo clarification); `service_id` chỉ tham chiếu (`service_type_id` 2/5); webhook PascalCase
   + dedup; Cancel batch all-or-nothing; Update COD cần OTP.
3. **Reverse mapping audit 2025→PRE_2025** (script evidence, read-only): ward-level
   ONE_TO_ONE **186 (5.60%)** · ONE_TO_MANY **3.097 (93.26%)** (same-district 2.832 /
   cross-district 265) · NO_MATCH **38 (1.14%)** · INVALID **0** · 0 cross-province merges.
   **v3 KPI: `FULL_PRE_2025_ADDRESS_RESOLVED` = 5.60%** (chỉ ONE_TO_ONE resolve trực tiếp —
   exact old ward required; same-district vẫn AMBIGUOUS; 90.88% district-granular chỉ là
   supporting statistic). NO_MATCH taxonomy: DATA_GAP 9 / STRUCTURAL 11 / UNKNOWN 18.
4. **E-B v2 MANDATORY** (priority blocker): internal deterministic (ward-level) → AMBIGUOUS/
   UNMAPPED ⇒ invoke ExternalAddressResolverPool → external unresolved ⇒ fail closed; no
   first-match / no random candidate / no district-only success. Impl v1 có sẵn (TASK-5XDG1P,
   chờ TL review) nhưng pool chưa invoke. Result-contract review: 4-status + exception ĐỦ cho
   semantics (RESOLVED_FULL↔EXACT/MAPPED, UNSUPPORTED↔exception); gap informational
   EXTERNAL_RESOLVED/EXTERNAL_FAILED → flag follow-up assess task, KHÔNG đổi contract trong
   spike. **GHN-C blocked** tới khi full PRE_2025 resolution path operational.
5. **VietMap** = optional external resolver đằng sau `ExternalAddressResolverInterface`
   (pool) — dependency `VietMap → ShippingCore contract`; KHÔNG `Secomm_Ghn → VietMap`;
   separate bridge task.
6. Provider mapping v2: keyed `(scheme_code=PRE_2025, unit_code)`; naming
   `GHN_OPERATIONAL_ADDRESS`/`GHN_PROVINCE_ID`/`GHN_DISTRICT_ID`/`GHN_WARD_CODE` (bỏ
   GHN_LEGACY/GHN_NEW); `provider_code` bắt buộc nếu promote shared table; Secomm_Ghn
   KHÔNG tự translate (chain E-B làm reverse trước).

Trạng thái: **PENDING TL/SA REVIEW** — không production code (v1 + v2 + v3 đều 0 code).
Recommendation chốt v3: **A — KEEP FULL PRE_2025 RESOLUTION**.
Review items: §6 (semantics + E-B v2 + result contract), §8 (availability), §15 (audit + KPI +
NO_MATCH taxonomy), §16 (rejected alternatives v3), §17 (empirical Q1-Q6 v3), §13 phases v3,
§14 R1-R28.
