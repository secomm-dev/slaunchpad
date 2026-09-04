---
id: DEC-FEATYA2C0W-003
title: 'Versioned VN admin scheme identities (VN_ADMIN_*) + scheme registry + historical unit layer + mapping/resolution layer'
status: accepted             # approved via plan 2026-08-27 (user acting as SA/TL)
owners: [sa, tl]
decision_type: architecture
approval_date: 2026-08-27
created: 2026-08-27
last_verified: 2026-08-27
verified_against_commit:
supersedes: [DEC-FEATYA2C0W-002]
superseded_by:
work_items: [FEAT-YA2C0W, TASK-ADT94K, TASK-9394A9, TASK-J9AVGK]
---

# Decision Record: Versioned VN administrative schemes (VN_ADMIN_*)

<!-- CANONICAL DECISION STORE. ACCEPTED 2026-08-27 (user acting as SA/TL, plan approved). -->
<!-- Index pointer: `.ai/project-context/memory/DECISIONS.md` -->
<!-- Supersede PHẦN NAMING của DEC-FEATYA2C0W-002 (scheme codes vn_current/vn_legacy, files VNC/VNL, profile codes).
     SWAP MODEL của DEC-002 GIỮ NGUYÊN (một scheme active trong runtime/lần). -->

## Context

Yêu cầu 2026-08-27 (32 mục): cải cách hành chính VN lặp lại theo thời gian; carrier hỗ trợ các phiên bản hành chính khác nhau. `VN_CURRENT`/`VN_LEGACY` là khái niệm tương đối — sai ngay khi có cải cách tiếp theo. Carrier phải không tự convert old/new; mọi translation tập trung. Datasets mới `VN_ADMIN_2025` (2 cấp) + `VN_ADMIN_PRE_2025` (3 cấp) đã cung cấp (7 cột tự chứa tên region, codes `VNA25-`/`VNAP25-` immutable).

## Decision

1. **Scheme identity versioned bất biến**: `VN_ADMIN_2025`, `VN_ADMIN_PRE_2025` (future `VN_ADMIN_2027` = thêm catalog entry + files + profile XML, không viết lại carrier). `CURRENT`/`HISTORICAL`/`FUTURE` là STATUS (label trong registry), không bao giờ là identity. Profile codes: `vn_admin_2025` / `vn_admin_pre_2025` (rename từ vn_current/vn_legacy — alias map cho upgrade không purge).
2. **Runtime giữ swap model** (DEC-002): `directory_country_region` + `directory_region_city` chứa MỘT scheme; `directory_country_region` không thêm cột; hierarchy = `parent_city_id` generic.
3. **Config**: `secomm_vietnam_address/general/active_scheme` (default scope, giá trị = scheme code); importer ghi SAU import thành công; admin UI chỉ cho chọn scheme đã cài (backend model guard); generic `address/profiles/mapping` được đồng bộ theo active_scheme.
4. **Historical reference layer** (knowledge không mất sau swap): `secomm_vietnam_address_scheme` (registry: scheme_code PK, label, profile_code, level_count, status, effective_from/to) + `secomm_vietnam_address_unit` (scheme_code, code, parent_code, region_code, level, name_vi, name_en; UNIQUE(scheme_code, code); KHÔNG FK runtime — portable; region rows lưu level 1). Import MỖI scheme đều ghi tích lũy vào đây.
5. **Mapping layer** (tách khỏi runtime): `secomm_vietnam_address_mapping` — directed edges (source_scheme, source_code, target_scheme, target_code, relation_type ∈ SAME_AS|RENAMED_TO|MERGED_INTO|SPLIT_INTO); UNIQUE edge; KHÔNG FK (CLI validate vs unit table). Không thêm legacy_code/current_code/… lên `directory_region_city`.
6. **Directional resolution**: `VnAdminAddressResolverInterface::resolve(sourceScheme, sourceCode, targetScheme)` → status `EXACT | MAPPED | AMBIGUOUS | UNMAPPED` (+ reason, candidateCodes sorted, relationType). Candidates = union outgoing + incoming edges (reverse-merge hoạt động từ single-direction authored rows). AMBIGUOUS KHÔNG BAO GIỜ auto-pick.
7. **Unit codes là internal Secomm identity**: không phải government/carrier code, không expose customer, không derive runtime từ tên, immutable một khi publish; không parse scheme từ code prefix — scheme luôn từ context/config.
8. **CSV canonical 7 cột** `region_code,region_name_vi,region_name_en,code,parent_code,name_vi,name_en` (5 cột không-region-name được chấp nhận khi có nguồn region khác — hiện không dùng); KHÔNG chứa city_id/region_id DB-sinh.

## Design notes — phases sau (KHÔNG implement đợt này)

- **Phase E (ShippingCore)**: `CarrierAddressCapabilityInterface` (carrier declare preferred_scheme + hierarchy + external-id + fallback policy) + resolution orchestration: unit code (snapshot/runtime) → `VnAdminAddressResolverInterface` → carrier external mapping (key carrier_code+scheme_code+address_code, tách khỏi directory tables) → carrier API. AMBIGUOUS/UNMAPPED không tự chế — carrier tự fallback (vd Ahamove geocode text). GHN migrate trước (`secomm_ghn_address_mapping_location` join runtime lấy code lúc migrate). Extension point `AddressDisambiguationStrategyInterface` (init: return AMBIGUOUS).
- **Phase F**: GHTK (`secomm_ghtk_address_map`, `WardIdBridge`) chuyển key scheme-code; Ahamove free-text không đổi.
- **§23 persisted-address snapshot**: quote/sales/customer address thêm `vn_scheme_code` + `vn_unit_code` (+optional parent unit code), capture lúc save từ unit selection của schema renderer (hiện post `address_city_id` chưa được PHP đọc). Snapshot làm địa chỉ swap-proof (re-resolve deterministic qua mapper thay vì name-match). Follow-up task riêng.

## Alternatives

- **Giữ vn_current/vn_legacy làm identity**: rejected — relative concepts, sai sau cải cách tiếp theo (yêu cầu §2 tường minh).
- **Nhiều scheme cùng tồn tại runtime + membership tách**: rejected (giữ kết luận DEC-002 — swap model đơn giản hơn cho Launchpad).
- **Mapping bằng cột legacy_code/current_code trên directory_region_city**: rejected — không diễn đạt N:M, phá portability (city_id đổi mỗi swap).
- **FK từ mapping/unit sang runtime tables**: rejected — bảng phải portable cross-environment + cross-swap.
- **SUPERSEDED_BY relation type**: deferred — chưa có use case resolution thật; thêm khi cần.

## Consequences

- (+) Cải cách tương lai = thêm dataset + mapping data + flip active_scheme (workflow §27); carrier không đổi trừ khi API contract đổi.
- (+) Historical knowledge sống qua swap; resolution deterministic; ambiguous tường minh.
- (−) Phase B phải rename scheme codes/profiles/CLI values (breaking CLI so với bản TASK-ADT94K chưa release — chấp nhận, chưa deploy).
- (−) Mapping data 2025↔PRE_2025 chưa cung cấp — Phase D ship model + CLI, seed chờ nguồn (như D-R1 cũ).
- (−) Địa chỉ đã lưu vẫn text-only đến khi §23 snapshot task chạy — swap vẫn reset name-matching (pre-launch chấp nhận).

## Verification

- TASK-ADT94K/9394A9/J9AVGK verification trên dev DB: import 2025 → swap PRE → swap ngược; registry status CURRENT/HISTORICAL đúng; unit table tích lũy; resolver 4 statuses (đặc biệt reverse-merge AMBIGUOUS). Evidence `.ai/runtime/evidence/`.
