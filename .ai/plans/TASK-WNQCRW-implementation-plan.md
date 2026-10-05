# TASK-WNQCRW: Align GHN RATE Weight Classification and CREATE Physical Parcel Decision

## Context

Brief TL (frozen operational rules, 2026-10-01) — chuẩn hoá semantics weight giữa RATE và CREATE:

1. **RATE service_type_id phụ thuộc CHỈ total quote weight**: `<20000g → 2`, `>=20000g → 5`. Hiện tại `getServiceTypeId()` có `packageCount === 1 && total < 20kg → 2` (TASK-WAWNDS, docs-câu "or multi-parcel orders") → phải flip. **Cấm**: packageCount/itemCount/SKU count/qty ảnh hưởng type.
2. **Per-unit weight gate (merchant-tunable)**: config mới `carriers/secomm_ghn/max_package_weight_g` (**GRAMS**, default **50000** = `TYPE_5_MAX_WEIGHT_G`, user decision 2026-10-01) — 1 sellable unit > config → ẩn GHN, **không** fee call, **không** fallback (hard carrier incompatibility). Strictly `>` (50.000g đúng biên vẫn quote). Aggregate KHÔNG cap khi mọi unit ≤ limit.
3. **RATE ≠ CREATE truth**: RATE service type transient, không persist/reuse (audit đã confirm — chỉ cần regression test giữ invariant). CREATE giữ nguyên semantics (interpreter + 50kg cap + dimension limits rev.2).
4. Đảo chiều một phần **DEC-TASKFXFMJ0-001** ("no RATE-side weight cap" + "type rule giữ nguyên" clause); fallback rule của FXFMJ0 giữ nguyên. TASK-ZS2B41 rev.2 (dimension config) giữ nguyên — task mới build trên đó; plan rev.3 (weight config) cũ bị absorb vào task này.

**Audit headline (đã trace, trước khi sửa):**
- Flip = 1 ternary [QuoteParcelEstimate.php:88-94](app/code/Secomm/Ghn/Model/Rate/QuoteParcelEstimate.php#L88-L94); consumer duy nhất `GhnRateCalculator::fetchFeeTotal()` L252; `items[]` guard theo type (L264) → type 2 multi-package payload-safe (root aggregate weight only, không items, không dims).
- Chỉ **2 test assertion flip** (5→2): `GhnRateCalculatorTest::testTwoParcelTenKgTotalStillSelectsTypeFive` (L322-345), `QuoteParcelEstimateTest::testMultiParcelEstimatesAlwaysSelectTypeFive` lightMulti leg (L67-78); +1 comment `GhnRateRequestMapperTest:126`.
- RATE transient (không persist/caching); `secomm_ghn_shipment.service_type_id` ghi từ CREATE plan (`GhnShipmentCreationService.php:314` → `GhnShipmentRepository.php:120`), không bao giờ từ RATE estimate; CREATE interpreter (`GhnPhysicalParcelInterpreter.php:53-78`) độc lập hoàn toàn.
- `RATE_REQUEST_UNREPRESENTABLE` đã RESERVED (no emitter) từ FXFMJ0 — §8 "remove" đã thoả, giữ RESERVED; `SafeDegradationEligibilityPolicy` không cần đổi (reason mới free-form → non-eligible by construction).
- Sandbox script `.ai/evidence/TASK-FXFMJ0/sandbox_weight_matrix.php` reuse được: shop 200537, dest 1846/291124, đổi 1 dòng type derivation (L113) + thêm cases.
- Legacy `carriers/secomm_ghn/max_package_weight` (không `_g`) = knob riêng `Ghn.php:427-451` (inert) — không đụng, chỉ docs interplay.

## Specification

| Field | Value |
|-------|-------|
| Task | TASK-WNQCRW (brief TL frozen rules 2026-10-01) |
| Mode | A (Tier-2 shipping) |
| Specification | Mini-Spec embedded trong [records/tasks/TASK-WNQCRW.md](../records/tasks/TASK-WNQCRW.md) — MINI, VALID (5 sections) |
| Decision | DEC-TASKWNQCRW-001 (supersedes DEC-TASKFXFMJ0-001 aspect-scoped: "no RATE weight cap" + "type rule giữ nguyên"; fallback rule FXFMJ0 giữ) |
| Reuses | Pattern TASK-ZS2B41 rev.2 (Config getter + findHardLimitViolation gate + system.xml/i18n), sandbox script TASK-FXFMJ0, CalculatorApi fixture |
| Out of scope | CREATE weight config; fallback eligibility change; legacy `max_package_weight` knob; commit/push |

**Mini-Spec (5 sections — full text trong TASK record):**
1. **Goal**: RATE type = total weight only; per-unit weight gate config-tunable default 50000g; giữ CREATE truth độc lập.
2. **Expected Behavior**: `<20000g → 2`, `>=20000g → 5` bất kể số item/package; unit > config → UNAVAILABLE `GHN_PACKAGE_WEIGHT_LIMIT_EXCEEDED`, API count 0, fallback none; aggregate 60/100/120kg (unit hợp lệ) → type 5 → fee call ×1; CREATE derive từ PhysicalPackages (2 parcel light → type 5 bất kể RATE type 2).
3. **Constraints / Rules**: config GRAMS store-scope `validate-digits validate-greater-than-zero`, fallback const khi empty/≤0; strictly `>`; weight check trước dims trong `findHardLimitViolation()`; không persist RATE type; không đổi CREATE constraints; không đụng §18 (address/PICK_PRIMARY/zones/COD/tracking/TableRate/PhysicalPackage contracts); provenance TÁCH 4 nguồn (§17): 20kg = fee contract; >50kg single = CREATE capability dùng làm eligibility guard (merchant tunable); aggregate acceptance = sandbox-observed; CREATE interpretation = Create contract + PhysicalPackage architecture.
4. **Out of Scope**: CREATE weight config; fallback eligibility cho weight reason; SHIPPINGCORE policy change; legacy `max_package_weight` knob; commit/push.
5. **Acceptance Criteria**: full §13/§14/§15 matrix + item-count independence + CREATE regression; sandbox matrix §6 đủ 11 cases; suites green; compile + validator baseline 56 không tăng; final report §20 A-L với closure marker.

## Implementation (theo thứ tự)

### A. Records trước (spec-first gate)
1. Generate id: `.ai/bin/project-ai-idgen` → tạo `.ai/records/tasks/TASK-{id}.md` (§ structure như TASK-FXFMJ0.md, audit section embed) + `.ai/records/decisions/DEC-{id}-001.md` + DECISIONS.md index line + plan `.ai/plans/TASK-{id}-implementation-plan.md`.
2. TASK-ZS2B41.md: note rev.3 kế hoạch weight chuyển sang task mới (rev.2 dimension giữ nguyên) — 1 dòng Implementation Notes.

### B. Production code
3. **Type flip** [QuoteParcelEstimate.php](app/code/Secomm/Ghn/Model/Rate/QuoteParcelEstimate.php#L88-L94): `return $this->getTotalWeightGrams() < self::HEAVY_WEIGHT_THRESHOLD_GRAMS ? self::SERVICE_TYPE_LIGHT_PARCEL : self::SERVICE_TYPE_HEAVY_GOODS;` — rewrite docblocks L14-22 + L84-87 (bỏ "multi-parcel → 5"); giữ `getPackageCount()` (log + items serialization còn dùng).
4. **Weight gate** (carries over rev.3 plan đã duyệt thiết kế):
   - [GhnPackageLimits.php](app/code/Secomm/Ghn/Model/Rate/GhnPackageLimits.php): `MAX_WEIGHT_G = TYPE_5_MAX_WEIGHT_G` + `REASON_PACKAGE_WEIGHT_LIMIT_EXCEEDED` (re-emitter, history MQ2DRG→FXFMJ0 removed→task này restore); rewrite docblock WEIGHT theo §17 provenance 4 nguồn.
   - [Config.php](app/code/Secomm/Ghn/Model/Config.php): `XML_PATH_MAX_PACKAGE_WEIGHT_G` + `getMaxPackageWeightG(?int $storeId): int` fallback `TYPE_5_MAX_WEIGHT_G`; rename private `readPositiveIntCm` → `readPositiveInt`.
   - [QuoteParcelEstimate.php](app/code/Secomm/Ghn/Model/Rate/QuoteParcelEstimate.php): ctor param 5 optional `int $maxWeightG = GhnPackageLimits::MAX_WEIGHT_G` + `getMaxWeightG()`; `findHardLimitViolation()` weight check **đầu tiên**, strictly `>`, tuple `[reason, index, 'weight', weightG, limit]`.
   - [QuoteParcelEstimator.php](app/code/Secomm/Ghn/Model/Rate/QuoteParcelEstimator.php): thread `getMaxPackageWeightG($storeId)` vào ctor call.
   - [GhnRateCalculator.php](app/code/Secomm/Ghn/Model/Rate/GhnRateCalculator.php): 2 guard blocks log keys kind-aware (`value_g`/`limit_g` vs `value_cm`/`limit_cm`); amend docblocks L54, L62-66 theo rule mới.
   - `etc/config.xml` (`<max_package_weight_g>50000</max_package_weight_g>` + comment §17 provenance), `system.xml` (field sortOrder 163, label "Max Package Weight (g)", comment checkout-only + "Shipment creation ALWAYS enforces 50000 g regardless"), i18n en/vi 1 row.
   - `GhnShipmentConstraints.php` docblock TYPE_5 amend (CREATE frozen + RATE gate default), `GhnPhysicalLimit.php` docblock cite multi-parcel wording fix.

### C. Tests
5. **Flip (2 re-pin)**: `GhnRateCalculatorTest::testTwoParcelTenKgTotalStillSelectsTypeFive` → rename + assert type **2**, không items; `QuoteParcelEstimateTest::testMultiParcelEstimatesAlwaysSelectTypeFive` lightMulti (2×5000) → 2, heavy legs giữ 5.
6. **§13 matrix mới** (QuoteParcelEstimateTest + GhnRateCalculatorTest): 10kg→2; 19.999→2; 20kg→5; 35kg→5; 50kg→5; multi-item 2×10kg total→2; 10 items 1.5kg→2; 2×10kg=20kg→5; 3×10kg=30kg→5; aggregate 2×30kg→5+API; 2×50kg→5+API; **1×50.001 → UNAVAILABLE weight reason, API never, fallback none** (mirror dimension guard test).
7. **§14 item-count independence**: 1×15kg ≡ 3×5kg ≡ 10×1.5kg → 2; 1×30kg ≡ 3×10kg → 5 (estimator-level qua N items).
8. **§15 CREATE regression**: RATE 15kg multi-item → type 2 (estimate) NGOÀI interpreter 2 physical light packages (7.5+7.5kg) → type 5 items[] (independence proof); CREATE 51000g package → vẫn reject (test hiện có giữ).
9. **Weight config tests** (carries rev.3): ConfigTest +3; estimator stub `getMaxPackageWeightG` (bắt buộc — mock un-stubbed trả 0) + flow test configured 40000; `GhnRateRequestMapperTest` stub; boundary 50000 quote; **re-pin FXFMJ0 tests**: 60kg single + 80kg mixed → giờ UNAVAILABLE tại default (đổi premise: assert UNAVAILABLE + weight reason + API never) — sandbox evidence "provider chấp nhận 60kg khi merchant nâng config" chuyển thành unit test với explicit ctor `(..., 100000)`; `testSingleUnitExactlyFiftyKgStillQuotes` giữ boundary; comment sweep "no cap"/"multi-parcel" (danh sách file:line ở audit §5: QuoteParcelEstimateTest:16-21/30, GhnRateCalculatorTest:324/349, GhnRateRequestMapperTest:126, HeavyWeightRateFlowVerificationTest:30-42, RealtimeRateContributorTest:162-170, GhnParcel.php:21-22, GhnRateQuery.php:21-22).

### D. Sandbox retest (§6) — sau khi code + test green
10. Copy script → `.ai/evidence/TASK-{id}/sandbox_weight_matrix.php`, đổi L113 sang total-weight-only + thêm cases còn thiếu: 2×5kg (type 2 multi-unit — case quan trọng nhất), 2×10kg (20000 boundary), 2×50kg, 3×40kg; chạy đủ 11 cases của §6; record service_type/root weight/items[]/HTTP/code/message/total fee vào `.ai/evidence/TASK-{id}/evidence.md`. Prereq: sandbox token + shop 200537 còn hiệu lực (nếu hết → report blocker, mark NOT FROZEN phần sandbox).

### E. Docs (§16)
11. USER_GUIDE line 255 ("Weight chỉ quyết định service type: ≤19.999 single → 2 · ≥20.000 hoặc multi-parcel → 5" + "KHÔNG có weight cap") + line 500 ("≥20kg hoặc multi-package → 5") → rewrite theo rule mới + `max_package_weight_g` + legacy interplay; extend ShippingCore CHANGELOG 0.26.3 docs bullet; CHANGELOG Ghn version mới cho task này (0.17.0 đang unreleased của ZS2B41 — giữ, task mới = entry riêng 0.18.0).

## Verification (§19 + §20)

1. Baseline: Ghn 471/471 hiện tại; validator 56 FAIL.
2. Targeted tests khi dev → full `Secomm_Ghn` + `Secomm_ShippingCore` (kỳ vọng ~479+ → report exact tests/assertions/failures/errors/skips).
3. `setup:di:compile` + `bin/magento cache:flush`.
4. Runtime bootstrap: `getServiceTypeId()` independent cases; `getMaxPackageWeightG()` → 50000; `GhnPhysicalLimit` frozen 50000.
5. Validator after: 56 không tăng.
6. Sandbox matrix §6 chạy + record.
7. Guard grep: `GHN_PACKAGE_WEIGHT_LIMIT_EXCEEDED` chỉ emit từ `QuoteParcelEstimate`; không còn "multi-parcel" wording ở RATE context; không còn `readPositiveIntCm`.
8. **Final report §20 A-L** (audit / sandbox / rule proof / 50kg rule / aggregate / fallback / boundary / CREATE regression / tests / compile-validator / docs / closure marker `GHN RATE/CREATE weight semantics = FROZEN` hoặc `NOT FROZEN` + blocker) — đăng trong chat + lưu `.ai/evidence/TASK-{id}/evidence.md`.
