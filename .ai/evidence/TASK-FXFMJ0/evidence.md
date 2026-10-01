# Evidence — TASK-FXFMJ0 (Retest GHN Calculate Fee Weight Threshold)

Ngày: 2026-09-30 · Branch: development

## A. Pre-change audit (§3) — code-truth hiện tại

50kg RATE gates inventory (grep TYPE_5_MAX_WEIGHT_G / findWeightLimitViolation /
HARD_UNIT_OVER_WEIGHT / AGGREGATE_UNREPRESENTABLE / RATE_REQUEST_UNREPRESENTABLE /
GHN_PACKAGE_WEIGHT_LIMIT_EXCEEDED / 50000):

| # | Vị trí | Vai trò |
|---|---|---|
| 1 | `GhnShipmentConstraints.php:41` `TYPE_5_MAX_WEIGHT_G = 50000` | DOCUMENTED Create contract per-package cap (provenance ghi ở :21) |
| 2 | `QuoteParcelEstimate::findWeightLimitViolation()` :145-169 | Pre-validation thuần: unit >50kg → HARD_UNIT_OVER_WEIGHT; total >50kg (units hợp lệ) → AGGREGATE_UNREPRESENTABLE; boundary strict `>` (50.000g representable) |
| 3 | `GhnRateCalculator.php:153` + `:207-210` | Cả 2 entry (standalone `calculate()` + v10 `quoteWithHandoff()`) gọi gate TRƯỚC handoff/mapping/POST → 0 API call khi vi phạm |
| 4 | `GhnRateCalculator::weightLimitOutcome()` :328-342 | Mapping: HARD → `GHN_PACKAGE_WEIGHT_LIMIT_EXCEEDED` (không fallback-eligible); AGGREGATE → `RATE_REQUEST_UNREPRESENTABLE` (fallback-eligible INTEGRATION_LIMITATION) |
| 5 | `GhnPackageLimits.php:57` | REASON_PACKAGE_WEIGHT_LIMIT_EXCEEDED const + docblock ghi rõ sandbox-fee-tolerance là FACT nhưng business-NO có chủ đích (TASK-MQ2DRG) |
| 6 | `GhnWeightConstraintViolation.php` | VO kind/index/weight/limit (chỉ tạo khi test dùng) |
| 7 | ShippingCore `CarrierRateExecutionService::outcomeDrivenEligibility()` :266-271 | UNAVAILABLE+RATE_REQUEST_UNREPRESENTABLE → INTEGRATION_LIMITATION |
| 8 | ShippingCore `SafeDegradationEligibilityPolicy` :49 | RATE_REQUEST_UNREPRESENTABLE nằm trong default eligible map |
| 9 | ShippingCore `ShippingFailureReason.php:67` | Định nghĩa shared reason |

Behavior hiện tại theo weight (verified bằng code + suite):

| Case | Kết quả hiện tại | Fallback? |
|---|---|---|
| <20kg, 1 package | type 2, gọi fee | — |
| 20kg, 1 package | type 5 (boundary `<` 20000), gọi fee | — |
| >20kg tới 50kg, 1 package | type 5, gọi fee | — |
| single unit >50kg | **BLOCK — không API call**, UNAVAILABLE `GHN_PACKAGE_WEIGHT_LIMIT_EXCEEDED` | **KHÔNG** (carrier-owned) |
| aggregate >50kg (units ≤50kg) | **BLOCK — không API call**, UNAVAILABLE `RATE_REQUEST_UNREPRESENTABLE` | **CÓ** (INTEGRATION_LIMITATION) |
| multi-parcel <20kg total | type 5, gọi fee | — |
| weight ≤0 | `GHN_RATE_INVALID_PARCEL_DATA`, không API call | KHÔNG |

RATE_REQUEST_UNREPRESENTABLE consumers ngoài Ghn (audit §9): chỉ ShippingCore (const +
eligibility branch + policy default). **GHN là emitter production duy nhất** — nếu gỡ gate,
reason trở thành RESERVED (không emitter); khuyến nghị giữ const + policy entry, cập nhật
docblock, không đụng shared behavior.

## B. GHN docs verification (§4)

Nguồn: **https://developer.ghn.dev/en/docs/order/calculate-fee** (fetch 2026-09-30, portal
developer.ghn.vn trỏ tới). Trích nguyên văn:

- `weight` — "Parcel weight (gram). **Must be non-zero**" (Int, grams) → required, non-zero.
- `service_type_id` — "Service type, chosen by weight"; "**2**: total weight under 20 kg";
  "**5**: total weight 20 kg or more, **or multi-parcel orders**".
- `length/width/height` — liệt kê không có nhãn required (tùy chọn; ví dụ payload gửi 25/20/8
  cho 600g).
- `items[]` — "Item list — same shape as Create Order; used for heavy goods".
- **KHÔNG có bất kỳ 50kg/50000g threshold nào** trên trang calculate-fee.

Đối chiếu code: rule chọn service type hiện tại (`QuoteParcelEstimate::getServiceTypeId()`
:88-94: 1 package AND total <20000 → 2, else 5) **khớp docs** — không cần sửa (§6).

## C. Sandbox retest (§5)

**BLOCKED_BY_CREDENTIAL.**

- Matrix script sẵn sàng: `sandbox_weight_matrix.php` (12 case A-L, payload shape =
  `fetchFeeTotal` KHÔNG pre-gate; destination RATE đã verify 1846/291124; sanitized).
- Kết quả chạy: 12/12 **HTTP 401 "Token is not valid!"** trên dev-online-gateway (sandbox).
- Chẩn đoán: cùng token + shop → production gateway cũng 401 (1 probe case A duy nhất, read-only
  quote) → token cấu hình (core_config_data default#0, len 92, set 2026-09-24 04:42) **chết ở cả
  hai gateway** — không phải nhầm env, token không còn hiệu lực.
- Bối cảnh: TASK-DFGFZ9 (2026-09-23) từng ghi BLOCKED_BY_CREDENTIAL (chưa có row); row hiện tại
  thêm 2026-09-24 nhưng không dùng được. Sandbox working cuối: TASK-WAWNDS 2026-09-18 (token cũ
  đã rotated, shop 200537 — shop_id hiện tại vẫn 200537).

## D. Decision (§7)

**Gate KHÔNG mở** — thiếu sandbox retest tươi (bằng chứng cũ 2026-09-18 của TASK-WAWNDS — fee
200 cho 2×35kg và single 60kg — gợi mạnh rằng calculate_fee chấp nhận >50kg, nhưng brief đòi
retest matrix trước khi sửa code). **Code giữ nguyên hiện trạng** (50kg pre-gate hoạt động như
DEC-TASKMQ2DRG-001). Docs-side: 50kg RATE pre-gate KHÔNG được docs calculate-fee hỗ trợ — đây
là INTERNAL BUSINESS DECISION (MQ2DRG), đúng phân loại provenance.

## E. Credential correction + gate mở

- 401 đầu tiên là **lỗi probe**: `carriers/secomm_ghn/api_token` lưu **encrypted** (backend
  model `Magento\Config\Model\Config\Backend\Encrypted`, system.xml:59) — đọc raw
  ScopeConfig gửi ciphertext `0:…==` → GHN 401. Credential trong admin là HỢP LỆ; script sửa
  đọc qua `Secomm\Ghn\Model\Config::getApiToken()` (decrypt như production) + base URL qua
  reflection `Config::BASE_URLS`.
- **Sandbox matrix (rerun, 2026-09-30): 12/12 HTTP 200 "Success"**:

| ID | Case | ST | Root(g) | HTTP | Code | Total |
|---|---|---|---|---|---|---|
| A | single 10kg | 2 | 10000 | 200 | 200 | 199,100 |
| B | single 19.999kg | 2 | 19999 | 200 | 200 | 353,100 |
| C | single 20kg (boundary) | 5 | 20000 | 200 | 200 | 121,000 |
| D | single 35kg | 5 | 35000 | 200 | 200 | 440,000 |
| E | single 50kg | 5 | 50000 | 200 | 200 | 440,000 |
| F | **single 50.001kg** | 5 | 50001 | 200 | 200 | 660,000 |
| G | **single 60kg** | 5 | 60000 | 200 | 200 | 660,000 |
| H | 2×10kg (multi-parcel) | 5 | 20000 | 200 | 200 | 121,000 |
| I | 2×15kg | 5 | 30000 | 200 | 200 | 440,000 |
| J | 2×30kg | 5 | 60000 | 200 | 200 | 660,000 |
| K | **2×35kg** | 5 | 70000 | 200 | 200 | 660,000 |
| L | **4×30kg = 120kg** | 5 | 120000 | 200 | 200 | 660,000 |

  Type-5 `items[]` per-unit qty=1 được chấp nhận ở TẤT CẢ case (serialization không disproved).
  (Lần chạy đầu 401-token đã ghi lại ở git history của file này; lần chạy 401-token làm skip 12
  case, không có case nào skip ở lần chạy sạch.)

## F. Code changes (§9/§16) — exact

- `QuoteParcelEstimate.php`: xóa `findWeightLimitViolation()` (+ import VO).
- `GhnRateCalculator.php`: xóa 2 gate call sites (standalone :153 + contributor :207) +
  `weightLimitOutcome()`; header docblock provenance → TASK-FXFMJ0/DEC-TASKFXFMJ0-001.
- `GhnWeightConstraintViolation.php`: **DELETED** (VO chỉ sinh ra cho pre-gate).
- `GhnPackageLimits.php`: xóa `REASON_PACKAGE_WEIGHT_LIMIT_EXCEEDED`; provenance rewrite.
- `GhnShipmentConstraints.php`: docblock `TYPE_5_MAX_WEIGHT_G` → CREATE-only (provenance giữ).
- ShippingCore (docblock only, 0 behavior change): `ShippingFailureReason::RATE_REQUEST_
  UNREPRESENTABLE` + `SafeDegradationEligibilityPolicy` + `CarrierRateExecutionService` →
  đánh dấu **RESERVED** (GHN là emitter duy nhất, giờ 0 emitter; wiring giữ nguyên — §9).

## G. Tests (§12/§13/§14/§15)

- `QuoteParcelEstimateTest`: rewritten — service-type grid (10k/19.999→2; 20k→5; 35k/50k/
  50.001k/60k→5 không reject; multi-parcel luôn 5; dims-gate giữ nguyên).
- `GhnRateCalculatorTest`: 4 test MQ2DRG đảo ngược thành matrix quote-qua-API với
  **API-call assertions** (60kg once + payload type5/weight60000/items1-row; 2×35kg once +
  items 2 rows; 2×5kg→type5; 20kg boundary→type5; mixed 80kg; standalone full-flow) — §13;
  giữ 2 boundary test 50.000g-still-quotes + weight≤0 không API (§12 retain).
- `HeavyWeightRateFlowVerificationTest` (mới, thay `OverFiftyKgNoFallbackVerificationTest`):
  §15 — 60kg/70kg → fee SUCCESS (realtime, weight không tạo UNAVAILABLE); timeout trên 70kg →
  TECHNICAL_FAILURE + policy thật eligible (fallback path giữ nguyên).
- `RealtimeRateContributorTest`: fixture reasons đổi sang reason còn emitter (old reasons
  retired).
- CREATE regression (§14): `GhnPhysicalParcelInterpreterTest` hiện có — 50.000g boundary valid
  (:75-76) + reject "above the 50000 g per-package limit" (:107); const link
  `GhnPhysicalLimit::MAX_WEIGHT_G = TYPE_5_MAX_WEIGHT_G` không đụng.

## H. Regressions (§18)

- `Secomm_Ghn`: **453 tests OK** (446 trước + 7 net mới/sửa), 0F/0E.
- `Secomm_ShippingCore`: **600 tests OK**, 0F/0E.
- `Secomm_Cod`: 48 OK (không đụng).
- `setup:di:compile`: OK.
- `bash .ai/bin/project-ai-validate --check-records --check-specs`: **56 FAIL = baseline, 0
  WARN mới** (1 WARN heading mini-spec phát hiện đã fix ngay).

## I. Grep invariants (§19)

- RATE path (`Model/Rate/`): 0 code ref tới 50kg gate — 2 hit còn lại là docblock provenance
  (GhnRateCalculator:67, GhnPackageLimits:30 — ghi nhận reason cũ đã bỏ, đúng §16).
- Service-type boundary: `HEAVY_WEIGHT_THRESHOLD_GRAMS = TYPE_2_MAX_WEIGHT_G` + `<` tại
  QuoteParcelEstimate:25/:91 — nguyên vẹn.
- CREATE: `TYPE_5_MAX_WEIGHT_G = 50000` + `GhnPhysicalLimit::MAX_WEIGHT_G` link — nguyên vẹn.

## E. Kế hoạch khi có credential (đã chuẩn bị, chờ gate)

1. Set Token/ShopId sandbox hợp lệ → rerun `php .ai/evidence/TASK-FXFMJ0/sandbox_weight_matrix.php`.
2. Nếu >50kg accepted (200 + total): viết DEC supersede DEC-TASKMQ2DRG-001 → gỡ
   `findWeightLimitViolation` khỏi RATE (cả 2 entry) + xóa emitter 2 reason + rework tests
   (§12-13: API-call assertions cho 60kg / 2×35kg; giữ weight≤0; CREATE regression §14;
   fallback regression §15) + cleanup docblocks (§16) + grep invariants (§19).
3. Nếu sandbox TỪ CHỐI >50kg: chốt giữ pre-gate, chuyển provenance INTERNAL → SANDBOX_OBSERVED
   (rejection), FROZEN không đổi code.
