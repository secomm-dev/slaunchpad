# Feature Spec: Vietnam current/legacy address profiles, relations + resolver

Specification ID: SPEC-FEAT-YA2C0W

> Filename: `SPEC-FEAT-YA2C0W-vietnam-current-legacy-address.md` — naming per `rules/spec-first.md` §Spec Naming.

## Metadata

| Field | Value |
|-------|-------|
| Specification ID | SPEC-FEAT-YA2C0W |
| Feature ID | FEAT-YA2C0W |
| Specification Level | FULL |
| Author | Claude (AI-assisted draft) — từ review 14 mục 2026-08-25 |
| Status | **Approved** — user acting as SA/TL, 2026-08-25 (kèm DEC-FEATYA2C0W-001 accepted) |
| Date | 2026-08-25 |
| Related Ticket(s) | TASK-R83FXW · TASK-ADT94K · TASK-X0XKH4 · TASK-AP6YXP · TASK-7RK8Q3 · TASK-S0M7YC |
| Workflow Mode | A |
| Depends on | FEAT-2PZQKJ: TASK-NW66H9 (done — profile engine), TASK-4F1K3N (import v2 — pending, gate cho TASK-ADT94K), TASK-J49PRZ (provider — membership consumer, pending) |

## 1. Objective

Đưa `Secomm_VietnamAddress` từ thin data-adapter lên **country layer hoàn chỉnh** trên nền `Secomm_AddressDropdown` đã refactor: đăng ký 2 profiles (`vn_current` 2-level, `vn_legacy` 3-level) với dataset membership tách biệt, xây relation model current↔legacy chuẩn hóa (canonical IDs), và service resolver deterministic (RESOLVED/AMBIGUOUS/NOT_FOUND) — **foundation cho carrier mapping (GHN/GHTK) ở work items sau**, hoàn toàn độc lập carrier.

## 2. User Stories

### US-001: Carrier module developer (consumer tương lai)
As a developer module carrier, I want một service deterministic để chuyển location hiện hành ↔ legacy, so that tôi map carrier master data cũ mà không tự viết matching logic.

**Acceptance Criteria:**
- [ ] `resolveLegacyLocation(currentLocationId)` với đúng 1 relation → status RESOLVED + resolvedLocationId.
- [ ] Nhiều relations (vd 1 province mới gộp từ nhiều tỉnh cũ) → AMBIGUOUS + đủ candidateLocationIds, KHÔNG auto-chọn.
- [ ] Không relation → NOT_FOUND. Reverse `resolveCurrentLocation` tương tự.
- [ ] Input không thuộc VN / không tồn tại → NOT_FOUND có log, không throw.

### US-002: Store operator (merchant)
As a merchant, I want country VN bind profile hành chính hiện hành làm mặc định, so that storefront render đúng 2-level sau khi renderer schema-driven lên (FEAT-2PZQKJ Phase 2).

**Acceptance Criteria:**
- [ ] Config `address/profiles/mapping` default `VN → vn_current` (patch set, không đè config merchant).
- [ ] `vn_current`/`vn_legacy` schema đúng levels: 2 vs 3 (dưới country), label keys dịch 3 locale.

### US-003: Data maintainer
As a maintainer, I want quy trình cập nhật dữ liệu hành chính = thay CSV + chạy CLI, so that không sửa SQL tay và có validate trước khi ghi.

**Acceptance Criteria:**
- [ ] CLI import relations (code-based refs, orphan check, idempotent upsert) + validate (duplicates/orphans/missing-reverse) + `--suggest` name-assist CHỈ report.
- [ ] Nguồn CSV documented trong header file.

## System Behaviour

**Resolution flow (canonical):**
```text
Input locationId (region_id | city_id)
  → load node + verify country = VN  (không VN / không tồn tại → NOT_FOUND + log)
  → query secomm_vietnam_address_relation theo (source_type, source_id)
  → 0 row → NOT_FOUND | 1 row → RESOLVED (+resolvedLocationId) | ≥2 rows → AMBIGUOUS (đủ candidates)
```

**Membership semantics** (kế thừa DEC-FEAT2PZQKJ-001 D5): profile claim subtree tại region root; node không có entry inherit từ parent. vn_current = 34 region mới; vn_legacy = 63 region prefix `L-`. Hai dataset không giao nhau ở bất kỳ cấp nào.

> **⚠️ SUPERSEDED 2026-08-27 — DEC-FEATYA2C0W-002 (swap model)**: hai dataset KHÔNG còn cùng tồn tại trong DB. DB chứa MỘT scheme/lần (63 region legacy giữ code chính thức, không prefix `L-`); membership chỉ còn "profile active claim toàn bộ region VN" (reseed mỗi lần import/swap). Mục Import workflow + US-001 resolver cũng chịu ảnh hưởng: resolver sau này (TASK-X0XKH4/AP6YXP) thiết kế lại theo hướng **code-based** (tham chiếu code `VNC-/VNL-` ổn định trong dataset, không cần 2 dataset đồng thời trong DB).
>
> **⚠️ SUPERSEDED (2) 2026-08-27 — DEC-FEATYA2C0W-003 (versioned schemes)**: scheme identity đổi sang `VN_ADMIN_2025` / `VN_ADMIN_PRE_2025` (profiles `vn_admin_2025`/`vn_admin_pre_2025`; unit codes `VNA25-`/`VNAP25-`; CURRENT/LEGACY chỉ là status label). Thêm 2 lớp mới: historical reference (registry `secomm_vietnam_address_scheme` + unit `secomm_vietnam_address_unit`, TASK-9394A9) và mapping/resolution (`secomm_vietnam_address_mapping` + `VnAdminAddressResolverInterface` EXACT/MAPPED/AMBIGUOUS/UNMAPPED, TASK-J9AVGK — supersede TASK-X0XKH4/AP6YXP). Swap model DEC-002 giữ nguyên.

**Import workflow:**
```text
Source dataset (CSV checked-in, nguồn documented)
  → CLI validate (orphans/duplicates/missing-reverse) → import (idempotent, code-based)
  → directory_region_city canonical nodes (codes, parent refs)
  → membership rows (profile, region, subtree)
  → relation rows (2 chiều, canonical IDs)
```

**Edge/failure:** relation CSV tham chiếu code không tồn tại → dòng bị từ chối + báo lỗi, KHÔNG insert lẻ; CLI exit nonzero khi có lỗi; re-run import không nhân bản.

## 3. Scope

### In Scope
- Profiles + i18n + config mapping + membership seeding (TASK-R83FXW)
- Legacy dataset import (L- codes, depth-2) + membership (TASK-ADT94K)
- Relation table + CLI + region-level seed (TASK-X0XKH4)
- Resolver contract + impl (TASK-AP6YXP)
- Integration tests + QC evidence (TASK-7RK8Q3); docs + boundary audit (TASK-S0M7YC)

### Out of Scope (PART 7 requirement gốc)
- GHN/GHTK/Ahamove mapping; carrier master-data sync; geocoding (VietMap/Goong/Google); lat/lng; polygons; point-in-polygon; shipping quote/shipment integration; table-rate fallback; renderer schema-driven (FEAT-2PZQKJ TASK-3T3NSV); GraphQL callers migration (TASK-YQSS3M).

## 4. Business Rules

| Rule ID | Rule | Test Approach |
|---------|------|---------------|
| BR-001 | Label keys 3 locale (vi_VN/en_VN/en_US) | grep + render smoke |
| BR-002 | VN dropdown behavior không đổi trong FEAT này (additive) | QC regression checklist |
| DEC-FEATJSZQV3-003 | Label VN chỉ ở VietNamAddress; generic giữ Magento-default | boundary audit (TASK-S0M7YC) |

## 5. Technical Approach

### 5.1 Architecture Impact
Country layer mới trên nền đã approve — không mở lại kiến trúc generic. Diagram target = PART "Final Target Workflow" của requirement gốc.

### 5.2 Implementation Notes
- Không sửa `Secomm_AddressDropdown` (consume-only). Relation/membership references dùng canonical `region_id`/`city_id`.
- Import legacy CHỈ qua import v2 (code refs) — không dùng entity 9-cột cũ.

### 5.3 Database Changes
- Mới (VietNamAddress, declarative): `secomm_vietnam_address_relation` — chi tiết trong DEC-FEATYA2C0W-001 mục 4.
- Không sửa bảng nào hiện hữu.

### 5.4 API Changes
- Mới: `VietnamAddressResolverInterface` + `VietnamAddressResolutionInterface` (service contract trong country module).
- Không đổi API hiện có.

### 5.5 Integration Impact
Không integration ngoài nào thay đổi trong FEAT này. Carrier modules consume resolver ở work items sau (không bắt buộc adopt ngay).

## 6. UI/UX
Không có — FEAT này không chạm renderer (renderer schema-driven thuộc FEAT-2PZQKJ TASK-3T3NSV; khi renderer đó active, profile labels tự áp dụng).

## 7. Dependencies

| Dependency | Provider | Trạng thái |
|---|---|---|
| Profile engine (XML reader, contracts) | TASK-NW66H9 (FEAT-2PZQKJ) | done |
| Membership table | TASK-9AEAQQ (FEAT-2PZQKJ) | done |
| Import v2 (dual-format, code refs) | TASK-4F1K3N (FEAT-2PZQKJ) | pending — gate TASK-ADT94K |
| Hierarchy provider (membership consumer runtime) | TASK-J49PRZ (FEAT-2PZQKJ) | pending — không gate FEAT này (isolation test data-level) |
| Ward-level relations data (D-R1) | Nguồn ngoài (TL/PM cung cấp) | pending — gate phần ward của TASK-X0XKH4 |
| Locale en_VN | Secomm_VietNamMarket | có sẵn |

## 8. Risks

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| D-R1 không có nguồn relations ward-level | H | M | Phase theo tầng: region-level trước; ward NOT_FOUND là Known Limitation tường minh |
| Import legacy đè data current | L | H | Namespace L- + snapshot diff trước/sau (AC-002 TASK-ADT94K) |
| Bảng chung +14k rows | M | L | Index sẵn; D-R4 sign-off |
| Scope leak vào generic/carrier | M | M | Boundary audit TASK-S0M7YC; review gate |

## 9. Test Approach
- Unit: resolver 6 scenarios (mock relations); CLI validate logic; idempotency helpers.
- Integration (DB dev): membership isolation 2 chiều; profile levels qua OM; resolver trên data thật (3 status); import re-run idempotent.
- QC checklist regression: storefront customer form VN, cart estimate, admin order form, 3 validator plugins.
- Evidence: `.ai/runtime/evidence/FEAT-YA2C0W/` + per-task.

## 10. Assumptions
- [ ] [ASSUMPTION] Mapping 63→34 provinces là công bố chính thức, ổn định, khối lượng kiểm thủ công được (34 dòng × chiều).
- [ ] [ASSUMPTION] Ward-level mapping sẽ có nguồn chính thức sau (D-R1) — không bao giờ infer theo tên.
- [ ] [ASSUMPTION] Storefront tiếp tục dùng vn_current mặc định; không có yêu cầu render legacy cho customer trong release này.

## 11. Open Questions
- [x] **D-R1**: nguồn file relations ward-level — **ĐÃ CHỐT 2026-08-25**: phase region-level trước; ward-level NOT_FOUND tường minh (Known Limitation) đến khi có file chính thức; không infer theo tên
- [x] **D-R2**: prefix `L-` cho legacy region codes — **ĐÃ CHỐT**: approve
- [x] **D-R3**: giữ `relation_type` nullable — **ĐÃ CHỐT**: approve
- [x] **D-R4**: chấp nhận +~11.4k rows bảng chung — **ĐÃ CHỐT**: approve
- [ ] Ward-level AMBIGUOUS handling ở carrier consumer là chính sách của module đó — không quyết ở đây.

## 12. Estimation

| Task | Estimate | Actual |
|------|----------|--------|
| TASK-R83FXW (profiles + membership) | 6h | |
| TASK-ADT94K (legacy dataset) | 12h | |
| TASK-X0XKH4 (relation + CLI) | 10h | |
| TASK-AP6YXP (resolver) | 8h | |
| TASK-7RK8Q3 (integration + QC) | 6h | |
| TASK-S0M7YC (docs) | 3h | |
| **Total** | **45h** | |

## Approval

| Role | Name | Date | Status |
|------|------|------|--------|
| SA/TL | User acting as SA/TL (chat; formal name [TBD]) | 2026-08-25 | Approved |
| PM | | | |

---

## Appendix A — Findings (review 14 mục, 2026-08-25)

1. VietNamAddress hiện không có Model/Api/Repository/Console/db_schema/GraphQL riêng — 3 validators + data patch + 4 GraphQL caller JS + i18n 3 locale + view overrides.
2. **0 code dependency** lên `sub_city`/`directory_city_sub_city` (chỉ i18n retranslation entries — chết tự nhiên Phase 3).
3. Datasets: `VN_Address_2Level.csv` (CURRENT, 3.321 rows, 34 regions, đã cài) vs `VN_Address.csv` (LEGACY, 10.600 rows, 63 regions + 705 + 10.595, chưa cài).
4. **Region codes 2 dataset chồng lấn namespace** (01–63 cũ vs 01–34 mới, khác nghĩa) — import legacy qua entity cũ sẽ đè regions đang chạy.
5. Không có bảng/mapping relation hiện hữu; nguồn mapping authoritative (NĐ-CP 165/2025) chưa có file trong project.
6. Cross-references: `Secomm_VietNamMarket` (sequence + locale en_VN); Mageplaza/themes/VNPAY: 0 coupling với VietNamAddress.
7. Overlap với FEAT-2PZQKJ: phần "đăng ký vn_current profile" của TASK-4F1K3N chuyển sang FEAT-YA2C0W (TASK-S0M7YC ghi nhận).
