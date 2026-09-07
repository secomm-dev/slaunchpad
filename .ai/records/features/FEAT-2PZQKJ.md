---
id: FEAT-2PZQKJ
type: feature
project_code: SLP
parent: null
legacy_ids: []
title: 'Generic hierarchical address profiles — refactor Secomm_AddressDropdown to recursive city hierarchy + Address Profile/Schema'
mode: A                      # DB schema migration + checkout-adjacent rendering + API contract → Tier-2
specification_level: FULL
spec_status: VALID
specification_ref: ../../specs/SPEC-FEAT-2PZQKJ-generic-hierarchical-address-profiles.md
risk: high
status: proposed
created: 2026-08-25
updated: 2026-08-25
ticket_ref:
  - TASK-9AEAQQ                   # Phase 1 — schema additive: parent_city_id + code + membership table + whitelist (DB migration, Tier-2)
  - TASK-NW66H9                   # Phase 1 — Address Profile/Schema XML config + DTO + Resolver/SchemaProvider contracts
  - TASK-J49PRZ                   # Phase 1 — LocationHierarchyProvider + GraphQL addressLocations/addressSchema
  - TASK-3T3NSV                   # Phase 2 — Hyva generic schema-driven renderer (flagged) + form-validation port (Tier-2)
  - TASK-4F1K3N                   # Phase 2 — Import v2 multi-level + VietNamAddress re-key (profile vn_current → chuyển FEAT-YA2C0W/TASK-R83FXW, 2026-08-25)
  - TASK-CR4D1V                   # Phase 2 — security/perf fixes trên touch-point sẵn có (SQL binding, CityData cache key, SaveToQuote cast bug)
  - TASK-YQSS3M                   # Phase 2 — carrier alignment: GhnAddressMapper CascadingOptions, GiaoHangNhanh readers, GraphQL shims
  - TASK-9EX975                   # Phase 2 — admin surfaces switch sang schema-driven renderer
  - TASK-K09G8Y                   # Phase 3 — sub_city removal (tables/columns/EAV/observers/KO mixins) — GATED trên prod data scan
  - TASK-ZHFVRH                   # Docs — module map, BR-002, README/CHANGELOG ×2
decisions:
  - DEC-8                    # reusable-module vs project boundary (accepted — vẫn hiệu lực)
  - DEC-FEATJSZQV3-001                  # generic VN address capability lives in Secomm_VietNamAddress (accepted — vẫn hiệu lực)
  - DEC-FEATJSZQV3-003                  # generic dùng Magento-default labels; VN behaviour/labels → VietNamAddress (accepted — vẫn hiệu lực)
  - DEC-TASKYJENM2-001                  # 2-level VN: ward = native city, canonical city_id (accepted — vẫn hiệu lực cho VN)
  - DEC-FEATE2HM1J-001                  # sub_city = generic 3rd level cố định (SUPERSEDED bởi DEC-FEAT2PZQKJ-001)
  - DEC-FEAT2PZQKJ-001                  # recursive city hierarchy (parent_city_id) + Address Profile/Schema (ACCEPTED 2026-08-25 — D2 location_path / D4 XML / D5 subtree / D6 code)
decision_assessment: material
decision_refs: [DEC-8, DEC-FEATJSZQV3-001, DEC-FEATJSZQV3-003, DEC-TASKYJENM2-001, DEC-FEATE2HM1J-001, DEC-FEAT2PZQKJ-001]
decision_approval_summary:
  total: 6
  pending_approval: []
  approved: [DEC-8, DEC-FEATJSZQV3-001, DEC-FEATJSZQV3-003, DEC-TASKYJENM2-001, DEC-FEATE2HM1J-001, DEC-FEAT2PZQKJ-001]   # FEATE2HM1J-001 status vẫn accepted; point 3 bị thay thế — xem superseded_by trên DEC
  rejected: []
  superseded: []
  last_synced: 2026-08-25
verified_against_commit:
components:
  - CMP-ADDR                 # Secomm_AddressDropdown — generic recursive directory + profile/schema engine (country-agnostic)
  - CMP-VNADDR               # Secomm_VietNamAddress — VN adapter: vn_current/vn_legacy profiles, import, validation
  - CMP-GHNMAPPING           # Secomm_GhnAddressMapper — consumer city_id (FK), cần rời sub_city table query
  - CMP-GHN                  # Secomm_GiaoHangNhanh — đọc sub_city/city_id từ quote/order address
  - CMP-GHTK                 # Secomm_Ghtk — WardIdBridge đọc directory_region_city
source_areas:
  - app/code/Secomm/AddressDropdown/
  - app/code/Secomm/VietNamAddress/
  - app/code/Secomm/GhnAddressMapper/Controller/Adminhtml/Ajax/CascadingOptions.php
  - app/code/Secomm/GiaoHangNhanh/Model/Service/Request/
  - app/code/Secomm/Ghtk/Model/Address/
changes_project_state: true
changes_architecture: true   # recursive hierarchy thay fixed city→sub_city; Address Profile/Schema layer mới
changes_integration: true    # GraphQL surface mới + shim; carrier modules alignment
changes_known_limitations: true
last_verified: 2026-08-25
supersedes: []
---

# [SLP][FEAT-2PZQKJ] Generic hierarchical address profiles — refactor Secomm_AddressDropdown to recursive city hierarchy + Address Profile/Schema

<!-- CANONICAL RECORD — refactor lớn CMP-ADDR: bỏ fixed city→sub_city, chuyển sang recursive `directory_region_city.parent_city_id`
     + Address Profile/Schema. Decomposes thành TASK-9AEAQQ…TASK-ZHFVRH theo 3 phase incremental. -->
<!-- Gate: DEC-FEAT2PZQKJ-001 ACCEPTED 2026-08-25 (user acting as SA/TL) — gate mở; sub-ticket có thể kích hoạt theo thứ tự plan. -->

## Context

`Secomm_AddressDropdown` hiện hardcode 2 cấp dưới region: `directory_region_city` → `directory_city_sub_city`. Architecture review 2026-08-25 (baseline audit trước refactor — xem Spec §Findings) xác định:

1. **Chiều sâu cố định** không diễn đạt được 3+ cấp (VN legacy region→district→ward, country khác), và implementation đã lệch DEC-FEATE2HM1J-001 ("render mấy tầng data có mấy tầng" — thực tế tối đa 2).
2. **Label gắn cứng vào cấp vật lý** ("City"/"Sub-City" trong generic template) — cùng cột `city` lúc là city (AU) lúc là ward (VN); phải vá label bằng i18n locale lạ `en_VN`.
3. **Identity theo tên, không theo ID**: address lưu `default_name` string; GraphQL lookup theo tên; carrier modules (GHN/GHTK) cần canonical `city_id` nhưng address không lưu.
4. **[BLOCK] Security**: SQL string interpolation tại `Helper/Address.php:77,163`, `Helper/Data.php:95`.
5. **DB thực tế (local `fashion_launchpad`)**: `directory_city_sub_city*` = **0 rows**; `sub_city` column: 0 customer / 3 quote (test) / 0 order — rủi ro dồn vào **code dependency**, không phải data (bằng chứng chỉ từ local dev — prod scan là gate của TASK-K09G8Y).

**Target** (theo DEC-FEAT2PZQKJ-001 — proposed): bỏ fixed `city→sub_city`, chuyển `directory_region_city` thành hierarchy đệ quy qua `parent_city_id`; thêm layer **Address Profile** (XML-declared) → **Address Schema** (region + city depth 1..n, label/placeholder/required/sort_order) → **dataset membership** (`secomm_address_profile_location`, subtree-claim + inheritance); service contracts `AddressProfileResolverInterface` / `AddressSchemaProviderInterface` / `LocationHierarchyProviderInterface`; renderer Hyvä generic schema-driven. Country-specific (VN_CURRENT/VN_LEGACY, ward mapping, GHN/GHTK IDs) sống ngoài generic module — **vi phạm này là kiến trúc đã đúng một phần** (VietNamAddress + carrier modules đã tách), refactor hoàn tất phần còn leak.

**Risk:** Tier-2 (DB schema migration — TASK-9AEAQQ/K09G8Y; checkout/customer form — TASK-3T3NSV/9EX975; §12 CMP-ADDR overlay) → Mode A toàn feature.

## Architecture (DEC-FEAT2PZQKJ-001 — accepted 2026-08-25)

```text
directory_country_region (core, giữ nguyên — root)
        ↓
directory_region_city (+ parent_city_id NULL, + code)   ← recursive, ID-canonical
        ↓ membership (secomm_address_profile_location — subtree-claim + inheritance)
Address Profile (etc/address_profiles.xml — module-shipped, country modules tự đăng ký)
        ↓
Address Schema (region | city depth 1..n; label/placeholder/required/sort_order)
        ↓
Service contracts → Generic renderer (Hyva Alpine) → OSC / Admin / Cart / Carriers / GraphQL
```

- **Không** domain concept country-specific (district/ward/commune/province…) trong generic module — label/level behavior 100% từ resolved schema; KHÔNG BAO GIỜ suy label từ depth.
- **Canonical identity** giữ `directory_country_region.region_id` + `directory_region_city.city_id` (FK GhnAddressMapper không vỡ).
- **Persist policy depth>2** (open decision D2 trong DEC): đề xuất leaf → native `city` + full path → extension attribute mới (`location_path`); chốt trong DEC trước TASK-9AEAQQ.
- **Incremental 3 phase**: (1) additive + flag, (2) cutover + re-key + alignment, (3) removal gated trên prod data scan. Chi tiết: implementation plan.

## Requirements (feature-level; AC chi tiết trong sub-ticket + Full Spec)

- **AC-001:** `directory_region_city` hỗ trợ nesting qua `parent_city_id` (NULL = root dưới region) + `code` identifier; mọi data hiện tại (3.313 VN wards) = root — không đổi hành vi sau Phase 1.
- **AC-002:** Address Profile XML + Schema hợp lệ: mỗi profile định nghĩa levels (`entity_type` ∈ {region, city}, `depth`, `label`, `placeholder`, `sort_order`, `required`); label dịch qua i18n của module khai báo profile.
- **AC-003:** Profile resolution `resolveProfile(countryId, context)` từ config country→default profile; không có rules engine; country không có profile → native Magento behavior.
- **AC-004:** Dataset membership kiểm soát node khả dụng theo profile (subtree-claim + inheritance); cùng country có thể có nhiều representation (current/legacy).
- **AC-005:** GraphQL mới `addressLocations` / `addressSchema` theo profile; `GetListCity`/`GetListSubCity` thành BC shim.
- **AC-006:** Renderer Hyva generic schema-driven (không `if country == 'VN'`, không label-from-depth); label/placeholder/level behavior từ schema; dừng ở profile-defined leaf (hết schema HOẶC node không còn children trong profile).
- **AC-007:** Generic module không chứa thuật ngữ/behavior country-specific (audit như TASK-KCBDDT precedent); VN labels/logic chỉ ở VietNamAddress.
- **AC-008:** Carrier modules (GhnAddressMapper, GiaoHangNhanh, Ghtk) hoạt động không đổi trên canonical `city_id`; không query trực tiếp `directory_city_sub_city` sau Phase 2.
- **AC-009:** Security fixes: mọi SQL parameterized; CityData cache key theo locale/store hoặc section bị thay thế.
- **AC-010:** Regression: storefront (customer form, cart estimate, OSC), admin forms, import/export, GHN/GHTK rate/sync nguyên vẹn sau mỗi phase (QC L3 cho checkout).

## Sub-ticket breakdown + readiness

| Task | Phase | Mode | Risk | Ghi chú |
|---|---|---|---|---|
| [TASK-9AEAQQ](../tasks/TASK-9AEAQQ.md) | 1 | A | high | schema additive + whitelist; **DB migration Tier-2** |
| [TASK-NW66H9](../tasks/TASK-NW66H9.md) | 1 | A | medium | Profile/Schema XML + DTO + 2 contracts đầu |
| [TASK-J49PRZ](../tasks/TASK-J49PRZ.md) | 1 | A | medium | LocationHierarchyProvider + GraphQL mới |
| [TASK-3T3NSV](../tasks/TASK-3T3NSV.md) | 2 | A | high | Hyva renderer behind flag; port form-validation; **checkout QC L3** |
| [TASK-4F1K3N](../tasks/TASK-4F1K3N.md) | 2 | B | medium | import v2 dual-format + re-key (profile `vn_current` → FEAT-YA2C0W) |
| [TASK-CR4D1V](../tasks/TASK-CR4D1V.md) | 2 | B | medium | SQL binding + cache key + cast bug (touch-point Phase 2) |
| [TASK-YQSS3M](../tasks/TASK-YQSS3M.md) | 2 | B | medium | GhnAddressMapper/GiaoHangNhanh/GraphQL shim alignment |
| [TASK-9EX975](../tasks/TASK-9EX975.md) | 2 | A | medium | admin surfaces (order form, config city, MSI source) schema-driven |
| [TASK-K09G8Y](../tasks/TASK-K09G8Y.md) | 3 | A | high | sub_city removal — **GATE: prod/staging data scan sạch + Tier-2 sign-off riêng** |
| [TASK-ZHFVRH](../tasks/TASK-ZHFVRH.md) | docs | C | low | module map 09, BR-002, README/CHANGELOG ×2 |

Tất cả `proposed`. Không task nào start trước khi DEC-FEAT2PZQKJ-001 được accept (đặc biệt D1 supersede + D2 persist policy).

## Risks

- **R1 Checkout Tier-2**: renderer đổi chạm OSC flow (QC end-to-end + payment test bắt buộc — AGENTS §7.1).
- **R2 Prod data unknown**: bằng chứng sub_city rỗng chỉ từ local dev; TASK-K09G8Y gate trên prod scan.
- **R3 Template fork 574 dòng bị thay**: phải port đủ form-validation (postcode rule, VAT, street lines) — regression risk cao.
- **R4 Membership query perf**: subtree-claim + inheritance trên 14k nodes cần index + test perf.
- **R5 GhnAddressMapper FK**: `city_id` vẫn canonical nên FK an toàn, nhưng CascadingOptions còn query sub_city table — fix trong TASK-YQSS3M.
- **R6 GiaoHangNhanh đọc `sub_city` attribute**: persist policy D2 quyết định nguồn thay thế — chốt trước Phase 2.

## References

- Full Spec: [SPEC-FEAT-2PZQKJ-generic-hierarchical-address-profiles](../../specs/SPEC-FEAT-2PZQKJ-generic-hierarchical-address-profiles.md)
- Plan: [FEAT-2PZQKJ-implementation-plan](../../plans/FEAT-2PZQKJ-implementation-plan.md)
- Architecture decision: [DEC-FEAT2PZQKJ-001](../decisions/DEC-FEAT2PZQKJ-001.md) (accepted 2026-08-25) — supersedes [DEC-FEATE2HM1J-001](DEC-FEATE2HM1J-001.md)
- Còn hiệu lực: [DEC-8](DEC-008.md) · [DEC-FEATJSZQV3-001](DEC-FEATJSZQV3-001.md) · [DEC-FEATJSZQV3-003](DEC-FEATJSZQV3-003.md) · [DEC-TASKYJENM2-001](DEC-TASKYJENM2-001.md)
- Dependency map nguồn review: GhnAddressMapper (FK `GHN_ADDR_MAP_CITY_ID_DIR_REGION_CITY_CITY_ID`), GiaoHangNhanh DataBuilders, Ghtk WardIdBridge, VietNamAddress (4 GraphQL callers + import patch)
- Precedent: [FEAT-E2HM1J](FEAT-E2HM1J.md) (admin surfaces) · [TASK-KCBDDT](../../tickets/TASK-KCBDDT-generalize-addressdropdown-remove-vn-leak.md) (de-VN cleanup) · [TASK-3F6QWZ](../tasks/TASK-3F6QWZ.md) (carrier modules)
- Risk tier: AGENTS.md §9/§12 (DB schema, checkout, customer/PII, CMP-ADDR overlay L421)
