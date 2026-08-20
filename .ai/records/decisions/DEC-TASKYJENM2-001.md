---
id: DEC-TASKYJENM2-001
legacy_ids: [DEC-020]
title: GHTK address mapping canonical key = (country_id, region_id, ward_id) on stable city_id (directory_region_city — VN 2-level ward) — ward_name is display/import only; vi_VN fallback is best-effort, NOT "API always works"
status: accepted
owners: [sa, tl]
decision_type: schema
approval_date: 2026-07-30
created: 2026-07-30
last_verified: 2026-07-30
verified_against_commit:
supersedes: []
superseded_by:
work_items: [TASK-YJENM2, FEAT-AE761Z]
---

# Decision Record: GHTK mapping canonical key + fallback semantics

<!-- CANONICAL DECISION STORE (Phase 1a / RM-01). ACCEPTED 2026-07-30 — approved by user acting as SA/TL. -->
<!-- Index pointer: `.ai/project-context/memory/DECISIONS.md` -->
<!-- Supersedes the earlier "(region_id, ward_name) key + fallback đảm bảo API luôn hoạt động" assumption in FEAT-AE761Z / TASK-YJENM2. -->
<!-- Audit 2026-07-30 verified against app/code/Secomm/AddressDropdown/etc/db_schema.xml + Model/Resolver/GetListSubCityGraphql.php. -->
<!-- CORRECTION 2026-07-31 (user): VN 2-level model → "ward" (phường/xã) = the CITY level, so ward_id = directory_region_city.city_id (NOT directory_city_sub_city.sub_city_id, which is the deprecated 3rd level). FEAT-JSZQV3 U3 confirms: GetListCity(region_id) sources the Ward. ward name (vi_VN) = directory_region_city_name.name. -->
<!-- ward_id path: B chosen (lean default) — GHTK-internal name→city_id bridge (resolve city_id from directory_region_city by region_id + default_name); path A (expose city_id in generic module) is long-term follow-up. -->

## Context

FEAT-AE761Z (GHTK carrier) ban đầu định nghĩa mapping key `(region_id, ward_name)` và mô tả fallback vi_VN là "đảm bảo API luôn hoạt động". Hai vấn đề sau được phát hiện khi chuẩn bị implementation:

1. **Key dùng `ward_name` không ổn định.** `ward_name` là chuỗi display, có thể trùng giữa các ward, thay đổi theo địa giới hành chính, và lệch giữa master project vs master GHTK. Dùng name làm canonical key → rework khi đổi tên / gộp/chia ward.

2. **Fallback semantics sai.** Fallback vi_VN chỉ tạo dữ liệu **hợp lệ để build request**; nó **không đảm bảo** GHTK nhận diện địa chỉ. API có thể reject vì naming convention, địa giới thay đổi, khu vực không hỗ trợ, hoặc pickup/destination không hợp lệ.

**Audit dữ liệu (2026-07-30, corrected 2026-07-31):**
- **VN 2-level model**: province = `directory_country_region`; ward (phường/xã) = **city level** (`directory_region_city`, PK `city_id`, có `region_id`). Sub-city (`directory_city_sub_city`) là mức 3 cũ → **KHÔNG dùng cho VN 2-level** (FEAT-JSZQV3 U3: `GetListCity` cho Ward). Vậy stable ward identifier = **`directory_region_city.city_id`**.
- **NHƯNG** persistence hiện lưu ward dưới dạng **name string** (native `city` field / `sub_city` varchar) — GraphQL surface (`GetListCity`/`GetListSubCity`) trả name, không trả id. **→ `ward_id` (= `city_id`) KHÔNG có sẵn trực tiếp trên address payload hiện tại** → compatibility/technical-debt gap → path B bridge giải quyết.

Tier-2 (shipping + address data + DB schema — §12) → Level-2 architecture/schema decision, SA/TL.

## Decision (accepted 2026-07-30 — user as SA/TL)

1. **Canonical key = `(country_id, region_id, ward_id)`** cho bảng `secomm_ghtk_address_map`, trong đó `ward_id` = **`directory_region_city.city_id`** (stable PK của mức city = ward trong VN 2-level model, data layer `Secomm_AddressDropdown`). `UNIQUE(country_id, region_id, ward_id)`. *(Corrected 2026-07-31: ward_id re-based từ `sub_city_id` sang `city_id` theo 2-level model.)*
2. **`ward_name` chỉ dùng cho display / audit / CSV import support** — KHÔNG phải canonical identifier. `source_province_name` + `source_ward_name` lưu trong bảng mapping để trace + import, không dùng làm join key.
3. **Mọi resolver ưu tiên stable ID.** Name-normalized fallback chỉ dùng khi request cũ KHÔNG có `ward_id` (legacy/compatibility path).
4. **Fallback semantics sửa thành:** mapping miss → fallback sang dữ liệu tên vi_VN chuẩn để build **best-effort request**. Fallback chỉ đảm bảo **có dữ liệu hợp lệ để build request**; **không đảm bảo** GHTK nhận diện địa chỉ thành công. API có thể reject. Mapping/API failure phải **graceful**: không crash cart/checkout; không trả rate nếu GHTK không phục vụ; log đủ dữ liệu đã mask để bổ sung mapping.
5. **ward_id availability gap — RESOLVED (path B, accepted 2026-07-30):** chọn **path B — GHTK-internal name→`city_id` bridge** (`WardIdBridge` trong `Secomm_Ghtk` resolve `city_id` từ `directory_region_city` theo `(region_id, default_name)` tại lookup; không đổi generic module → tôn trọng DEC-FEATJSZQV3-003/DEC-8). Path B là compatibility/technical-debt tạm; **path A (expose + persist `city_id` ở address data layer)** ghi là long-term follow-up (ticket riêng khi decide). Bridge phải deterministic (name trong region đủ định danh).

## Alternatives

- **Giữ `(region_id, ward_name)` làm key** — rejected: name không stable (trùng tên, đổi tên, địa giới); sẽ rework.
- **Dùng name-normalized hash làm key** — rejected: vẫn phụ thuộc name; chỉ xấp xỉ stable.
- **Giữ mô tả "fallback đảm bảo API luôn hoạt động"** — rejected: sai semantics; gây kỳ vọng sai, che giấu mapping gap.

## Consequences

- (+) Key stable theo data layer; không rework khi đổi tên ward.
- (+) Fallback semantics chính xác → QC/log/monitoring phản ánh đúng failure mode.
- (+) Path B không chạm generic module → TASK-YJENM2 unblocked (→ ready).
- (−) Path B = tech-debt tạm (bridge name→`city_id`); path A (long-term) sinh ticket generic module (DEC-FEATJSZQV3-003 boundary).
- Follow-up: tracking audit timestamp + admin identity khi import; không duplicate VN master data trong `Secomm_Ghtk`.

## Affected components

- `CMP-GHTK` — `Secomm_Ghtk` (bảng `secomm_ghtk_address_map`, resolver).
- `CMP-ADDR` — `Secomm_AddressDropdown` (chỉ nếu path A: expose `city_id` = ward_id; reuse data, KHÔNG sửa cho carrier-specific mapping).
- Sub-tickets: [TASK-YJENM2](../../tickets/TASK-YJENM2-ghtk-address-mapping.md).

## Related records

- Features: [FEAT-AE761Z](../features/FEAT-AE761Z.md) · [FEAT-JSZQV3](../features/FEAT-JSZQV3.md)
- Decisions: [DEC-FEATJSZQV3-002](DEC-FEATJSZQV3-002.md) · [DEC-FEATJSZQV3-003](DEC-FEATJSZQV3-003.md) (generic vs carrier ownership) · DEC-8 (boundary)
- DECISIONS.md index: DEC-TASKYJENM2-001
