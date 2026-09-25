---
id: DEC-TASKWY6WP5-001
title: 'Shipping Coverage target abstraction — CoverageTargetRegistry thay CarrierRegistry, CarrierCoverageConfigAdapter trên config paths FROZEN, IA rename "Shipping Coverage" (ACL/routes ổn định), zone form geography-only, selector rebuild core ui-select'
status: accepted             # TL directive 2026-09-23 + user approval 2 điểm (menu 3 cấp, implement Reset) — Tier-2 review trước merge
owners: [tl, sa]
decision_type: architecture
approval_date: 2026-09-23
created: 2026-09-23
last_verified: 2026-09-23
verified_against_commit:
supersedes: []
superseded_by:
work_items: [TASK-WY6WP5, FEAT-QA23PZ]
---

# Decision Record: Shipping Coverage P1 Admin UX — CoverageTarget abstraction & admin rework

## Status

Accepted (2026-09-23 — TL directive 36 section; user chốt menu 3 cấp + implement Reset).
Triển khai trong TASK-WY6WP5 trên nền FEAT-QA23PZ/TASK-G3K9V2 (working tree). Runtime
ShippingCore v10 + coverage semantics + GHN v10 FROZEN — decision này CHỈ đụng config/
admin layer. Tier-2 (CTO/SA) review toàn bộ change set trước merge.

## Decision Type

Architecture (config-layer abstraction — KHÔNG đổi runtime contracts, KHÔNG tạo v11)

## Decisions

1. **CoverageTarget abstraction (config layer)** — `Model\CoverageTarget\{CoverageTargetType,
   CoverageTargetIdentity, CoverageTarget}` + `CoverageTargetRegistry` (DI opt-in array
   `{type, code, label}`; thiếu type → CARRIER). P1: chỉ type `CARRIER` được produce;
   `METHOD` là reserved constant (no admin surface, no runtime consumer). Registry entry
   chỉ nghĩa là "target hỗ trợ cấu hình coverage" — **registration ≠ persisted coverage**;
   không auto-create; không config → runtime defaults (`destination_scope` missing → ALL).

2. **Adapter boundary** — `PolicyConfig` → `CarrierCoverageConfigAdapter` (API nhận
   `CoverageTargetIdentity`); config paths `carriers/<code>/{destination_scope,
   allowed_zone_codes, rate_source_mode, address_resolution_policy}` giữ byte-identical
   (zero migration); DEFAULT scope P1; `hasExplicitConfig()` đọc thẳng `core_config_data`
   MỌI scope (consistent với CarrierZoneIndex) — scoped rows = Configured.

3. **IA + lifecycle** — menu `Secomm → Shipping → [Shipping Zones, Shipping Coverage]`
   (node cha "Shipping" — user approval 2026-09-23, amend placement flat của
   DEC-FEATQA23PZ-001 §5); rename user-facing "Carrier Coverage" → "Shipping Coverage";
   ACL ids `Secomm_ShippingCore::carrier_coverage[_manage]` + route
   `secomm_shippingcore/coverage/*` GIỮ NGUYÊN. Shipping Coverage là bề mặt edit DUY
   NHẤT cho target↔zone mapping; listing rows = registered targets × persisted state;
   lifecycle Add/Configure → Edit → Reset to Defaults (xoá 4 rows DEFAULT; scoped rows
   còn lại → warning; target vẫn registered).

4. **Zone form geography-only** — BỎ "Carriers Referencing This Zone" (`assigned_carriers`
   + `CarrierOptions`); CarrierZoneIndex + ZoneReferenceGuard giữ nguyên (internal delete/
   mass-delete/disable protection all-scope).

5. **Selector rebuild trên core `ui-select`** — recipe `new_category_form.xml`
   (`component` attribute + `elementTmpl ui/grid/filters/elements/ui-select`); Included
   Wards qua subclass `ward-select` (cascade AJAX + prune, port từ component cũ); XOÁ
   `searchable-multiselect.js` + KO template (chưa từng render đúng trong browser —
   component approach bị thay, không debug mù). Không lib mới.

## Consequences

- Future METHOD targets: đăng ký registry + adapter tương ứng là đủ seams; KHÔNG implement
  METHOD execution ở P1; method-inheritance rule (method policy overrides carrier policy;
  mặc định inherit) chỉ document — reopen khi có consumer thật.
- GHTK KHÔNG tự register (opt-in per directive §27); ShippingCore generic, không hardcode
  GHN; GHN chỉ thêm registry metadata + text pointer (runtime freeze intact).
- Selector render defect root-cause client-side được đóng bằng cách thay approach (không
  root-cause chi tiết) — chấp nhận vì component custom chưa từng verify và core recipe
  proven; browser smoke là gate trước READY.
