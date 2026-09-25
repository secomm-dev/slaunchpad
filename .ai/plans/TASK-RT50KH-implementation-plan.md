# Implementation Plan — TASK-RT50KH (Product Shipping Dimensions Contract + GHN Checkout Dimension Pre-Validation)

| Specification | SPEC-FEAT-FQWEQ3 (../../records/specs/SPEC-FEAT-FQWEQ3-ghn-carrier-adapter.md) + delta embedded trong TASK-RT50KH |
|---|---|
| **Work item** | TASK-RT50KH — slice của FEAT-FQWEQ3; kế tục phần DEFERRED của TASK-MQ2DRG |
| **Mode** | A |
| **Depends** | TASK-MQ2DRG (weight pre-validation + GhnShipmentConstraints — trên working tree) |
| **Risk** | medium — EAV migration (0 data risk) + estimator wiring; ShippingCore runtime không đụng |
| **TL decision** | Option A — Reuse codes, xoá+tạo lại decimal/GLOBAL (AskUserQuestion 2026-09-23) |

> Work item: TASK mới (mint bằng `.ai/bin/project-ai-idgen TASK --project .`) — parent `FEAT-FQWEQ3` (GHN adapter; kế tục phần DEFERRED của TASK-MQ2DRG), Mode A, Mini-Spec embedded + `| Specification |` row. Related: TASK-MQ2DRG, TASK-WAWNDS.

## Context

TASK-MQ2DRG đã implement weight pre-validation và DEFER dimension pre-validation vì "không có attribute source có unit contract". Task này đóng gap: định nghĩa contract shipping-dimension product (P1) + bật live dormant 150cm hard-limit chain của GHN.

**TL đã duyệt (AskUserQuestion 2026-09-23): Option A — REUSE codes `length/width/height` qua xoá+tạo lại decimal/GLOBAL.**

## Audit hiện trạng (verified — 2 explorers + Plan agent + DB audit trực tiếp)

- **DB**: `length`(145)/`width`(146)/`height`(144) = varchar, text, frontend_class NULL, STORE scope, group General, 9 attribute sets, merchandising flags all=1. **0 value rows** ở cả `catalog_product_entity_varchar` + `_decimal` (176 products) — empty shells, không data cần bảo toàn. Không tồn tại `ts_dimensions_*`/`package_*`/`shipping_*`. Contrast: `weight` (82) = decimal/global/backend Weight.
- **Consumers**: `Secomm_Base/Plugin/Model/Shipping.php:102-105` (float casts; output `package_*` không ai đọc) + `Secomm_Ahamove` (ENABLED — volumetric `getLength() ?? 0`) — cả hai tương thích decimal. Theme không render. `catalog_attributes.xml` quote_item resolve theo CODE (đổi id vô hình).
- **GHN dormant chain (live code, dormant vì thiếu data)**: `EstimatedPackage` đã có `?int $lengthCm/widthCm/heightCm` trailing args; `findHardLimitViolation()` per-dimension PRESENT-and-violating vs 150cm; dual-home gates `GhnRateCalculator.php:136+190` (no API call, `GHN_PACKAGE_{LENGTH|WIDTH|HEIGHT}_LIMIT_EXCEEDED`, UNAVAILABLE không fallback — carrier-owned); fee payload OMIT dims (test-locked — giữ nguyên).
- **Estimator wiring (1 điểm duy nhất)**: `QuoteParcelEstimator.php:97-102` construct `EstimatedPackage` không dims. Expansion đã có `$item->getProduct()` (reliable qua AbstractItem lazy load). ⚠ Ship-together configurable → parent product (child tại `getChildren()[0]`); bundle ship-together → parent (dims cấp bundle); ship-separately → child items = plain simples.
- **Composite resolution RESOLVED (không còn gap)**: downloadable = `Product\Type\Virtual` subtype (`isVirtual()=true, hasWeight()=false`, DB: quote items `is_virtual=1`) → estimator skip sẵn như virtual. Grouped → simples nằm trực tiếp trên quote. → mọi case §4 đều deterministic.
- **Silent failure từ MQ2DRG (fix trong task này)**: `GhnPackageLimits::MAX_DIMENSION_CM = 150` (line 42) chưa alias `RATE_MAX_SIDE_CM` — grep invariant lúc đó chỉ check weights, miss 150.

## TL decision (2026-09-23)

**Option A — Reuse (delete + recreate)**: decimal backend, GLOBAL scope, `frontend_class: validate-number validate-zero-or-greater`, labels "Shipping Length (cm)/Width (cm)/Height (cm)", searchable/comparable/visible_on_front/used_in_listing/is_used_in_grid = false, user_defined=true, required=false, group General (tự nhân rộng 9 sets), apply_to ''. Migration add-only; zero data loss.

## Thay đổi theo file

### Secomm_Base (product-data owner — lần đầu có Api/ + Test/)

| File | Nội dung |
|---|---|
| `Api/Data/ShippingDimensions.php` (MỚI) | final immutable VO: int cm L/W/H (complete-only — không null accessor; "missing" = reader trả null) |
| `Api/ShippingDimensionsReaderInterface.php` (MỚI) | `read(Quote\Item $item): ?ShippingDimensions` — docblock: authoritative CHỈ khi cả 3 present + numeric + > 0; **ceil() từng giá trị → int cm** (conservative tại boundary 150: 149.2→150 quotable, 150.1→151 reject); còn lại → null = missing, không bao giờ rejection |
| `Model/Shipping/ProductShippingDimensionsReader.php` (MỚI) | final impl. `resolveProduct()`: configurable ship-together → `getChildren()[0]->getProduct()` (children rỗng/product null → null); bundle ship-together → parent dims; còn lại (simple/grouped-simple/ship-separately child) → own dims; product null → null. `toCm()`: `is_numeric && (float)>0 → (int)ceil`, else null — **cả 3 phải valid, else null**; dùng `getData()`, never throws |
| `Setup/Patch/Data/UpgradeDimensionAttributesToShippingContract.php` (MỚI) | DataPatch + Revertable; `getDependencies() = [AddDimensionProductAttribute::class]` (ordering contractual — glob alphabetical cũng đúng). `apply()`: per code — `removeAttribute` (FK cascade `eav_entity_attribute` dọn 9 set rows; 0 value rows — ghi docblock) rồi `addAttribute` theo definition mới (sort_order 30/31/32; bỏ `option` rác); `revert()`: removeAttribute (không thể resurrect varchar — documented). KHÔNG sửa patch cũ đã execute |
| `etc/module.xml` | sequence += `Magento_Quote` (API signature type `Quote\Item`) |
| `etc/di.xml` | + preference `ShippingDimensionsReaderInterface` → `ProductShippingDimensionsDimensionsReader` (explicit preference theo convention) |
| `composer.json` 1.0.0→1.1.0 + `CHANGELOG.md` v1.1.0 | contract upgrade + reader Api + first tests |

### Secomm_Ghn (wiring + de-dup)

| File | Nội dung |
|---|---|
| `etc/module.xml` | sequence += `Secomm_Base` |
| `Model/Rate/QuoteParcelEstimator.php` | ctor += `ShippingDimensionsReaderInterface`; đọc dims **1 lần per expanded item** (sau qty/weight guards, trước unit loop) → pass CÙNG dims vào MỌI unit package (qty=3 → 3 packages identical dims — không nhân với qty); docblock supersede "Dimensions NOT read at RATE" (giờ đọc CHỈ cho hard-limit gate; fee payload vẫn omit) |
| `Model/Rate/GhnPackageLimits.php` | line 42: `MAX_DIMENSION_CM = GhnShipmentConstraints::RATE_MAX_SIDE_CM` (fix silent-failure MQ2DRG; provenance giữ) |
| `GhnRateRequestMapper.php` + `EstimatedPackage.php` | docblock-only supersede ("dimensions come back when..." → THIS TASK) |

**Không đụng**: fee payload builder, `findHardLimitViolation()` logic, calculator gates, `GhnShipmentConstraints`, CREATE path, Secomm_Base plugin, Ahamove.

### Tests

**Base (MỚI — first suite)** `Test/Unit/Model/Shipping/ProductShippingDimensionsReaderTest.php`: complete `'40.5'/20/'30'` → (41,20,30) ceil; `'150'`→150, `'149.2'`→150; partial → null; `'0'`/`'-5'`/`'abc'`/`''`/null → null; configurable → child dims; children rỗng → null; bundle → parent dims; product null → null.

**GHN**:
- NEW `QuoteParcelEstimatorTest.php` (fixture mirror `GhnRateRequestMapperTest::item()` :212-237 — Product mock `getData` willReturnMap + Item onlyMethods/addMethods): complete dims → package getters int cm; qty=3 → 3 packages same dims; partial → null dims + `findHardLimitViolation()` null + vẫn quote; malformed → null dims; configurable pass-through; reader-null parity với behavior cũ.
- MODIFY `GhnRateRequestMapperTest::setUp` (line ~45) — estimator ctor mới cần reader mock (null-returning).
- Giữ nguyên (phải green): `GhnRateCalculatorTest` dim tests (151 reject/150 quote/missing quote — :215-250), payload-omit tests (:373-394), `QuoteParcelEstimateTest`, weight/payload regression.

### Docs + records

1. Mint ID → TASK record (parent FEAT-FQWEQ3, risk medium, related MQ2DRG/WAWNDS) + plan (Specification row) + DEC record (Option A reuse; completeness-only-authoritative; ceil rationale; composite table incl. downloadable=Virtual finding; fee payload stays dim-free; 150 de-dup correction ghi nhận MQ2DRG grep gap) + FEAT refs + DECISIONS.md index.
2. `Base/CHANGELOG.md` v1.1.0; `Ghn/CHANGELOG.md` **0.13.0** (supersede "Dimension pre-validation DEFERRED" của 0.12.0 + docblocks "deliberately NOT read").
3. `address-shipping.md` — GHN rating paragraph (~:209 USER_GUIDE-like): dimension pre-validation ACTIVE, source = Base reader, dims vẫn omit khỏi fee payload. NO v11.
4. `ShippingCore/docs/USER_GUIDE.md` §5 GHN: dimension contract + 150cm rule + missing-dims semantics + hard reasons không fallback.
5. `SESSION_STATE.md` + evidence dir.

## Thứ tự + gates

| Step | Nội dung | Gate |
|---|---|---|
| 1 | Records (TASK/plan/DEC) | validator records |
| 2 | Base contract (Api/VO/reader/di/module.xml) + Base tests | Base suite green (first) |
| 3 | Migration patch + `setup:upgrade` + DB verify (decimal/global/labels/frontend_class; flags=0; 9 set rows per code; no orphans; value tables 0) + `cache:flush` | DB assertions |
| 4 | Ghn wiring (module.xml + estimator + 150 de-dup + docblocks) + mapper-test fix + Ghn tests | Ghn suite green (~404+) |
| 5 | Full regression: ShippingCore 550 / Launchpad 135 / Ghtk / VietNamAddress + `setup:di:compile` | all green |
| 6 | Admin smoke (Playwright, temp admin user — precedent WY6WP5): product edit render 3 fields (cm) labels; save `40.5/20/30` → reload `40.5000`; `-5`/`0` blocked bởi validation; set thứ 2 cũng render; checkout smoke: product 160×20×20 → GHN ẩn + log `GHN_PACKAGE_LENGTH_LIMIT_EXCEEDED value_cm=160` + TableRate fallback vẫn hiện; reset data. Nếu chặn → `ADMIN_SMOKE = BLOCKED_BY_ENVIRONMENT` | smoke hoặc blocked-honest |
| 7 | Docs + records + validator + invariant grep §19 (11 mục) + final report A–Q | validator 0 FAIL (record mới) |

## Risks

| Risk | Mitigation |
|---|---|
| `removeAttribute` không dọn set rows | FK CASCADE verified (`module-eav/etc/db_schema.xml:411-413`) + post-patch orphan check |
| updateAttribute-only không đổi được backend_type | reject từ đầu — delete+recreate là mechanic đúng |
| Attr ids đổi (144-146 → mới) | 0 references theo id (config/code theo codes) |
| Ahamove regression | null-semantics identical + full suite |
| Fresh-install ordering | explicit `getDependencies()` + glob-alphabetical verified |
| Staging data xuất hiện trước khi patch chạy | re-audit trước apply (docblock note) |
| Qty-loop nhân dims | dims đọc 1 lần ngoài loop + test qty=3 |

## Verification

Full suites (Base mới / Ghn ~404 / ShippingCore 550 / Launchpad 135 / Ghtk / VietNamAddress — số chính xác vào report M) · compile · validator · invariant grep §19: không cartonization/splitting/qty-multiplication/reuse-without-contract/invented-defaults/TECHNICAL-misclassify/INTEGRATION-misclassify/duplicate-150/ShippingCore-redesign/API-call-on-known-violation/missing-dims-regression · final report A–Q với decision `READY | NOT READY`.
