# TASK-5H8WKE — Cap engine: MaxDiscountCap collector (sort 310) + CapResolver + LRM allocator

**Legacy ID:** SL-022 *(re-identified 2026-08-20 per DEC-027; mapping: .ai/toolkit/legacy-id-map.yaml)*

**Type:** Task (slice của FEAT-JKZM68 — core engine, phần rủi ro nhất)
**Priority:** High
**Estimate:** ~16–20h
**Mode:** A (Tier-2: quote totals pricing — AGENTS §9/§11/§12; spec-first + plan trước khi code)
**Placement:** `app/code/Secomm/PromotionMaxDiscount/{etc/sales.xml,Model/Quote/Address/Total/MaxDiscountCap.php,Model/Cap/,Model/Redistribution/,Test/Unit/}`
**Risk tier:** Tier 2 (pricing/quote totals — checkout-critical path)
**Author:** AI draft · **Date:** 2026-08-19 · **Status:** Dev complete *(2026-08-20: TL Tier-2 approved trong chat → execute Task 1–9 một session. AC-1..AC-10 verified (map bằng chứng trong evidence). 2 bug thật fix trong session: resolver instance-memo leak qua collect passes (giờ stateless — collector contract 1 query/pass), test-data pollution do `exit()` bỏ `finally`. 5 design corrections từ vendor thật (getDiscountData/getRuleLabel, DiscountData setters → in-place mutation, PriceCurrency::round hard-code 2 → Locale Format precision, sales.xml `<item>`, AbstractExtensibleObject cần OM factories). Runtime smoke VND: cap 50k → 20k/30k đúng contribution, Σ == cap cả 2 chuỗi, idempotent ×3. Unit 38/849 GREEN + existing 132/354 OK. Limitation disclosed: module-disable runtime cycle để QC/TASK-HPK1WZ. **2026-08-21: AI pre-review round 2 → 2 Critical + 2 Warnings, fix cùng session** (C1 rebuild address breakdown từ mọi assignment items — rule uncapped trên item khác không còn mất entry; C2 precision theo currency code của quote, không rơi về request scope; W1 resolver select 3 cột; W2 plugins around→after) + 2 repro tests → **40/858 GREEN, all-Secomm 217/1325 OK**. **Runtime smoke ROUND 2 engine thật: 24/24 PASS** (C1 mixed-rule breakdown R1 capped 50k + R2 uncapped 10k cùng present; C2 precision VND persisted codes; W2 converter round-trip; idempotent ×3; `di:compile` re-run sau plugin change). Chờ TL code review Tier-2. Evidence: [TASK-5H8WKE-evidence](../runtime/evidence/TASK-5H8WKE/TASK-5H8WKE-evidence.md))*
**Specification:** MINI — embedded `## Mini Spec` dưới đây (ID: TASK-5H8WKE) · canonical parent: [SPEC-FEAT-JKZM68](../specs/SPEC-FEAT-JKZM68-promotion-max-discount.md) (FULL, VALID) §4–§11 · Plan: [TASK-5H8WKE plan](../plans/TASK-5H8WKE-implementation-plan.md)

## Description

Implement phần lõi của spec — **cap per-rule + redistribution**, theo thuật toán canonical:

1. **`etc/sales.xml`** — quote total collector `max_discount_cap` instance `MaxDiscountCap` **sort_order 310** (sau SalesRule `discount` 300, trước `shipping` 350 — DEC-FEATJKZM68-001 §1).

2. **`MaxDiscountCap::collect()`** — mirror pattern item-iteration của native `Discount::collect()`:
   - Lấy items của mọi address (`getAllAddresses` → `getAllItems`, filter `noDiscount`/parent như native L197).
   - Đọc per-rule per-item breakdown từ `item.extension_attributes.discounts[]`; chỉ xử lý rule có entry breakdown (eligibility native quyết định — không viết lại).
   - Với mỗi capped rule (`CapResolver`): `simple_action == by_percent` && `maximum_discount_amount > 0` (runtime guard — bảo vệ cả rule đổi action).
   - `N = Σ base_amount`; `N ≤ cap` → no-op (kể cả `==`).
   - `factor = cap / N` → scale 4 field DiscountData (amount/base/original/base_original) + `item.discount_amount/base_discount_amount` adjust theo delta; KHÔNG đụng `discount_percent`.
   - Cập nhật aggregates: `Total` + `Address` discount amounts (dấu âm như native), `subtotal_with_discount`/`base_...` recompute, item breakdown entry + address extension attributes breakdown rebuild (mirror `aggregateDiscountPerRule`).

3. **`CapResolver`** — load rule cap theo rule_id, **cache per collect pass** (không query per item); chỉ load khi có capped candidate trong breakdown/applied_rule_ids.

4. **`LargestRemainderAllocator`** — LRM cho 2 chuỗi **độc lập** base + display: floor/round theo `PriceCurrencyInterface::round()` (currency precision — VND 0 decimals), remainder phân theo fractional part lớn nhất, **tie-break item_id ASC** (deterministic). KHÔNG hard-code VND integer.

5. **`fetch()`** — không thêm total row riêng (cap nằm trong total `discount` native).

**Idempotency (spec §11):** collector không persist state riêng; mọi mutation nằm trên item/address object sẽ bị native reset ở pass sau → deterministic function của quote state. KHÔNG session state, KHÔNG accumulate.

## Mini Spec

> Embedded Mini-Spec (identity = TASK-5H8WKE). Behavioral contract của slice này; thuật toán canonical thuộc [SPEC-FEAT-JKZM68](../specs/SPEC-FEAT-JKZM68-promotion-max-discount.md) §4–§11, kiến trúc thuộc [DEC-FEATJKZM68-001](../records/decisions/DEC-FEATJKZM68-001.md) §1/§2/§4/§6.

### Goal

Cap engine của FEAT-JKZM68: quote total collector `max_discount_cap` (sort 310, config-only qua `etc/sales.xml`) áp **per-rule maximum discount cap** lên kết quả native SalesRule — đọc per-rule per-item breakdown native, khi Σ contribution của rule vượt cap thì scale-down + redistribute theo contribution (LRM), **không đụng native calculation, không modify core**.

### Expected Behavior

- Rule `by_percent` + cap > 0 + Σ native base > cap → **Σ final eligible base == cap** và **Σ final display == cap** (mỗi chuỗi round theo currency precision); phân bổ theo **contribution** (item native 40k/60k, cap 50k → 20k/30k — không theo row_total).
- Cap NULL/0, hoặc Σ ≤ cap (kể cả ==), hoặc `simple_action ≠ by_percent` tại thời điểm collect → **identical native** (totals không đổi).
- Idempotent: `collectTotals()` lặp lại với quote không đổi → kết quả identical; collector không persist state riêng (native reset mỗi pass là cơ sở).
- ≥ 2 capped rules độc lập — mỗi rule ≤ cap riêng; rule capped + non-capped cùng chạy đúng; `stop_rules_processing` semantics giữ nguyên.
- `address.extension_attributes.discounts[]` (per-rule breakdown hiển thị qua REST/GraphQL totals) phản ánh số đã cap; `fetch()` không thêm total row riêng (cap nằm trong total `discount` native).
- Shipping discount / free shipping của capped rule **không bị cap**, không tính vào N; downstream order/invoice/creditmemo untouched (native pure allocation).

### Constraints / Rules

- KHÔNG modify `vendor/`; hook config-only (`etc/sales.xml` sort 310 — sau discount 300, trước shipping 350/tax 450) — DEC-FEATJKZM68-001 §1.
- Nguồn contribution = `item.extension_attributes.discounts[]` **native** (set trước `minFix()`); eligibility không viết lại — item eligible của rule = item có breakdown entry (native chỉ ghi amount > 0).
- **Scale-down only:** mọi delta ≤ 0 → minFix invariant (`item discount ≤ itemPrice × qty`) không bao giờ bị phá.
- 2 chuỗi **base + display độc lập**, không convert; round qua `PriceCurrencyInterface::round()` theo currency precision (VND 0 decimals — KHÔNG hard-code integer); LRM tie-break **item_id ASC** (deterministic).
- Guard runtime: `simple_action` kiểm tại thời điểm calc; `(float)cap ≤ 0` → no-op; không đụng `discount_percent`.
- Perf: rule load 1 lần / capped-rule / pass (`CapResolver` cache per pass) — không query trong vòng item.
- Tier-2 pricing (checkout-critical): TL code review + QC e2e OSC (TASK-HPK1WZ) trước production.

### Out of Scope

Admin form field (TASK-67GGPR) · integration/end-to-end tests đầy đủ (TASK-4HYX6Y) · QC matrix + docs (TASK-HPK1WZ) · cap cho action ≠ `by_percent` · shipping cap · multishipping full QC (smoke only — OSC single-shipment là primary path).

### Acceptance Criteria

- [x] **AC-1 (Wiring):** collector chạy đúng vị trí 310 (verify thứ tự qua totals config); fetch không thêm row totals mới; module disable → totals về native. *(sorted collector list: discount(300) → max_discount_cap(310) → shipping(350); không có fetch(); di:compile GREEN. Module-disable runtime cycle: registration config-only — để QC/TASK-HPK1WZ, disclosed)*
- [x] **AC-2 (Cap đúng):** N > cap → Σ final eligible base == cap **và** Σ final display == cap (đã round theo precision); factor áp trên contribution KHÔNG theo row_total (spec AC-004: 40k/60k → 20k/30k). *(smoke VND: A=20k B=30k base+display, address −50k; unit testCapScalesBothChains)*
- [x] **AC-3 (No-op):** cap NULL/0, hoặc N ≤ cap, hoặc simple_action ≠ by_percent → kết quả identical native. *(unit ×3 điều kiện + smoke Case B: totals identical native khi cap NULL)*
- [x] **AC-4 (LRM invariant):** unit tests chứng minh `Σ rounded == cap` cho bộ case sinh tự động (n items × factor × precision 0 và 2 decimals); tie-break deterministic theo item_id ASC. *(24 generated cases + tie-break test — result ≤ native asserted cùng lúc)*
- [x] **AC-5 (Multi-rule):** ≥ 2 capped rules độc lập — mỗi rule ≤ cap riêng; capped + non-capped rule cùng chạy đúng; `stop_rules_processing` semantics giữ nguyên. *(unit testCappedAndUncappedRulesAreIndependent — rule uncapped không bị đụng; stop_rules: native quyết định breakdown trước khi cap thấy)*
- [x] **AC-6 (Idempotency):** gọi collect 3 lần liên tiếp với quote không đổi → kết quả identical. *(smoke ×3 identical engine-level + unit determinism test)*
- [x] **AC-7 (Guards):** mọi delta ≤ 0 (natives on-grid → LRM không vượt native — proven trong generated cases); guard `simple_action` + `(float)cap ≤ 0` tại resolver query; breakdown amount=0 không tồn tại (native chỉ set khi > 0).*
- [x] **AC-8 (Downstream untouched):** sweep grep — 0 ghi shipping field, 0 reference Order/Invoice/Creditmemo trong Model/.*
- [x] **AC-9 (Unit tests):** 38 tests / 849 assertions GREEN (exit 0; 1 benign deprecation); existing Secomm suites 132/354 OK cùng bootstrap. Run command trong evidence. *(round 2 — 2026-08-21: 40/858 GREEN; all-Secomm 217/1325 OK)*
- [x] **AC-10 (Perf):** resolver gọi đúng 1 lần trong collect() (ngoài item loop); empty ids skip query hoàn toàn (unit test). Resolver stateless sau khi fix memo-leak.*

## AI Pre-review (AGENTS §8.3) — Round 2, 2026-08-21 (độc lập — đối chiếu vendor native)

Kết quả: **NEEDS FIX → đã fix toàn bộ trong session**, repro tests kèm theo. Chi tiết bảng findings/fixes/bằng chứng vendor: [TASK-5H8WKE-evidence](../runtime/evidence/TASK-5H8WKE/TASK-5H8WKE-evidence.md) mục "AI Pre-review round 2".

- [x] **C1 (Critical, fixed):** rebuild address breakdown trước giờ chỉ từ items bị cap → rule uncapped trên item khác mất entry/thiếu contribution trong `address.extension_attributes.discounts[]` (vi phạm AC-5 display path). Fix: rebuild từ mọi shipping-assignment items, mirror `$itemsAggregate` native. Repro: `testAddressBreakdownKeepsEntriesOfItemsUntouchedByCap`.
- [x] **C2 (Critical, fixed):** `getPriceFormat(null, null, $store)` — tham số 3 bị bỏ qua (method chỉ nhận 2 args) → precision theo request scope thay vì store của quote (sai grid ở admin/CLI/API context). Fix: truyền `base_currency_code`/`quote_currency_code` tường minh, 2 chuỗi 2 precision. Repro: `testPrecisionIsResolvedFromQuoteCurrenciesNotRequestScope`.
- [x] **W1 (fixed):** resolver giờ `addFieldToSelect` đúng 3 cột như plan §1.2 (bỏ blobs serialized).
- [x] **W2 (fixed):** converter plugins `around` → `after` (behavior-identical, theo chuẩn 7.2).
- [x] Sau fix: module **40 tests / 858 assertions GREEN**; all-Secomm unit dirs **217/1325 OK**; `.gitkeep` leftovers dọn.
- Còn mở cho TL: N2 (LRM ±1 đơn vị nhỏ nhất ở float edge — bounded, accepted), `composer.lock.bk` ở repo root đừng commit.

## AI Pre-review (AGENTS §8.3) — 2026-08-20

- [x] Code matches approach (plan Task 1–9) — 7 file đúng placement; 5 design corrections từ vendor thật có lý do document (evidence "Corrections")
- [x] No changes outside requested scope — chỉ `Secomm_PromotionMaxDiscount/` + `.ai/` records; 0 file `vendor/` đổi (AC-5 sweep TASK-33J3RP pattern)
- [x] No hardcoded values — precision từ Locale Format (VND 0 verified, không hard-code); cap từ DB; không credentials/URLs
- [x] Error handling — defensive null-guards (breakdown null, ext attrs null, cap ≤ 0, N ≤ 0); fail loud đúng triết lý dev env (bug memo-leak BẮT được nhờ không nuốt lỗi)
- [x] No security surface mới — collector đọc internal quote state; không input trực tiếp từ user; resolver query qua Magento collection (SQL-injection safe)
- [x] Business rules respected — semantic NULL/0 unlimited; scale-down-only; shipping/downstream untouched (AC-8 sweep)
- [x] Tests — unit 38/849 GREEN (LRM generated 24 cases, guards, determinism) + runtime smoke engine-level; integration đầy đủ thuộc TASK-4HYX6Y
- [x] Performance — 1 query/capped-pass (unit proven empty-skip + single-call contract); không query trong item loop; collector work O(items × capped rules)
- [x] Regression risk: TRUNG BÌNH-CAO cho pricing (bản chất Tier-2) — no-op paths verified identical native; Mollie fee + Mageplaza collectors chạy sau 310 thấy số đã cap (wiring list); QC e2e OSC bắt buộc (TASK-HPK1WZ) trước production

## Risks

- **Tier-2 pricing, checkout-critical**: mọi thay đổi totals ảnh hưởng OSC/Mollie/VNPAY payment amount — QC e2e bắt buộc (TASK-HPK1WZ); TL code review Tier-2.
- minFix divergence (breakdown trước minFix): guard scale-down-only + unit test; nếu phát hiện case breakdown inconsistent nghiêm trọng → **không workaround** — escalate theo brief (document root cause + alternatives).
- Module 3rd party chạy collector 300–450 khác: kiểm tra conflict khi install extension mới (spec §14).
- Multishipping (D3 quote-level): implement theo native pattern; Launchpad OSC single-shipment — QC multishipping chỉ smoke.

## Related

- Depends: TASK-3R6X8E, TASK-33J3RP (done) · Mini-Spec: embedded `## Mini Spec` (ID TASK-5H8WKE) · Plan: [TASK-5H8WKE plan](../plans/TASK-5H8WKE-implementation-plan.md) (prospective — chờ TL Tier-2 approval để execute) · Spec: [SPEC-FEAT-JKZM68](../specs/SPEC-FEAT-JKZM68-promotion-max-discount.md) §4–§11, §13 · Decision: [DEC-FEATJKZM68-001](../records/decisions/DEC-FEATJKZM68-001.md) §1, §2, §4, §6
- Next: TASK-67GGPR (∥ TASK-4HYX6Y)
