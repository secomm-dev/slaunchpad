# QC L3 Evidence — TASK-FMBBSD GHN-C slice 2: Magento Carrier RATE end-to-end

> Date: 2026-09-14 (r2 = TL-review fixes; **r3 = U1 fix → REAL Quote path PASS — xem §8**) · Environment: local dev WSL, store `fashion_en` (website 1), MySQL mysql84:3307
> GHN sandbox: `dev-online-gateway.ghn.vn` (shop_id 200537 — token NEVER printed; masked in evidence)
> Runner scripts: `/tmp/qc_ghn/qc_run.php` (real Quote→Shipping→Carrier path) + `/tmp/qc_ghn/qc_domain.php`
> (domain path: RuntimeAddressContextBuilder → handoffContextForOperation(RATE) → Stage-2 → fee)

## 0. Preflight

| Check | Result |
|---|---|
| `carriers/secomm_ghn/active` | **1** (đã set cho QC; old `enabled` path không tồn tại — confirmed replaced) |
| environment / shop_id / api_token | sandbox / 200537 / **đã cấu hình (masked)** |
| `general/locale/weight_unit` | **kgs** (website-1 scope override; default scope kgs) |
| dataset manifest | v1.0.0 (bundled `data/manifest.json`) |
| mapping audit | PRE_2025: 10,794/10,794 100%, unmapped/ambiguous/invalid/stale/duplicate/dangling = 0, **production_ready YES** · 2025: 3,355/3,355 100%, **production_ready YES** |

## 1. QC matrix

| # | Case | Result | Evidence (sanitized) |
|---|------|--------|----------------------|
| 1 | Normal VN checkout (real Quote path) | **PARTIAL** — upstream-blocked | Carrier invoked & fail-closed: `GHN rate unavailable {"status":"UNAVAILABLE","reason":"CANONICAL_UNRESOLVED"}`; flatrate rate vẫn trả (`ratesCollected=1`). Nguyên nhân: name-bridge upstream defect (§3). **Domain path (id-based) PASS: SUCCESS 214,500 VND thật từ sandbox** |
| 2 | 2025→PRE_2025 canonical, không bypass ShippingCore | **PASS** | `context: targetScheme=VN_ADMIN_PRE_2025 sourceScheme=VN_ADMIN_2025 sourceUnit=VNA25-F2118484F0` → `canonical SNAPSHOT: scheme=VN_ADMIN_PRE_2025 unit=VNAP25-01965FA7E0` (**chỉ Secomm identity — 0 GHN provider id trong snapshot**) → `representations=[UNIT_ID]` → `SUCCESS amount=214500 VND`, đúng 1 `calculate_fee` HTTP 200 |
| 3 | Reviewed APPROVED mapping (non-trivial) | **PASS** (contract-level) | `OFFICIAL_SOURCE_REVIEWED`: Thạch Giám @ Tương Dương → `GhnMappingResolver` → `provinceId=235 districtId=3288 wardCode=90779` (APPROVED-only, không name matching). Combo Stage-2+fee e2e chứng minh qua Long Vĩnh (APPROVED NORMALIZED_EXACT → 580806/2103 → 214,500 VND). *Ghi chú: cặp "Kỳ Lừa→Tân Thanh" của bộ curated cũ (6TNKDH) không còn trong snapshot hiện tại — dùng reviewed mapping hiện có |
| 4 | Postcode gate | **NOT APPLICABLE cho VN** | Magento core `general/country/optional_zip_countries = HK,IE,MO,PA,GB,VN` → **VN là zip-optional** → zip gate không bao giờ chặn address VN; runtime: postcode NULL → carrier vẫn được invoke (log CANONICAL_UNRESOLVED = validation passed). Gate behavior nếu country zip-required: khóa bởi unit test `testParentZipCodeGateIsPreserved`. Không có UX issue cho Launchpad |
| 5 | Invalid weight unit (fail-closed) | **PASS** | `websites/1 weight_unit='stones'` → `WARNING {"status":"UNAVAILABLE","reason":"INVALID_CONFIGURATION","exception":"...must be kgs or lbs...got stones"}` → no GHN method, flatrate sống, checkout không crash. **Không assume kg, không gọi GHN**. Restored kgs sau QC |
| 6 | Heavy ≥20kg | **PASS** | Product 45kg → `INFO {"status":"UNAVAILABLE","reason":"GHN_HEAVY_PARCEL_UNSUPPORTED"}` + log **0 dòng `GHN call`** = 0 HTTP call, không downgrade 5→2, không exception. Limitation tạm thời được chấp nhận — backlog: GHN type-5 RATE item payload |
| 7 | Provider technical failure | **PASS (test-double level)** | Unit suite: timeout/HTTP 5xx/malformed → `TECHNICAL_FAILURE` (GhnRateCalculatorTest ×3). Runtime network simulation BLOCKED trong QC env (không có sudo/DNS control để black-hole endpoint) — non-blocking: cùng exception→outcome mapping code |
| 8 | Business unavailable (phân biệt technical) | **PASS** | (a) Ward merger AMBIGUOUS: Kỳ Lừa (VNA25-4EA3ADA7E1, 5 candidates) → `UNAVAILABLE / CANONICAL_UNRESOLVED`, **0 GHN call**; (b) Mapping missing: flip row APPROVED→REVIEW_REQUIRED (SQL, đã restore) → canonical VẪN resolve `VNAP25-01965FA7E0` → `UNAVAILABLE / PROVIDER_MAPPING_MISSING` (≠ CANONICAL_UNRESOLVED — §7 boundary runtime-proven), 0 GHN call; (c) auth/config: unit-covered (auth → UNAVAILABLE, never TECHNICAL) |
| 9 | Stable method identity | **PASS (unit level)** | `GhnTest`: `setCarrier('secomm_ghn')`, `setMethod('secomm_ghn')`, `getAllowedMethods() = ['secomm_ghn' => …]`; 0 service_id/service_type/district/ward ở identity (grep gate = 0 hit runtime). Runtime render: bị chặn bởi case 1 upstream |
| 10 | Dimensions omitted | **PASS** | Mapper không đọc package_depth/width/height (code + unit `testDimensionsAreNeverReadAtRate`); fee payload chỉ `service_type_id/weight/to_district_id/to_ward_code` (+optional from_district_id/cod_value) — unit-asserted |
| 11 | No COD inference | **PASS** | Mapper luôn `collectionAmount=null`; `cod_value` chỉ emit khi >0 (unit-asserted); 0 tham chiếu payment method trong RATE path (grep) |
| 12 | API-call efficiency | **PASS** | 1 successful rate = đúng **1 dòng `GHN call calculate_fee`** trong log (HTTP 200, 346ms); 0 available-services, 0 matcher, 0 geocoder, 0 duplicate call |
| 13 | Logging audit | **PASS** | Toàn bộ log QC: chỉ `operation/shop_id/http_status/provider_code/duration_ms` + `status/reason/exception message`; grep token/phone/email/street = **0 hit** (token scrub qua `GhnLogger::sanitizeContext`) |

## 2. Checkout runtime chain (verified)

```text
Quote → collectShippingRates → Shipping::collectRates (Magento core)
  → CarrierFactory → Secomm\Ghn\Model\Carrier\Ghn          [active=1, VN gate, VND guard]
  → GhnRateRequestMapper (RateRequest → GhnRateQuery)       [weight kgs→g, no dims, no COD]
  → GhnRateCalculator
      → RuntimeAddressContextBuilder → handoffContextForOperation(RATE)   [ShippingCore v5]
      → canonical identity VN_ADMIN_2025 → PRE_2025 (Secomm-only snapshot)
      → GhnMappingResolver (Stage-2 APPROVED) → district_id + ward_code
      → GhnApiClient POST v2/shipping-order/fee (sandbox)   [1 call]
      → CarrierRateOutcome SUCCESS 214,500 VND
  → (translate → RateResult\Method 'secomm_ghn')            [unit-proven; runtime render blocked by §3]
```

## 3. Upstream defects found (owning follow-ups — KHÔNG patch trong GHN)

| # | Defect | Impact | Owner |
|---|--------|--------|-------|
| U1 | **Name-based canonical bridge chết**: `secomm_vietnam_address_unit` — toàn bộ level-2 rows cả 2 scheme có `parent_code=NULL` (2025: 3,321/3,321; PRE_2025: 696/696) trong khi seed CSV 2025 chủ đích để trống parent và link ward→province qua `region_code`; `VnOperationalNameResolver::resolveWardByName` → `VnAddressUnitProvider::getChildren` khớp `parent_code` → 0 match → **mọi address theo tên → UNMAPPED**. (Cùng gốc với defect #3 đã ghi ở TASK-6TNKDH — "VietNamAddress-side, vẫn thuộc owning stream") | Real checkout (RateRequest chỉ mang destCity NAME) → GHN/GHTK name-path ẩn với mọi address; chặn QC CASE 1 runtime render | **Secomm_VietNamAddress** (data model/import hoặc resolver linkage) |
| U2 | **Core `RateRequest` không có dest city node id**: `requestShippingRates`/`collectRatesByAddress` chỉ copy field tường minh → id-bridge (city_id) không thể đi qua real Magento collectRates (ShippingCore scalar builder nhận cityId nhưng không caller nào cấp được) | Id-path chỉ tới được qua composition tự gọi domain services; cần quyết định kiến trúc (name-bridge là path chính thức hiện tại) | Magento_Shipping core limitation — kiến trúc (ShippingCore per-op scalar builder gap đã ghi ở slice 2) |

GHN-C behavior với cả 2 defect: **fail-closed đúng thiết kế** (hidden method, log reason, checkout không crash, carrier khác không ảnh hưởng).

## 4. Regression (QC re-run)

See §6 below (final numbers) — scoped Ghn suite + ShippingCore/VietNamAddress suites + `setup:di:compile` + grep gates.

## 5. Config restore checklist (đã thực hiện sau QC)

- `general/locale/weight_unit` websites/1 + default/0 = `kgs` ✓ (verified bằng config:show)
- `carriers/secomm_ghn/showmethod` = 0 ✓ · `debug` = 0 ✓ · `active` = 1 (để merchant QC tiếp)
- `secomm_ghn_address_mapping` row VNAP25-01965FA7E0 = APPROVED ✓ (audit lại production_ready PASS)
- 0 credentials/PII trong evidence; sandbox không bị tạo order (rate-only)

## 8. r3 — U1 fixed (BUG-ZTGGYZ) → REAL QUOTE PATH PASS

Fix thuộc `Secomm_VietNamAddress` (BUG-ZTGGYZ — `UnitSnapshotWriter` synthesize province edge +
`HierarchyParentBackfill` + CLI `secomm:vietnam-address:hierarchy:repair`); **0 đổi trong
Secomm_Ghn/ShippingCore/Ghtk**. DB repair: 2025 backfilled 3,321; PRE 696; idempotent (re-run
no-op); counts nguyên vẹn (34+3,321 / 63+696+10,035); non-root parent NULL = 0.

**QC CASE 1 rerun — REAL Quote path (sanitized):**

```text
CASE=case1-rerun store=fashion_en weight=5.0 postcode=86000 ratesCollected=2
  rate carrier=flatrate   method=flatrate     price=5
  rate carrier=secomm_ghn method=secomm_ghn   price=214500
  secomm_ghn carrierTitle=GHN methodTitle=GHN Delivery
LOG: GHN call {"operation":"calculate_fee","shop_id":"200537","http_status":200,"duration_ms":357}
```

Chain: destCity "Long Vĩnh" (name-only, cityId bỏ trống) → name-bridge EXACT `VNA25-F2118484F0` →
handoff per-op RATE → `VNAP25-01965FA7E0` (Secomm-only snapshot) → Stage-2 APPROVED → GHN ward
580806 @ district 2103 → service_type 2 → **fee 214,500 VND** → `RateResult\Method`
carrier/method `secomm_ghn`.

**U2 decision (theo §17 brief): NOT REQUIRED** — name-only pipeline (cityId=null) SUCCESS trọn vẹn
214,500 VND; RateRequest không cần city node id; KHÔNG mở ShippingCore architecture task.

Kỳ Lừa (merged ward, 5 PRE candidates) → hidden (AMBIGUOUS fail-closed — data reality; external
disambiguation = shift-left task riêng). **GHTK cross-carrier impact**: regression 802 tests / 0F / 0E
(Ghn+Ghtk+VietNamAddress+ShippingCore); 0 đổi code GHTK; name path hưởng lợi cùng bridge.

Follow-up riêng (§20, không thuộc BUG-ZTGGYZ): `VnSchemes::unitFile(PRE_2025)` trỏ reference file cũ
(`VN_ADMIN_PRE_2025_import.csv`) trong khi dataset hiện hành là `VN_ADMIN_PRE_2025_SNAPSHOT_2024_import.csv`.
