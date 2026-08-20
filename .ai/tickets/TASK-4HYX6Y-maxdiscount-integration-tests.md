# TASK-4HYX6Y — Integration tests: quote matrix (A–D) + lifecycle (E) + idempotency

**Legacy ID:** SL-024 *(re-identified 2026-08-20 per DEC-027; mapping: .ai/toolkit/legacy-id-map.yaml)*

**Type:** Task (slice của FEAT-JKZM68 — verification tầng system)
**Priority:** High
**Estimate:** ~16–24h
**Mode:** A (Tier-2: order/invoice/creditmemo lifecycle verification — L3 System validation theo AGENTS §8.6)
**Placement:** `app/code/Secomm/PromotionMaxDiscount/Test/Integration/`
**Risk tier:** Tier 2 (order lifecycle)
**Author:** AI draft · **Date:** 2026-08-19 · **Status:** Proposed
**Specification:** SPEC-FEAT-JKZM68 (FULL, VALID) — [spec §15 AC, §16 Test strategy, §12 Lifecycle](../specs/SPEC-FEAT-JKZM68-promotion-max-discount.md)

## Description

Integration tests (Magento TestFramework, DB test thật) theo matrix tối thiểu của brief — ánh xạ trực tiếp spec §16. Fixture builder dựng quote + rules theo case; assert **base + display** chains.

**A. Đơn item:** 1 eligible, discount < cap (no-op, identical native) · 1 eligible, discount > cap (final = cap).

**B. Multi items:** nhiều eligible (khác giá, khác qty, có `discount_step`/`discount_qty`) · mixed eligible/ineligible — ineligible không bị scale, contribution = 0 · item native 40k + 60k cap 50k → 20k/30k (proportional contribution, KHÔNG row_total) · configurable product (child price calc) · bundle product (dynamic price, children-calculated path qua `aggregateDiscountBreakdown`).

**C. Multi rules:** 2 rules thường · 2 capped (độc lập per-rule) · capped + non-capped · `stop_rules_processing` · coupon-based rule · automatic rule.

**D. Config/recompute:** tax incl/excl × catalog price incl/excl (≥ 2 cấu hình) · currency precision 0 (VND) và 2 decimals (fixture USD store) — LRM invariant từng chuỗi · add/remove item · change qty · change/remove coupon · repeat `collectTotals()` × 3 identical · login/logout + customer group change (nếu fixture khả thi, minimum group switch).

**E. Lifecycle:** place order → **full invoice · partial invoice · multiple partial invoices · full credit memo · partial credit memo · refund by item · partial quantity refund** — assert: order item discount == quote item capped; `Σ invoiced ≤ order item discount`; `Σ refunded ≤ discount invoiced`; mỗi document = allocation từ order item (không re-run rule — verify bằng cách đổi rule/disable rule sau place order rồi invoice → số không đổi).

## Acceptance Criteria

- [ ] **AC-1:** Toàn bộ case A–E pass (`vendor/bin/phpunit` integration suite) — không flaky (repeat run 3×).
- [ ] **AC-2:** Mỗi case assert invariant `Σ final eligible == min(Σ native, cap)` trên base + display (spec AC-006) trừ case no-op assert identity với native.
- [ ] **AC-3:** Lifecycle E assert đầy đủ 7 kịch bản invoice/refund với invariants allocation (spec AC-013) — kể cả multi partial invoices lẻ tiền (LRM remainder xuyên chuỗi).
- [ ] **AC-4:** Repeat collectTotals × 3 identical (float compare theo currency precision — không naive `==` trên float nhị phân).
- [ ] **AC-5:** Test đổi rule sau place order (disable + sửa cap) → invoice/creditmemo numbers KHÔNG đổi (chứng minh downstream không re-run rule).
- [ ] **AC-6:** Evidence: test output + summary lưu `.ai/evidence/` theo evidence-policy; coverage gap (nếu case không fixture được) đánh dấu rõ, KHÔNG bỏ im lặng.

## Out of Scope

Manual QC/OSC e2e (TASK-HPK1WZ) · performance benchmark (chỉ assert không N+1 ở unit TASK-5H8WKE) · multishipping đầy đủ (smoke trong TASK-HPK1WZ nếu TL yêu cầu).

## Risks

- MFTF/integration fixtures tốn thời gian (product + rule + tax config matrix) — budget ⅓ thời gian cho fixture builder tái sử dụng.
- Bundle dynamic-price breakdown qua parent aggregation là path phức tạp nhất — nếu phát hiện native breakdown không đủ thông tin redistribute đúng **không workaround**: escalate root-cause theo brief + spec §13.
- Test DB currency/tax config ảnh hưởng chéo — isolate bằng fixture riêng per group.

## Related

- Depends: TASK-5H8WKE (engine), TASK-33J3RP (schema) · Spec: [SPEC-FEAT-JKZM68](../specs/SPEC-FEAT-JKZM68-promotion-max-discount.md) §12, §15, §16 · Decision: [DEC-FEATJKZM68-001](../records/decisions/DEC-FEATJKZM68-001.md) §6
- QC manual: TASK-HPK1WZ
