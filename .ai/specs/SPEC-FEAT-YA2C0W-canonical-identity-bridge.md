# Feature Spec: VietNamAddress Canonical Identity Bridge & Reference Guard

Specification ID: SPEC-FEAT-YA2C0W

> Filename: `SPEC-FEAT-YA2C0W-canonical-identity-bridge.md` — naming per `rules/spec-first.md` §Spec Naming + `rules/work-item-identity.md` §7.
> Cùng feature với `SPEC-FEAT-YA2C0W-vietnam-current-legacy-address.md` (Phases A–D); spec này là slice
> Phase-E-prerequisite (bridge + guard) theo DEC-FEATYA2C0W-004.

## Metadata

| Field | Value |
|-------|-------|
| Specification ID | SPEC-FEAT-YA2C0W |
| Feature ID | FEAT-YA2C0W |
| Specification Level | FULL |
| Author | Claude (AI-assisted draft) — từ architecture audit 2026-09-03 + DEC-FEATYA2C0W-004 |
| Status | **Approved** — user acting as SA/TL, 2026-09-03 (kèm DEC-FEATYA2C0W-004 accepted) |
| Date | 2026-09-03 |
| Related Decision(s) | DEC-FEATYA2C0W-004 (D5 bridge, D7 guard) · DEC-FEATYA2C0W-003 (scheme/reference layer — giữ nguyên) |
| Related Ticket(s) | TASK-9394A9 · TASK-J9AVGK · (implementation task mint khi activate) |
| Workflow Mode | A (address domain = Project-Specific Tier-2, AGENTS §12) |

## 1. Objective

Hoàn thiện missing boundary giữa Magento operational address tree và canonical Vietnam administrative
reference layer. Sau spec này, caller phải chuyển được:

```text
Magento region_id / city_id  ↔  VN scheme_code / unit_code
```

mà không cần lookup bằng name. Đồng thời loại bỏ carrier-specific table knowledge khỏi
`Secomm_VietNamAddress` (Decision 7 của DEC-FEATYA2C0W-004).

Spec này KHÔNG implement carrier profile framework (Out of scope — §5).

## 2. Verified implementation basis (audit 2026-09-03)

Thiết kế dựa trên fact đã verify trong code — **runtime tables ĐANG mang canonical codes**:

* `directory_country_region.code` = dataset region code `VN-XX` — ghi lúc import
  (`VnAddressSchemeImporter` — region bootstrap `'code' => (string)$row['region_code']`).
* `directory_region_city.code` = unit code `VNA25-*`/`VNAP25-*` — generic hierarchy importer ghi per row
  (`Secomm\AddressDropdown\Model\Import\Hierarchy\HierarchyImportService`, `'code' => $code`).
* Reference layer `secomm_vietnam_address_unit` giữ `(scheme_code, code)` UNIQUE + level + parent_code +
  region_code; region rows lưu level 1 với `code = region_code` (snapshot writer).
* Active scheme: `secomm_vietnam_address/general/active_scheme` (default `VN_ADMIN_2025`) + registry
  `secomm_vietnam_address_scheme.status = CURRENT`.

➜ **Bridge = pure code-based lookup, KHÔNG cần schema change, KHÔNG bảng mới** (Decision 10:
reuse `secomm_vietnam_address_unit`).

## 3. Scope — contracts

### 3.1 Operational → canonical resolution

Service contract (tên final có thể đổi sau implementation analysis):

```php
Secomm\VietNamAddress\Api\VnOperationalAddressResolverInterface
```

* Input: `int $cityId` (nullable) và/hoặc `int $regionId`; context scheme = active scheme (config).
* Output: `?VnOperationalIdentityInterface` + reason khi không resolve được (không exception cho
  business cases; exception chỉ cho misconfiguration cứng).
* Mechanism: `directory_region_city.code` (scoped `city_id`, join region theo `region_id` để guard
  country = VN) → unit_code; scheme lấy từ `active_scheme`. Region-only query: `directory_country_region.code`.
* Requirements: scheme-aware · ID-based · deterministic · no name inference · no carrier dependency.

### 3.2 Canonical → operational resolution

* Input: `(scheme_code, unit_code)`.
* Output: runtime identity (`region_id` / `city_id`) khi scheme đó ĐANG active và có runtime row.
* Scheme không active / chưa install / runtime row missing (drift): trả explicit unavailable result
  (+ reason `scheme_not_active` / `runtime_row_missing`) — KHÔNG BAO GIỜ fabricate runtime IDs.

### 3.3 Stable identity DTO

Immutable result object `Api/Data/` (pattern `VnAddressUnitData`):

```text
schemeCode · unitCode · level · regionCode · parentUnitCode
```

Hydration: canonical metadata (level/parent/region_code) từ reference layer qua
`VnAddressUnitProviderInterface` (REUSE — không query trùng). KHÔNG thêm carrier data; KHÔNG thêm
geospatial fields.

### 3.4 No name-based canonical identity

Bridge MUST NOT dùng `default_name` / localized name / region name / ward name làm join keys. Name chỉ
được xuất hiện display/diagnostic. Existing best-effort name resolvers (`WardIdBridge`,
`BestEffortViVnResolver`, `getCityIdByName`) KHÔNG bị xoá trong spec này (chưa proven unused — chúng
chết khi carrier migration spec chạy).

### 3.5 Scheme-swap reference guard extension point

Thay 2 const hardcode trong `VnAddressSchemeImporter` (`TABLE_GHN_MAPPING`, `TABLE_GHTK_MAPPING` +
`assertNoCarrierReferences`):

```php
Secomm\VietNamAddress\Api\DirectoryReferenceGuardInterface
// assertSafe(string $operation, array $regionIds, array $cityIds): void  (hoặc trả violation report)
```

* Importer nhận toàn bộ guards qua DI array argument; trước destructive swap/re-key/rebuild:
  chạy từng guard. Vi phạm được **aggregate** từ tất cả guards rồi throw MỘT exception duy nhất
  (message giữ định hướng hiện tại: count + bảng + cột, translated).
* **Vị trí implementation**: concrete guards chuyển về module sở hữu bảng —
  `Secomm_GhnAddressMapper` + `Secomm_Ghtk`. Để tham chiếu interface, 2 carrier module này thêm
  `Secomm_VietNamAddress` vào `etc/module.xml <sequence>` (+ `composer.json` require của
  GhnAddressMapper; Ghtk hiện không có composer.json). Hướng phụ thuộc tuân thủ DEC-FEATYA2C0W-004
  D1 (không tạo cycle: VietNamAddress không depend carrier).
* `VietNamAddress` sau spec này: 0 import carrier class, 0 tên bảng carrier hardcode.

## 4. Constraints / Rules

* PHP 8.2+, `declare(strict_types=1)`; DI constructor, không ObjectManager; không SQL string-concat.
* KHÔNG schema change, KHÔNG bảng mới, KHÔNG whitelist change; CLI import/validate giữ behavior hiện tại.
* `VnAdminAddressResolverInterface` KHÔNG bị sửa (không nhận name/region_id/city_id/carrier_id — AC-5).
* Không cache ở lần đầu (mỗi lookup = 1 indexed query); thêm cache chỉ khi profile chứng minh cần.
* String mới (exception message guard) phải vào cả `vi_VN.csv` + `en_US.csv`.
* Các lookup phải bounded (indexed by PK/code; no table scan).

## 5. Out of scope

```text
CarrierApiProfileInterface · CarrierAddressMapperInterface
ShippingCore destination pipeline · VietMap integration · geospatial disambiguation
GHN mapper merge (DEC-004 D8) · GHTK mapper re-key (D6)
carrier API switching · persisted quote/order/customer snapshots (§23)
xoá name-based resolvers của carrier
```

## 6. Acceptance Criteria

- [ ] **AC-1**: Active `VN_ADMIN_2025` + runtime `(region_id, city_id)` hợp lệ → trả đúng MỘT
  `VN_ADMIN_2025 + unit_code`, không so sánh name bất kỳ đâu trong path.
- [ ] **AC-2**: Active `VN_ADMIN_PRE_2025` → contract hoạt động cho region / district / ward qua
  recursive hierarchy (`city_id` ở depth bất kỳ), không fixed-depth assumption.
- [ ] **AC-3**: `(scheme_code, unit_code)` thuộc scheme active → reverse trả runtime identity đúng.
- [ ] **AC-4**: Unit thuộc scheme non-active → reverse KHÔNG fabricate `city_id`; trả unavailable + reason.
- [ ] **AC-5**: `VnAdminAddressResolverInterface` giữ nguyên contract (code-based).
- [ ] **AC-6**: `VnAddressSchemeImporter` chứa 0 reference tới GHN / GHTK / Ahamove / tên bảng carrier
  (grep-verifiable).
- [ ] **AC-7**: Scheme-swap protection cho `secomm_ghn_address_mapping_location` +
  `secomm_ghtk_address_map` VẪN hoạt động (behavior parity với guard cũ: cùng điều kiện chặn, cùng
  ngữ nghĩa message) — qua extension point.
- [ ] **AC-8**: Carrier mới đăng ký guard bằng DI (module riêng) KHÔNG cần sửa VietNamAddress.
- [ ] **AC-9**: Config/registry drift (`active_scheme` = X nhưng registry CURRENT = Y hoặc runtime
  trống) → explicit unavailable result, không crash, không resolve theo scheme sai.
- [ ] **AC-10**: Runtime row có `code` NULL (legacy stray) hoặc id không tồn tại / không thuộc VN →
  explicit null + reason, KHÔNG fallback theo name.

## 7. Tests

| Nhóm | Case |
|---|---|
| 2025 | region runtime id → canonical unit · ward runtime `city_id` → canonical unit · canonical unit → runtime `city_id` |
| PRE_2025 | province · district · ward (depth 2) |
| Edge | invalid runtime id · invalid unit code · non-active scheme reverse · config/registry drift (AC-9) · runtime code NULL (AC-10) · non-VN region |
| Guard | zero guards · one guard safe · one guard blocking · multiple guards (aggregate 1 exception) · guard đăng ký qua DI từ module khác · dry-run liệt kê guards |
| Verification | no name lookup performed (mock/spy) · `grep` 0 carrier reference trong VietNamAddress |

Arrange-Act-Assert; happy + edge + error; Unit test cho resolver + guard aggregation; integration-style
test DI registration.

## 8. Migration / compatibility

* Giữ nguyên 3 bảng: `secomm_vietnam_address_scheme` / `_unit` / `_mapping`. KHÔNG bảng canonical mới.
* CLI `secomm:vietnam-address:import|--swap|--rebuild|--dry-run` + `import-mapping` + `validate-mapping`:
  behavior + output format giữ tương thích; dry-run BỔ SUNG phần guard report.
* Mapping semantics giữ: EXACT / MAPPED / AMBIGUOUS / UNMAPPED.
* Module.xml: +2 edge (`Secomm_GhnAddressMapper` → `Secomm_VietNamAddress`, `Secomm_Ghtk` →
  `Secomm_VietNamAddress`); không edge nào bị xoá; không cycle (verify bằng danh sách sequence hiện có).
* Guard impl move = move code + DI registration, không đổi DB.

## 9. Deliverables

1. `Api/VnOperationalAddressResolverInterface` + `Api/DirectoryReferenceGuardInterface` + `Api/Data/VnOperationalIdentityInterface` (final tên sau analysis) + DI wiring.
2. Operational → canonical implementation (`Model/`).
3. Canonical → operational implementation.
4. Unit/integration tests (§7).
5. Guard contract + DI array argument trên `VnAddressSchemeImporter` (aggregate + dry-run report).
6. Guard impls: `Secomm_GhnAddressMapper` + `Secomm_Ghtk` (move behavior cũ), + module.xml/composer edges.
7. Xoá `TABLE_GHN_MAPPING`/`TABLE_GHTK_MAPPING` + `assertNoCarrierReferences` hardcode khỏi VietNamAddress.
8. README/CHANGELOG `VietNamAddress` + `GhnAddressMapper` + `Ghtk` cập nhật theo DEC-FEATYA2C0W-004.

## 10. Risks & Unknowns

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Legacy-stray runtime rows thiếu `code` nhiều hơn kỳ vọng | M | L (bridge trả reason, không sai dữ liệu) | Report count trong CLI import; cleanup path đã có sẵn |
| Guard message i18n chuyển ownership làm mất translation cũ | L | L | Copy message vào i18n cả 2 module nhận |
| Region unit semantics (level-1 `code = region_code`) khác giả định | L | M | Đã verify snapshot writer; thêm unit test khoá behavior |
| `active_scheme` config đổi tay giữa chừng (backend model đã chặn flip scheme chưa install) | L | M | AC-9 drift check dựa registry, không tin config một mình |

## 11. Exit condition

KHÔNG bắt đầu ShippingCore carrier-profile implementation cho tới khi:

```text
runtime address identity → canonical VN identity
```

là deterministic và có automated tests (AC-1..10 green).

Spec kế tiếp sau khi xong: `ShippingCore Carrier API Profile + Destination Address Orchestration`
→ `GHN migration` → `GHTK migration` → `Ahamove profile adoption`.
