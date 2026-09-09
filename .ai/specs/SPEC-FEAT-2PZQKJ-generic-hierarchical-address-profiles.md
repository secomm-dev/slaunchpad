# Feature Spec: Generic hierarchical address profiles — refactor Secomm_AddressDropdown

Specification ID: SPEC-FEAT-2PZQKJ

> Filename: `SPEC-FEAT-2PZQKJ-generic-hierarchical-address-profiles.md` — naming per `rules/spec-first.md` §Spec Naming + `rules/work-item-identity.md` §7.

## Metadata

| Field | Value |
|-------|-------|
| Specification ID | SPEC-FEAT-2PZQKJ |
| Feature ID | FEAT-2PZQKJ |
| Specification Level | FULL |
| Author | Claude (AI-assisted draft) — từ architecture review 2026-08-25 |
| Status | **Approved** — user acting as SA/TL, 2026-08-25 (kèm DEC-FEAT2PZQKJ-001 accepted) |
| Date | 2026-08-25 |
| Related Ticket(s) | TASK-9AEAQQ · TASK-NW66H9 · TASK-J49PRZ · TASK-3T3NSV · TASK-4F1K3N · TASK-CR4D1V · TASK-YQSS3M · TASK-9EX975 · TASK-K09G8Y · TASK-ZHFVRH |
| Workflow Mode | A (DB migration + checkout/customer form + API contract → Tier-2) |

## 1. Objective

Chuyển `Secomm_AddressDropdown` từ cấu trúc cố định `city → sub_city` (đúng 2 cấp dưới region) sang **hierarchical directory đệ quy** (`directory_region_city.parent_city_id`) điều khiển bởi **Address Profile / Address Schema**, để: (a) một cơ chế duy nhất phục vụ mọi country với chiều sâu bất kỳ; (b) label/level behavior đến từ cấu hình profile chứ không từ cấu trúc bảng; (c) canonical identity theo `city_id` end-to-end; (d) loại bỏ hạ tầng `sub_city` sau cửa sổ backward-compatibility 3 phase.

Generic module **không** chứa domain concept country-specific (district/ward/commune/province/…). VN-specific sống ở `Secomm_VietNamAddress`; carrier-specific (GHN/GHTK IDs) sống ở carrier modules — nguyên tắc DEC-8 / DEC-FEATJSZQV3-001/003 (giữ nguyên).

## 2. User Stories

### US-001: Customer (storefront Hyva)
As a customer, I want address form hiển thị đúng các dropdown theo country tôi chọn, so that tôi nhập địa chỉ chính xác không thấy label sai (vd "City" cho phường).

**Acceptance Criteria:**
- [ ] Country có profile (vd VN → `vn_current`) → render levels theo schema: đúng số level, label, placeholder, required, thứ tự (`sort_order`).
- [ ] Country không có profile → native Magento behavior (text input `city`, không cascade).
- [ ] Chọn region → load city roots; chọn city có children + schema còn level → render level tiếp; hết schema HOẶC không còn children hợp lệ trong profile → dừng (profile-defined leaf).
- [ ] Edit-mode prefill chọn lại đúng chuỗi đã lưu (qua `getLocationPath`).
- [ ] Giá trị submit là `city_id` (ID-canonical); leaf persist vào native `city`.

### US-002: Admin (backend)
As an admin, I want mọi admin address surface (customer address, order create/edit, Store/Shipping Origin, MSI Source) render theo cùng schema như storefront, so that nhập liệu nhất quán và không tạo địa chỉ sai quan hệ.

**Acceptance Criteria:**
- [ ] 4 admin surface render theo resolved schema; AJAX-loaded form (insertForm) hydrate cascade đúng (pattern SL-011).
- [ ] Server-side validation: leaf phải thuộc path hợp lệ của region đã chọn; invalid → neutralise + log, không chặn save sai kiểu crash (pattern `ValidateVietNamWard`).
- [ ] Mỗi surface persist đúng native store của nó (DEC-FEATE2HM1J-001 principle giữ).

### US-003: Integrator (carrier modules / future country modules)
As a developer module khác, I want stable service contracts + canonical IDs, so that tôi không phải query bảng hoặc bridge tên→ID.

**Acceptance Criteria:**
- [ ] `AddressProfileResolverInterface` / `AddressSchemaProviderInterface` / `LocationHierarchyProviderInterface` khả dụng qua di; không expose table structure.
- [ ] GraphQL `addressLocations(regionId|parentCityId, profile)` + `addressSchema(countryId, profile)` hoạt động anonymous, locale đúng.
- [ ] GhnAddressMapper/GiaoHangNhanh/Ghtk hoạt động không đổi trên `city_id` sau alignment; không còn code ngoài AddressDropdown đọc `directory_city_sub_city*`.

### US-004: Merchant (multi-country)
As a merchant, I want cấu hình country → address profile, so that mỗi thị trường có representation phù hợp (vd VN current vs legacy khi cần đối soát dữ liệu cũ).

**Acceptance Criteria:**
- [ ] Config scope store: country → default profile code; unmapped → native.
- [ ] Cùng country có nhiều profile import song song (membership tách dataset) — chỉ 1 active qua config.

## System Behaviour

**Runtime rendering flow (canonical):**
```text
Country change → resolveProfile(countryId, context)
  → getSchema(profile) → render fields theo sort_order
Region select → getRootLocations(regionId, profile)
City select  → getChildLocations(cityId, profile) — lặp theo schema
Stop         → hết schema levels HOẶC hasChildren == false
Persist      → leaf → native `city`; full path → extension attribute (D2)
Validate     → server-side: leaf ∈ path hợp lệ (rule thuộc country adapter)
```

**Membership semantics:** node có entry trong `secomm_address_profile_location` → theo entry; không có → inherit tư cách parent (root region mặc định thuộc mọi profile trừ khi loại trừ); profile không có membership data → all-nodes (BC với data hiện tại).

**Migration flows:** import v2 dual-format (legacy 9 cột map depth-1/2; format mới tham chiếu `code`); re-key VN dataset gán `code`, giữ nguyên `city_id`. Declarative schema additive Phase 1; destructive Phase 3 gated.

**Edge/failure:** profile code cấu hình nhưng không tồn tại → fallback native + log warning; GraphQL lỗi → renderer giữ level hiện tại + hiển thị retry; import row lỗi → STOP_ON_ERROR như hiện tại.

## 3. Scope

### In Scope
- Recursive schema (`parent_city_id`, `code`) + membership table (TASK-9AEAQQ)
- Profile/Schema XML + DTO + 2 contracts (TASK-NW66H9)
- Hierarchy provider + GraphQL mới (TASK-J49PRZ)
- Hyva schema-driven renderer behind flag + validation port (TASK-3T3NSV)
- Import v2 + VietNamAddress re-key + `vn_current` (TASK-4F1K3N)
- Security/perf fixes touch-point (TASK-CR4D1V)
- Carrier + GraphQL consumer alignment (TASK-YQSS3M)
- Admin surfaces switch (TASK-9EX975)
- sub_city removal gated (TASK-K09G8Y)
- Docs sync (TASK-ZHFVRH)

### Out of Scope
- `Secomm_VietnamAddress` future capabilities: `vn_legacy` dataset import, old↔new ward mapping, historical conversion
- Carrier master-data normalization, GHN/GHTK ID mapping logic, geocoding
- OSC deep integration (SPEC-TASK-FMAN1B — feature riêng)
- Mageplaza TableRate logic; admin CRUD cho profiles; GIS/reporting

## 4. Business Rules

| Rule ID | Rule | Test Approach |
|---------|------|---------------|
| BR-001 | Storefront strings song ngữ vi_VN + en_US (label keys từ profile dịch trong module khai báo) | QC 2 store locales |
| BR-002 | Address fields = AJAX hierarchical dropdowns điều khiển bởi profile (sau refactor: không hardcode level) | QC end-to-end VN + fixture country depth-2 |
| BR-004 | Mageplaza OSC thay default checkout — không regress OSC features (ExtraFee, DeliveryTime, Mollie) | QC L3 checkout + payment test |
| DEC-FEATJSZQV3-003 | Generic dùng Magento-default labels; country labels/behaviour → country module | Audit grep + review |

## 5. Technical Approach

### 5.1 Architecture Impact
Thay đổi kiến trúc dữ liệu + render của CMP-ADDR (chi tiết DEC-FEAT2PZQKJ-001 — proposed): recursive hierarchy thay fixed 2 cấp; thêm profile/schema layer; ID-canonical end-to-end. Giữ: generic/adapter boundary, native-store persistence, canonical `region_id`/`city_id`.

### 5.2 Implementation Notes
- Declarative schema only; whitelist sync bắt buộc; no ObjectManager; strict_types; ViewModels preferred; Hyva Alpine patterns (DEC-7 Strategy B); KO stack legacy không đụng đến Phase 3.
- Renderer mới behind config flag `address/general/renderer` (legacy|schema) cho cutover per-store.
- 3 phase incremental theo FEAT-2PZQKJ plan; Phase 3 gate: prod scan + không còn reader + Tier-2 sign-off.

### 5.3 Database Changes
- `directory_region_city`: + `parent_city_id` (NULL, self-FK CASCADE), + `code` VARCHAR(64), + 2 index, + UNIQUE `(region_id, parent_city_id, code)`.
- New `secomm_address_profile_location` (profile_code, location_type, location_id, include_subtree; PK 3 cột).
- Phase 3 (gated): drop `directory_city_sub_city`, `directory_city_sub_city_name`, cột `sub_city` ×3 bảng, EAV attr.

### 5.4 API Changes
- Mới: 3 PHP service contracts (§US-003); GraphQL `addressLocations`, `addressSchema`.
- Mới (D2): extension attribute `location_path` (JSON array `{city_id, level}`) trên Quote/Order/Customer Address — leaf vẫn persist native `city`.
- BC shim: `GetListCity` → getRootLocations; `GetListSubCity` → getChildLocations (giữ response shape), deprecate sau đó retire Phase 3.

### 5.5 Integration Impact
GhnAddressMapper (CascadingOptions rời sub_city query), GiaoHangNhanh (đổi nguồn reader theo D2 + khai báo sequence), Ghtk (verify, không đổi logic), Mageplaza OSC (không đổi code — QC regression), VNPAY (không ảnh hưởng).

## 6. UI/UX
Renderer Hyva mới dùng form markup + Tailwind v4 tương đương template hiện tại (không đổi visual language); label/placeholder động từ schema. Admin giữ pattern UI hiện có, chỉ đổi nguồn data cascade.

## 7. Dependencies
- TASK order: 9AEAQQ → NW66H9 → J49PRZ → {3T3NSV ∥ 4F1K3N ∥ CR4D1V} → YQSS3M → 9EX975 → K09G8Y → ZHFVRH.
- Gate: DEC-FEAT2PZQKJ-001 accept (đặc biệt D2/D4/D5/D6) trước task đầu tiên in_progress.
- Nền: Magento 2.4.8-p5 declarative schema; Hyva 3.x; GraphQL có sẵn.

## 8. Risks

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| Checkout regression (OSC + renderer mới) | M | H | Flag per-store; QC L3 + payment test (gate AGENTS §7.1) |
| Prod data sub_city khác local (có data) | M | H | Pre-flight scan gate TASK-K09G8Y; data patch migrate nếu cần |
| Form-validation port thiếu rule | M | M | Checklist rule-by-rule (AC-003 TASK-3T3NSV) + QC parity |
| Membership query perf (14k nodes) | L | M | Index + EXPLAIN verification (AC-004 TASK-J49PRZ) |
| GhnAddressMapper FK / carrier payload break | L | H | city_id canonical không đổi; QC carrier path L3 |
| Scope creep (GIS/carrier mapping) | M | M | Out-of-scope list tường minh; DEC phân tầng trách nhiệm |

## 9. Test Approach
- Unit: profile merge/sort/fallback; membership inheritance; provider queries (mock collections).
- Integration: schema migration trên DB có data; import v2 dual-format; GraphQL responses; 2-locale cache isolation.
- QC L3 (per AGENTS §8.6): VN dropdown end-to-end, OSC checkout + payment, admin 4 surfaces, GHN/GHTK rate + order sync, cart estimate.
- Evidence: `.ai/runtime/evidence/FEAT-2PZQKJ/` + per-task.

## 10. Assumptions
- [ ] [ASSUMPTION] `directory_city_sub_city*` = 0 rows và `sub_city` column ~empty trên **production/staging** như local dev — phải verify ở TASK-K09G8Y pre-flight.
- [ ] [ASSUMPTION] VN tiếp tục dùng 2-level (`vn_current`) làm representation active; `vn_legacy` chỉ khi business yêu cầu.
- [ ] [ASSUMPTION] KO/Luma stack hiện tại vẫn cần cho cửa sổ Phase 1-2 (OSC qua luma-checkout compat) — không gỡ sớm.
- [ ] [ASSUMPTION] Merchant không cần admin-editable profiles (D4 = XML).

## 11. Open Questions
- [x] **D2**: persist policy depth > 2 — **ĐÃ CHỐT 2026-08-25**: leaf→`city` + `location_path` extension attribute (DEC-FEAT2PZQKJ-001)
- [x] **D4**: profile XML vs DB — **ĐÃ CHỐT**: XML code-shipped
- [x] **D5**: membership subtree-claim vs per-node — **ĐÃ CHỐT**: subtree-claim + inheritance
- [x] **D6**: thêm `code` column — **ĐÃ CHỐT**: CÓ
- [ ] Có cần `vn_legacy` trong release này không? (PM/SA — mặc định KHÔNG)
- [ ] OSC address integration (SPEC-TASK-FMAN1B) chạy song song phase nào? (SA/TL — mặc định sau Phase 2 cutover)

## 12. Estimation

| Task | Estimate | Actual |
|------|----------|--------|
| TASK-9AEAQQ (schema) | 6h | |
| TASK-NW66H9 (profile/contracts) | 10h | |
| TASK-J49PRZ (provider+GraphQL) | 12h | |
| TASK-3T3NSV (Hyva renderer) | 16h | |
| TASK-4F1K3N (import v2 + re-key) | 10h | |
| TASK-CR4D1V (fixes) | 6h | |
| TASK-YQSS3M (carrier alignment) | 10h | |
| TASK-9EX975 (admin surfaces) | 12h | |
| TASK-K09G8Y (removal) | 10h | |
| TASK-ZHFVRH (docs) | 3h | |
| **Total** | **95h** | |

*(Estimate AI-draft — chỉnh sau technical review.)*

## Approval

| Role | Name | Date | Status |
|------|------|------|--------|
| SA/TL | User acting as SA/TL (chat; formal name [TBD]) | 2026-08-25 | Approved |
| PM | | | |

---

## Appendix A — Findings (baseline audit 2026-08-25, nguồn spec này)

1. Schema hiện tại: `directory_region_city` (city_id, region_id FK, default_name — không có code) + `directory_city_sub_city` (1 cấp cố định) + name-tables per locale; cột `sub_city` trên 3 bảng core + EAV attribute.
2. DB local: sub_city tables 0 rows; sub_city column: 0 customer / 3 quote (test) / 0 order; 3.313 city rows (VN only, 2 locales).
3. Dependency: VietNamAddress (writer qua import patch + 4 GraphQL callers + validators); GhnAddressMapper (FK `city_id` SET NULL + CascadingOptions query sub_city table); GiaoHangNhanh (đọc `sub_city`/`city_id` từ quote/order address; thiếu sequence decl); Ghtk (WardIdBridge tên→ID); Mageplaza OSC + themes + VNPAY: 0 coupling.
4. Khiếm khuyết: SQL string interpolation (`Helper/Address.php:77,163`, `Helper/Data.php:95`); CityData single cache key (locale collision); `(int)` cast bug `SaveToQuote.php:36`; extension attribute khai báo lặp; import dedupe name-hack `CONVERT(? USING binary)`.
5. Governance: DEC-FEATE2HM1J-001 point 3 (sub_city fixed 3rd level) bị thay thế bởi DEC-FEAT2PZQKJ-001; `09_MAGENTO_MODULE_MAP.md` stale (thiếu 8+ module Secomm — TASK-ZHFVRH).
