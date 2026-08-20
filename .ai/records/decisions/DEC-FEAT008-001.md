---
id: DEC-FEAT008-001
title: 'Promotion Max Discount — cap engine qua custom quote total collector sort 310 (sau SalesRule discount 300); per-rule breakdown native làm nguồn contribution; column maximum_discount_amount trên bảng salesrule; redistribution largest-remainder theo contribution; module boundary Secomm_Promotion (base tối giản) + Secomm_PromotionMaxDiscount (feature)'
status: accepted
owners: [sa, tl]
decision_type: architecture
approval_date: 2026-08-19
created: 2026-08-19
last_verified: 2026-08-19
verified_against_commit: d871a85f
supersedes: []
superseded_by:
work_items: [FEAT-008]
---

# Decision Record: Promotion Max Discount architecture (hook point, data model, redistribution, module boundary)

<!-- CANONICAL DECISION STORE. ACCEPTED 2026-08-19 — approved by user acting as TL (chat "Approved", Level 2). Naming per-work-item (Entry h): DEC-FEAT008-001. -->
<!-- Index pointer: `.ai/project-context/memory/DECISIONS.md` -->
<!-- Full analysis: `.ai/specs/SPEC-FEAT-008-promotion-max-discount.md` (18 mục, verified source 2.4.8-p5). -->

## Context

Merchant cần Cart Price Rule **"Giảm X%, tối đa Y VND"** — Magento native `by_percent` không có cap (verified trên 2.4.8-p5 source: `ByPercent::calculate()` chỉ `min(100, percent)`; bảng `salesrule` không có field cap). Self-build đã quyết định; feature thuộc Launchpad Core, backend-first/theme-agnostic, `Secomm_Promotion` là foundation cho nhóm Promotion. Tier-2 (quote totals pricing + order/invoice/creditmemo lifecycle — AGENTS §9/§11/§12) → Level-2 architecture decision.

## Decision (approved 2026-08-19, kèm 3 open decisions của spec resolved theo khuyến nghị)

1. **Hook point (spec D1): custom quote total collector sort_order 310** — module `Secomm_PromotionMaxDiscount` khai báo qua `etc/sales.xml`, chạy ngay sau SalesRule `discount` (300), trước `shipping` (350)/`tax_shipping` (375)/`shipping_discount` (400)/`tax` (450)/`grand_total` (550). Config-only, zero core modification. Bị loại: event `salesrule_validator_process` (per rule×item, không thấy Σ rule); plugin/preference trên `Discount`/`RulesApplier`/`ByPercent` (xâm nhập core, fragile upgrade); collector sau tax (450 — tax đã tính trên discount chưa cap).

2. **Nguồn contribution: per-rule per-item breakdown native** — `item.extension_attributes.discounts[]` do `RulesApplier::setDiscountBreakdown()` ghi (RuleDiscount{rule_id, DiscountData{amount, base_amount, original_amount, base_original_amount}}). Không viết lại eligibility/aggregation của Magento. Guard: breakdown được set **trước** `minFix()` → chỉ scale **down** (mọi delta ≤ 0) nên invariant minFix giữ nguyên.

3. **Data model (spec D2): column `maximum_discount_amount` DECIMAL(12,4) UNSIGNED NULL** trên bảng `salesrule` qua declarative schema của module + `db_schema_whitelist.json`. Semantic: NULL/0 = unlimited (native behavior); >0 = cap **base-currency** product discount. **Extension attribute cho `Magento\SalesRule\Api\Data\RuleInterface`** được include Phase 1 (mapping qua plugin trên `RuleRepository`) — chống REST API-save bỏ field.

4. **Redistribution: proportional theo native contribution + largest remainder (LRM)** — factor = cap / Σ native per-rule; scale cả 4 field của DiscountData; chạy **độc lập 2 chuỗi** base + display (native tính song song, không convert); round bằng `PriceCurrencyInterface::round()` theo currency precision (VND 0 decimals, không hard-code integer); tie-break item_id ASC (deterministic). Invariant: `Σ final eligible items == min(Σ native, cap)` trên cả hai chuỗi.

5. **Module boundary:** `Secomm_Promotion` = base **tối giản** (module.xml/registration/README/CHANGELOG, không code — không over-engineer cho feature chưa tồn tại); `Secomm_PromotionMaxDiscount` = toàn bộ business logic, dependency `Secomm_Promotion` + `Magento_SalesRule`. Shared contracts chỉ rút lên base khi có promotion module thứ hai.

6. **Lifecycle & idempotency:** cap mutate in-memory trước persist → quote_item/order_item copy native qua fieldset `to_order_item_discount`; invoice/creditmemo = native pure allocation (không custom code downstream, không re-run rule). Idempotency kế thừa native reset (`Discount::collect()` L169–185 reset item discount + breakdown mỗi pass); collector không persist state riêng. **D3 multishipping:** quote-level per-rule cap (mỗi pass native tính items của mọi address — mirror pattern).

7. **Admin UX:** field `Maximum Discount Amount` trong Actions fieldset qua plugin sau `Metadata\ValueProvider::getMetadataValues()` (KHÔNG override `sales_rule_form.xml` core) + JS component switch theo `simple_action` (visible chỉ khi by_percent); runtime guard trong collector kiểm `simple_action` tại thời điểm calc (bảo vệ khi đổi action, field còn giá trị trong DB).

## Alternatives considered

- **Observer `salesrule_validator_process`** — fires per (rule, item) trước khi biết Σ rule → không thể cap + redistribute đúng. Bị loại.
- **Plugin/preference core SalesRule** — vi phạm "không modify core" về tinh thần, fragile khi upgrade. Bị loại.
- **Collector sau tax (450+)** — tax tính trên discount chưa cap → sai tax config. Bị loại.
- **Bảng riêng keyed by rule_id** thay vì column trên `salesrule` — thêm join, phức tạp hóa persist/load native (loadPost/DataProvider full-data path mất tự động); column qua declarative merge là pattern chuẩn. Bị loại.
- **Remainder cho item cuối deterministic** thay LRM — đơn giản hơn nhưng méo relative contribution; LRM chỉ phức tạp nhẹ, ưu tiên giữ tỉ lệ. Bị loại.
- **Commercial promotion extension** — out per build-vs-buy decision.

## Consequences

- Column trên bảng core `salesrule`: an toàn upgrade (declarative schema), cần whitelist; rủi ro module 3rd party khác mutate discount sau collector 300 → theo dõi khi install extension mới (spec §14).
- Cap engine chỉ áp `by_percent`; các action khác (by_fixed/cart_fixed/buy_x_get_y) ngoài scope feature này.
- Decompose thành SL-020..SL-025 (spec §17); estimate SA 0.5–1d · Dev 6–8d · QC 2–3d (spec §18).

## References

- Spec: [SPEC-FEAT-008](../../specs/SPEC-FEAT-008-promotion-max-discount.md) (VALID 2026-08-19)
- Feature record: [FEAT-008](../features/FEAT-008.md)
- Tickets: SL-020 (scaffold) · SL-021 (data model) · SL-022 (cap engine) · SL-023 (admin UI) · SL-024 (integration tests) · SL-025 (QC + docs)
- Source evidence: `vendor/magento/module-sales-rule/Model/{Quote/Discount,RulesApplier,Utility,Validator,Rule/Metadata/ValueProvider}.php` (2.4.8-p5)
