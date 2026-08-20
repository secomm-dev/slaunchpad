# TASK-5H8WKE — Cap engine: MaxDiscountCap collector (sort 310) + CapResolver + LRM allocator

**Legacy ID:** SL-022 *(re-identified 2026-08-20 per DEC-027; mapping: .ai/toolkit/legacy-id-map.yaml)*

**Type:** Task (slice của FEAT-JKZM68 — core engine, phần rủi ro nhất)
**Priority:** High
**Estimate:** ~16–20h
**Mode:** A (Tier-2: quote totals pricing — AGENTS §9/§11/§12; spec-first + plan trước khi code)
**Placement:** `app/code/Secomm/PromotionMaxDiscount/{etc/sales.xml,Model/Quote/Address/Total/MaxDiscountCap.php,Model/Cap/,Model/Redistribution/,Test/Unit/}`
**Risk tier:** Tier 2 (pricing/quote totals — checkout-critical path)
**Author:** AI draft · **Date:** 2026-08-19 · **Status:** Proposed
**Specification:** SPEC-FEAT-JKZM68 (FULL, VALID) — [spec §4 Flow, §5 Hook, §7 Redistribution, §8 Rounding, §9–§11](../specs/SPEC-FEAT-JKZM68-promotion-max-discount.md)

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

## Acceptance Criteria

- [ ] **AC-1 (Wiring):** collector chạy đúng vị trí 310 (verify thứ tự qua totals config); fetch không thêm row totals mới; module disable → totals về native.
- [ ] **AC-2 (Cap đúng):** N > cap → Σ final eligible base == cap **và** Σ final display == cap (đã round theo precision); factor áp trên contribution KHÔNG theo row_total (spec AC-004: 40k/60k → 20k/30k).
- [ ] **AC-3 (No-op):** cap NULL/0, hoặc N ≤ cap, hoặc simple_action ≠ by_percent → kết quả identical native (assert bằng total comparison).
- [ ] **AC-4 (LRM invariant):** unit tests chứng minh `Σ rounded == cap` cho bộ case sinh tự động (n items × factor × precision 0 và 2 decimals); tie-break deterministic theo item_id ASC.
- [ ] **AC-5 (Multi-rule):** ≥ 2 capped rules độc lập — mỗi rule ≤ cap riêng; capped + non-capped rule cùng chạy đúng; `stop_rules_processing` semantics giữ nguyên.
- [ ] **AC-6 (Idempotency):** gọi collect 3 lần liên tiếp với quote không đổi → kết quả identical; unit test hàm pure + integration assert (nếu TASK-4HYX6Y chưa cover thì cover ở đây tối thiểu repeat-collect engine-level).
- [ ] **AC-7 (Guards):** minFix divergence — mọi delta ≤ 0, item discount không bao giờ vượt `itemPrice × qty`; guard `simple_action`; `(float)cap ≤ 0` → no-op; breakdown entry amount = 0 bị bỏ (native chỉ ghi amount > 0).
- [ ] **AC-8 (Downstream untouched):** KHÔNG ghi `shipping_discount_amount`/shipping fields; không modify downstream order/invoice/creditmemo logic (đã đảm bảo native — chỉ verify không override gì).
- [ ] **AC-9 (Unit tests):** LRM allocator, factor scaling, resolver guard, collector logic (mock items/breakdown) — Arrange-Act-Assert, happy + edge + error; chạy `vendor/bin/phpunit` green cùng existing suite.
- [ ] **AC-10 (Perf):** không N+1 — rule load 1 lần/capped-rule/pass; không query trong vòng item.

## Out of Scope

Admin form field (TASK-67GGPR) · integration/end-to-end tests đầy đủ (TASK-4HYX6Y) · cap cho action khác `by_percent` · shipping cap.

## Risks

- **Tier-2 pricing, checkout-critical**: mọi thay đổi totals ảnh hưởng OSC/Mollie/VNPAY payment amount — QC e2e bắt buộc (TASK-HPK1WZ); TL code review Tier-2.
- minFix divergence (breakdown trước minFix): guard scale-down-only + unit test; nếu phát hiện case breakdown inconsistent nghiêm trọng → **không workaround** — escalate theo brief (document root cause + alternatives).
- Module 3rd party chạy collector 300–450 khác: kiểm tra conflict khi install extension mới (spec §14).
- Multishipping (D3 quote-level): implement theo native pattern; Launchpad OSC single-shipment — QC multishipping chỉ smoke.

## Related

- Depends: TASK-3R6X8E, TASK-33J3RP · Spec: [SPEC-FEAT-JKZM68](../specs/SPEC-FEAT-JKZM68-promotion-max-discount.md) §4–§11, §13 · Decision: [DEC-FEATJKZM68-001](../records/decisions/DEC-FEATJKZM68-001.md) §1, §2, §4, §6
- Next: TASK-67GGPR (∥ TASK-4HYX6Y)
