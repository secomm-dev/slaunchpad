# TASK-4HYX6Y — Integration tests: quote matrix (A–D) + lifecycle (E) + idempotency

**Legacy ID:** SL-024 *(re-identified 2026-08-20 per DEC-027; mapping: .ai/toolkit/legacy-id-map.yaml)*

**Type:** Task (slice của FEAT-JKZM68 — verification tầng system)
**Priority:** High
**Estimate:** ~16–24h
**Mode:** A (Tier-2: order/invoice/creditmemo lifecycle verification — L3 System validation theo AGENTS §8.6)
**Placement:** `app/code/Secomm/PromotionMaxDiscount/Test/Integration/`
**Risk tier:** Tier 2 (order lifecycle)
**Author:** AI draft · **Date:** 2026-08-19 · **Status:** Dev complete *(2026-08-21: TL chốt Option 2 engine-level trong chat → execute Task 2–8 một session. **24 tests / 121 assertions GREEN, repeat ×3 identical, residue mỗi run = 0.** Groups A (3) · B (3 + 1 skipped-gap) · C (4) · D (6 — idempotent ×3, two-precision VND/USD, coupon, group switch) · E (7/7 lifecycle kịch bản incl. đổi-rule-sau-place-order numbers không đổi). 2 bug fixture fix + 1 test-design fix trong session. **Coverage gaps disclosed (AC-6): configurable/bundle composite (MSI fixture infra — escalate TL), tax matrix (QC env), real FX rates (synthetic parity thay thế).** Chờ TL review Tier-2. Evidence: [TASK-4HYX6Y-evidence](../runtime/evidence/TASK-4HYX6Y/TASK-4HYX6Y-evidence.md))*
**Specification:** SPEC-FEAT-JKZM68 (FULL, VALID) — [spec §15 AC, §16 Test strategy, §12 Lifecycle](../specs/SPEC-FEAT-JKZM68-promotion-max-discount.md)

## Description

Integration tests (Magento TestFramework, DB test thật) theo matrix tối thiểu của brief — ánh xạ trực tiếp spec §16. Fixture builder dựng quote + rules theo case; assert **base + display** chains.

**A. Đơn item:** 1 eligible, discount < cap (no-op, identical native) · 1 eligible, discount > cap (final = cap).

**B. Multi items:** nhiều eligible (khác giá, khác qty, có `discount_step`/`discount_qty`) · mixed eligible/ineligible — ineligible không bị scale, contribution = 0 · item native 40k + 60k cap 50k → 20k/30k (proportional contribution, KHÔNG row_total) · configurable product (child price calc) · bundle product (dynamic price, children-calculated path qua `aggregateDiscountBreakdown`).

**C. Multi rules:** 2 rules thường · 2 capped (độc lập per-rule) · capped + non-capped · `stop_rules_processing` · coupon-based rule · automatic rule.

**D. Config/recompute:** tax incl/excl × catalog price incl/excl (≥ 2 cấu hình) · currency precision 0 (VND) và 2 decimals (fixture USD store) — LRM invariant từng chuỗi · add/remove item · change qty · change/remove coupon · repeat `collectTotals()` × 3 identical · login/logout + customer group change (nếu fixture khả thi, minimum group switch).

**E. Lifecycle:** place order → **full invoice · partial invoice · multiple partial invoices · full credit memo · partial credit memo · refund by item · partial quantity refund** — assert: order item discount == quote item capped; `Σ invoiced ≤ order item discount`; `Σ refunded ≤ discount invoiced`; mỗi document = allocation từ order item (không re-run rule — verify bằng cách đổi rule/disable rule sau place order rồi invoice → số không đổi).

## Acceptance Criteria

- [x] **AC-1:** *(24/121 GREEN; ×3 identical; residue 0 — 1 test skipped = gap-flag composite theo AC-6)* Toàn bộ case A–E pass (`vendor/bin/phpunit` integration suite) — không flaky (repeat run 3×).
- [x] **AC-2:** *(assertAmountEquals theo precision xuyên suốt; no-op assert identity native)* Mỗi case assert invariant `Σ final eligible == min(Σ native, cap)` trên base + display (spec AC-006) trừ case no-op assert identity với native.
- [x] **AC-3:** *(7/7 kịch bản: full/partial/multi-partial invoice, full CM, refund-by-item, partial-qty 300k×¼, rule-change-after-place)* Lifecycle E assert đầy đủ 7 kịch bản invoice/refund với invariants allocation (spec AC-013) — kể cả multi partial invoices lẻ tiền (LRM remainder xuyên chuỗi).
- [x] **AC-4:** *(×3 identical + two-precision chain test — tolerance ½ unit theo precision, không naive float ==)* Repeat collectTotals × 3 identical (float compare theo currency precision — không naive `==` trên float nhị phân).
- [x] **AC-5:** *(disable rule + cap→9M bằng SQL sau place order → Σ invoices vẫn == order discount)* Test đổi rule sau place order (disable + sửa cap) → invoice/creditmemo numbers KHÔNG đổi (chứng minh downstream không re-run rule).
- [x] **AC-6:** *(evidence đầy đủ; 4 gaps liệt kê rõ trong evidence — composite/tax/FX/login-full, không bỏ im lặng)* Evidence: test output + summary lưu `.ai/evidence/` theo evidence-policy; coverage gap (nếu case không fixture được) đánh dấu rõ, KHÔNG bỏ im lặng.

## Out of Scope

Manual QC/OSC e2e (TASK-HPK1WZ) · performance benchmark (chỉ assert không N+1 ở unit TASK-5H8WKE) · multishipping đầy đủ (smoke trong TASK-HPK1WZ nếu TL yêu cầu).

## Risks

- MFTF/integration fixtures tốn thời gian (product + rule + tax config matrix) — budget ⅓ thời gian cho fixture builder tái sử dụng.
- Bundle dynamic-price breakdown qua parent aggregation là path phức tạp nhất — nếu phát hiện native breakdown không đủ thông tin redistribute đúng **không workaround**: escalate root-cause theo brief + spec §13.
- Test DB currency/tax config ảnh hưởng chéo — isolate bằng fixture riêng per group.

## Related

- Depends: TASK-5H8WKE (engine), TASK-33J3RP (schema) · Spec: [SPEC-FEAT-JKZM68](../specs/SPEC-FEAT-JKZM68-promotion-max-discount.md) §12, §15, §16 · Decision: [DEC-FEATJKZM68-001](../records/decisions/DEC-FEATJKZM68-001.md) §6
- QC manual: TASK-HPK1WZ
