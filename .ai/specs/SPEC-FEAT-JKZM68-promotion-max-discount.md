# Spec: Secomm Promotion Max Discount — Maximum Discount Cap cho Cart Price Rule (by_percent)

Specification ID: SPEC-FEAT-JKZM68
Feature ID: FEAT-JKZM68
Specification Level: FULL

> **Status:** VALID — approved 2026-08-19 bởi user acting as TL (chat "Approved"); open decisions D1–D3 resolved theo khuyến nghị spec → [DEC-FEATJKZM68-001](../records/decisions/DEC-FEATJKZM68-001.md).
> **Mode A** · Tier 2 (quote totals pricing, order/invoice/creditmemo lifecycle) · Build-vs-Buy: **self-build** (đã quyết định).
> Phiên bản Magento được inspect: **2.4.8-p5** (source thật trong `vendor/`, mọi claim hook-point đều có file:line ở §5).

---

# Purpose

Merchant cần cấu hình promotion dạng **"Giảm X%, tối đa Y VND"** cho Cart Price Rule. Magento native hỗ trợ `by_percent` nhưng **không có khả năng cap tổng tiền discount** của một rule (verified: `ByPercent::calculate()` chỉ `min(100, percent)`; `salesrule` schema không có field cap — §1, §2). Feature bổ sung **per-rule maximum monetary cap** cho `simple_action = by_percent`, backend-first, theme-agnostic, không modify core.

# Scope

- Module `Secomm_PromotionMaxDiscount` (feature, độc lập) + `Secomm_Promotion` (base tối giản — chỉ anchor/foundation, không business logic).
- Cap áp lên **tổng product discount contribution** của một Sales Rule trên các eligible quote items; redistribute phần bị cắt theo relative contribution; cap số tiền cuối cùng xuống quote → order item; invoice/creditmemo đi theo native allocation.
- Admin field `Maximum Discount Amount` trong Actions tab, chỉ active/visible khi `simple_action = by_percent`.

# Out of Scope

`by_fixed` · `cart_fixed` · `buy_x_get_y` cap · Multiple Coupon · Coupon Stacking · Global promotion cap · Tiered Discount · Gift With Purchase · Cross-product BOGO · **Maximum Shipping Discount** (shipping discount hoàn toàn ngoài cap) · Promotion Analytics · Promotion Sync · External Promotion Engine · frontend storefront UI riêng (dùng native totals render).

# Actors

Merchant (admin cấu hình rule) · SalesRule engine (Magento, không đổi) · Cap engine (`Secomm_PromotionMaxDiscount`, collector mới) · QC (test matrix §16) · Downstream documents (order/invoice/creditmemo — native).

---

# 1. Current Magento behavior (verified trên 2.4.8-p5)

**Totals collector order** (merge `etc/sales.xml` của module-quote / module-sales-rule / module-tax):

```
subtotal(100) → tax_subtotal(200) → discount(300, SalesRule) → shipping(350)
→ tax_shipping(375) → shipping_discount(400) → tax(450) → grand_total(550)
```

**Discount collect flow** (`vendor/magento/module-sales-rule/Model/Quote/Discount.php`):

1. `Discount::collect()` (sort 300) **reset toàn bộ item discount = 0** và clear per-item/per-rule breakdown (L169–185) — đây là cơ sở idempotency native.
2. Loop **2 tầng: rule (outer) × item (inner)** (L193–232): mỗi cặp (rule, item) gọi `Validator::process($item, $rule)` (L218). `stop_rules_processing` loại item khỏi các rule sau (L220–222).
3. `RulesApplier::applyRule()` → `getDiscountData()` (`Model/RulesApplier.php` L350–369), theo thứ tự:
   - **Calculator theo action** (`Rule/Action/Discount/ByPercent.php`): `amount = (qty × price − item.discountAmount) × pct` — discount rule sau tính trên giá **đã giảm** bởi rule trước (stacking); `rulePercent = min(100, …)`; `fixQuantity()` theo `discount_step`.
   - **Event `salesrule_validator_process`** (L512–534) — per (rule, item), mutable `result`.
   - **`deltaRoundingFix()`** (`Model/Utility.php` L166–210) — round theo **currency precision** (`PriceCurrencyInterface::round()`), có delta theo key `discountPercent`.
   - **`setDiscountBreakdown()`** (RulesApplier L381–406) — lưu **per-rule per-item delta** vào `item.extension_attributes.discounts[]` (`RuleDiscount{rule_id, rule_label, DiscountData{amount, base_amount, original_amount, base_original_amount}}`). ⚠️ Được gọi **trước** `minFix()`.
   - **`minFix()`** (Utility L141–157) — cap cumulative item discount tại `itemPrice × qty`. ⚠️ Sau breakdown ⇒ `Σ breakdown per-rule` **có thể >** `item.discount_amount` cuối (edge: % cao + nhiều rule).
   - `setDiscountData()` — `item.discount_amount` = cumulative sau minFix.
4. Bundle/children-calculated: discount tính trên parent rồi `distributeDiscount()` xuống children theo row ratio + rounding delta (RulesApplier L315–339).
5. Sau loop: `aggregateItemDiscount()` (total discount, dấu âm), `aggregateDiscountPerRule()` (L351–394) tổng hợp per-rule breakdown lên **`address.extension_attributes.discounts[]`** — nguồn hiển thị per-rule totals cho REST `V1/carts/totals` / GraphQL cart.
6. Shipping discount: collector riêng `ShippingDiscount` (sort 400) qua `Validator::processShippingAmount()` — **hoàn toàn tách** khỏi item discount.
7. Tax: `Validator::getItemPrice()` dùng `discount_calculation_price` — được chuẩn bị bởi `tax_subtotal` (sort 200, chạy **trước** discount) theo tax configuration; `getItemOriginalPrice()` qua `getTaxPrice()`. Base/display được tính **song song độc lập** rồi round riêng (không convert từ base).

# 2. Business gap

- Native `by_percent` không giới hạn tổng tiền: rule 20% trên cart 10.000.000 VND → discount 2.000.000 VND, không có cách nào cap ở 50.000 VND.
- `salesrule` table không có field cap (verified `db_schema.xml`); Mageplaza suite trong project không có promotion module (`project-context/09`).
- Merchant VN cần pattern "Giảm X% tối đa Y VND" — tiêu chuẩn khuyến mãi apparel/fashion.

# 3. Proposed module boundary

```
Secomm_Promotion                 (base — TỐI GIẢN, không business logic)
├── composer.json / registration.php / etc/module.xml
├── README.md / CHANGELOG.md     (per CODING_RULES [WARN] rule)
└── (không code)                 — anchor cho nhóm Promotion; KHÔNG over-engineer

Secomm_PromotionMaxDiscount      (feature — toàn bộ business logic nằm ở đây)
├── composer.json                (require: secomm/promotion, magento/module-sales-rule, magento/module-quote)
├── etc/module.xml               (sequence: Secomm_Promotion, Magento_SalesRule, Magento_Quote, Magento_Sales)
├── etc/db_schema.xml            (+ db_schema_whitelist.json) — column mới trên bảng `salesrule`
├── etc/sales.xml                — quote total collector `max_discount_cap`, sort_order 310
├── etc/adminhtml/di.xml         — plugin sau ValueProvider (admin field meta)
├── Model/Cap/…                  — rule cap resolver + redistribution allocator
├── Model/Quote/Address/Total/MaxDiscountCap.php — collector
├── Plugin/Adminhtml/…           — admin form field
├── view/adminhtml/web/js/form/element/max-discount-field.js — visibility switch theo simple_action
├── i18n/vi_VN.csv + en_US.csv
├── Test/Unit + Test/Integration
└── README.md / CHANGELOG.md
```

**Dependency đúng như yêu cầu:** `Secomm_Promotion` + `Magento_SalesRule`. Base module chỉ tồn tại làm foundation anchor — **không** tự tạo interface/famework chờ feature tương lai (YAGNI); khi promotion module thứ hai xuất hiện mới rút shared contracts lên base.

# 4. Technical flow (processing contract)

```
Magento native SalesRule calculation (Discount collector, sort 300 — KHÔNG đổi)
        ↓
MaxDiscountCap collector (sort 310) đọc per-rule per-item breakdown
        ↓
Aggregate per-rule discount (quote-level, eligible items)
        ↓
Rule có cap > 0 && simple_action = by_percent && Σ native > cap → apply cap
        ↓
Redistribute = factor × native per-item contribution (largest remainder)
        ↓
Cập nhật item.discount_amount/base + item & address breakdown extension attrs
        ↓
Collectors sau (shipping 350, tax 450, grand_total 550) thấy số đã cap → Finalize totals
```

Persist xuống `quote_item`/`quote_address` diễn ra tự nhiên khi quote save sau `collectTotals()` — collector chỉ mutate in-memory objects.

# 5. Hook-point analysis (7 câu hỏi của brief)

| # | Câu hỏi | Kết quả (verified) |
|---|---|---|
| 1 | Magento tính % discount ở đâu? | `Rule/Action/Discount/ByPercent::_calculate()` — `(qty×price − discount đã có) × pct`, giá từ `Validator::getItemPrice()` (tax-aware `discount_calculation_price`) |
| 2 | Discount attach vào quote item thế nào? | Cumulative `item.discount_amount/base_discount_amount` + **per-rule breakdown** `item.extension_attributes.discounts[]` (RulesApplier::setDiscountBreakdown) + `applied_rule_ids` |
| 3 | Aggregate discount của rule lấy ở đâu? | Σ các `DiscountData.amount/base_amount` của cùng `rule_id` trên mọi item (chính xác cơ chế `aggregateDiscountPerRule` dùng cho address-level) |
| 4 | Hook point phù hợp nhất? | **Custom quote total collector sort 310** (sau discount 300, trước shipping 350/tax 450) — xem bảng loại dưới |
| 5 | Custom collector sau SalesRule hay extension khác? | Collector sau SalesRule — vì cap cần Σ đầy đủ của rule (biết sau khi mọi item đã tính) |
| 6 | Tránh collector chạy nhiều lần gây accumulate? | Native `Discount::collect()` **reset item discount + breakdown = 0/null ở đầu mỗi pass** (L169–185) và collector cap **không persist state riêng** → mỗi pass tính lại trên native fresh state; không session state |
| 7 | Quote item → order/invoice/creditmemo? | Copy declarative qua fieldset `quote_convert_item` → `to_order_item_discount` (module-sales fieldsets, gọi trong `ToOrderItem`); invoice/creditmemo totals = **pure allocation** từ order item persisted (§12) |

**Các hook bị loại:**

- ❌ Event `salesrule_validator_process` — fires per (rule, item) **trước khi** biết Σ rule → không thể cap + redistribute đúng.
- ❌ Plugin/preference trên `Discount`/`RulesApplier`/`ByPercent` — xâm nhập core logic, fragile khi upgrade, vi phạm "không modify core" về tinh thần.
- ❌ Collector sau tax (450) — tax đã tính trên discount chưa cap → sai tax.
- ❌ Recalculate/order-side cap — downstream documents là allocation từ order item; can thiệp sau khi place order sai tầng.

**Điểm chốt chọn collector 310:** config-only (`etc/sales.xml`), zero core modification, mọi consumer sau (shipping, tax, grand total, fetch, GraphQL breakdown) đọc số đã cap; idempotency kế thừa từ reset của native.

# 6. Data model

**Chosen: extend Sales Rule entity qua declarative schema** — module khai báo column mới trên bảng `salesrule`:

```xml
<column xsi:type="decimal" name="maximum_discount_amount" scale="4" precision="12"
        unsigned="true" nullable="true" default="NULL"
        comment="Maximum Discount Amount (base currency); NULL/0 = unlimited; by_percent only"/>
```

- Declarative schema cho phép module ngoài thêm column vào core table (merge db_schema) — pattern chuẩn, không modify core code.
- **Semantic:** `NULL` / `0` = unlimited (native behavior); `> 0` = cap **base-currency** monetary product discount của rule (đơn vị giống `by_fixed` dùng `rule.getDiscountAmount()` làm base amount — Validator L466–468).
- **Persist tự nhiên:** admin save → `Save::execute()` → `$model->loadPost($data)` → `save()`; load → `AbstractDb::load` map mọi column; form data → `DataProvider::getData()` trả `$rule->getData()` full → field tự có trong form data, không cần PHP glue cho data path.
- **Repository/API:** `RuleRepository` dùng `extensionAttributesJoinProcessor` — **Open Decision D2** (xem cuối spec): có thêm `extension_attributes.xml` mapping cho `RuleInterface` ngay Phase 1 (recommend: có — tránh API-save path bỏ qua field) hay defer.

# 7. Redistribution algorithm

Per rule R (điều kiện: `simple_action = by_percent` && `maximum_discount_amount > 0`):

```
native_i  = DiscountData.base_amount của rule R trên item i   (eligible items only —
             chính là các item có breakdown entry cho R; eligibility do native quyết định)
N         = Σ native_i                          (base chain — authoritative)
cap       = rule.maximum_discount_amount        (base currency)

if N <= cap → no-op (giữ native result — kể cả N == cap)

factor    = cap / N                             (0 < factor < 1)
target_i  = native_i × factor                   (proportional theo CONTRIBUTION, không theo row_total)
final_i   = largestRemainderAllocate(target_i → cap)     (§8)
delta_i   = final_i − native_i                  (≤ 0)
item_i.discount_amount       += delta_i (display chain tương tự, đục độc lập)
item_i breakdown[R]           = final values (scale original_amount cùng factor)
```

- **Eligibility không viết lại:** item eligible với rule R = item có breakdown entry của R sau native pass (`setDiscountBreakdown` chỉ ghi khi amount > 0). Không re-validate conditions/actions.
- Children items (bundle/configurable price-calc children): breakdown được aggregate lên parent (RulesApplier::aggregateDiscountBreakdown L424–463) → cap thao tác trên **cùng tầng dữ liệu native đã phân bố** (parent-visible items), children giữ phân bố native ratio của parent.
- **minFix divergence guard** (§1 mục ⚠️): mọi delta ≤ 0 và item total chỉ giảm nên không bao giờ vượt `itemPrice × qty` — invariant native giữ nguyên; khi `N > Σ item.discount_amount` (do minFix) vẫn an toàn vì ta chỉ scale **down** từng contribution.

# 8. Rounding strategy

- Dùng `PriceCurrencyInterface::round()` — theo **currency precision của store** (VND = 0 decimals, USD = 2). KHÔNG hard-code VND integer.
- **Largest remainder method (LRM)** cho mỗi capped rule, chạy **độc lập 2 chuỗi** (base + display — native tính 2 chuỗi song song, không convert từ nhau):
  1. `floor` (hoặc `round`) từng `target_i` theo precision;
  2. `remainder = cap − Σ rounded_i`;
  3. phân remainder cho các item theo **fractional part lớn nhất**; tie-break **item_id ASC** (deterministic, không phụ thuộc iteration order).
- **Invariant (critical):** `Σ final eligible item discounts (rule R) == min(N, cap)` — trên cả base chain và display chain. Chứng minh bằng unit test exhaustive (bộ số générée, tổng luôn khớp tới cent/đồng).
- Fallback "remainder cho item cuối" KHÔNG dùng — LRM chỉ phức tạp hơn nhẹ và giữ relative contribution tốt hơn.

# 9. Tax behavior

- Cap engine **không tính tax riêng, không đụng price fields**. `by_percent` native đã discount trên `discount_calculation_price` được `tax_subtotal` (sort 200) chuẩn bị theo tax config (catalog prices incl/excl tax, discount tax setting). Cap chỉ scale **kết quả monetary** của rule → tự động đúng dưới mọi tax configuration (`_calculate` đã tách base/display tax-aware song song).
- Tax (sort 450) chạy **sau** cap 310 → tax tính trên discount đã cap, đúng theo hiện hành incl/excl configuration. Không hard-code catalog incl/excl.

# 10. Shipping behavior

- Cap = **product/item discount only**. Shipping discount (rule `apply_to_shipping = 1`, free shipping) nằm ở `ShippingDiscount` collector (400) + `processShippingAmount()` — cap collector **không đọc/ghi** các field `shipping_discount_amount`, không cộng shipping benefit vào N.
- Free shipping của capped rule tiếp tục theo native.

# 11. Idempotency strategy

- Mỗi pass `collectTotals()`: native `Discount::collect()` reset items (discount = 0, breakdown = null, L169–185) rồi tính lại từ đầu → collector cap đọc **native fresh state mỗi pass** và chỉ mutate in-memory. Không session state, không persist giữa các lần collect, không mutate input theo hướng làm pass sau giảm tiếp (mọi hàm của native state, pass sau tính lại như pass đầu).
- Multi-pass trong một collect (quote có virtual + shipping address, multishipping): mỗi pass của cap chạy trên state native vừa được reset/tính lại trong pass đó → kết quả pass cuối = hàm của quote state, deterministic.
- Trigger recompute: add/remove item, qty, coupon, login/logout, customer group, address — tất cả đi qua `collectTotals()` chuẩn của Magento (quote trigger triggers native) → không cần observer riêng.

# 12. Quote → Order → Invoice → Credit Memo lifecycle

- **Quote → Order:** `ToOrderItem` copy `discount_amount/base_discount_amount` (đã cap) qua fieldset `to_order_item_discount` → persist `sales_order_item`. Không cần custom code.
- **Invoice** (`Magento\Sales\Model\Order\Invoice\Total\Discount::collect()` — verified L41–69): per order item = `discount_amount − discount_invoiced`, chia theo qty cho partial, `isLast()` lấy phần còn dư. **Pure allocation — không re-run SalesRule.**
- **Creditmemo** (`...\Creditmemo\Total\Discount::collect()` — verified L80–110): `discount_invoiced − discount_refunded`, tương tự. **Pure allocation.**
- ⇒ Vì invoice/creditmemo chỉ phân bổ từ order-item discount persisted (đã cap), downstream **không được và không cần tính lại**; invariant `Σ invoiced/refunded ≤ discount persisted trên order/item` được đảm bảo bởi chính cơ chế native (invoiced/refunded chỉ cộng dồn allocation của order item).
- Yêu cầu verify lifecycle (full/partial invoice, multi invoice, full/partial refund, refund by item, partial qty refund) → test matrix §16 nhóm E.

# 13. Failure / edge cases

| Case | Behavior |
|---|---|
| Cap = NULL / 0 | Collector no-op — native behavior 100% |
| Σ native ≤ cap (kể cả == cap) | No-op — không redistribute không cần thiết |
| Rule đổi action sang by_fixed/cart_fixed…, cap còn giá trị trong DB | **Runtime guard:** collector chỉ áp khi `simple_action = by_percent` tại thời điểm collect — giá trị nằm yên, không ảnh hưởng calculation |
| Nhiều capped rules cùng chạy | Độc lập per-rule (mỗi rule tự N, tự cap). Rule A và B không chia sẻ cap |
| `stop_rules_processing` | Native loại item khỏi rule sau — breakdown phản ánh đúng → cap chỉ thấy contribution thật |
| Coupon-based vs automatic | Như nhau — cap không phân biệt coupon type |
| minFix divergence (Σ breakdown > item discount) | Chỉ scale down — an toàn (§7 guard) |
| Multi-currency (base ≠ display) | 2 chuỗi LRM độc lập; invariant từng chuỗi; rate neo theo native conversion |
| Multishipping | Mỗi pass native reset + tính lại; cap tính quote-level per rule theo pattern native (items của mọi address); aggregate theo shipping assignment như native `Discount::collect` |
| 100% discount rule + cap | Cap cắt về cap amount; `discount_percent` giữ native (informational); deltaRoundingFix 100%-check đã chạy trước cap |
| Bundle / configurable (children calculated) | Cap trên tầng parent-visible breakdown — phân bố children giữ ratio native |
| REST/API save rule không truyền field | **Risk D2** — extension attribute mapping (khuyến nghị Phase 1) |
| Cap âm / non-numeric từ POST | Admin validation `validate-number` + `validate-zero-or-greater`; runtime `(float) ≤ 0` → no-op |

# 14. Compatibility risks

| Risk | L | I | Mitigation |
|---|---|---|---|
| Custom column trên bảng core `salesrule` | L | M | Declarative schema chuẩn; không script migration thủ công; upgrade Magento không đụng cột custom |
| Admin form inject qua plugin `ValueProvider::getMetadataValues` | M | M | Không override `sales_rule_form.xml` (tránh copy 700 dòng core brittle theo upgrade); plugin after chỉ thêm key `actions.children.maximum_discount_amount` |
| Mageplaza OSC re-collect totals nhiều lần | M | M | Idempotency design §11; QC gọi riêng repeat collectTotals |
| REST `RuleRepository::save` bỏ field (D2) | M | M | Extension attribute mapping hoặc guard document; Launchpad quản lý rule qua admin |
| Module 3rd party khác cũng mutate discount sau 300 | L | H | Collector 310 chạy sớm nhất sau native; spec yêu cầu khảo sát conflict khi install extension mới (review §12) |
| Magento upgrade đổi collector order/flow | L | M | Hook là public extension point (`sales.xml` + extension attributes); theo dõi `12_UPGRADE_NOTES` |
| Hiệu năng: collector load rules để đọc cap | L | M | Rule cap resolver cache per-collect-pass; chỉ load khi có capped rule ids trong `applied_rule_ids`/breakdown |

Không modify Magento core · không phụ thuộc theme (admin UI component + backend collector) · không phụ thuộc checkout implementation (OSC/OSC Pro/OSC Ultimate cùng đi qua `collectTotals`) · hoạt động với automatic + coupon rules · **không đổi native behavior khi cap không configure** (collector no-op sớm).

# 15. Acceptance criteria

- [ ] AC-001: Rule `by_percent` + cap 50.000, eligible amount 500.000, 20% → final discount = 50.000; item discounts Σ = 50.000 (base + display).
- [ ] AC-002: Rule `by_percent` + cap, Σ native < cap → kết quả **bằng native** (byte-identical totals khi cap = NULL).
- [ ] AC-003: Σ native == cap → no-op, không redistribute.
- [ ] AC-004: Redistribution theo contribution: item A native 40.000 + item B 60.000, cap 50.000 → A = 20.000, B = 30.000 (không theo row_total).
- [ ] AC-005: Cap độc lập per rule; 2 capped rules cùng chạy → mỗi rule ≤ cap riêng.
- [ ] AC-006: `Σ final eligible items == min(N, cap)` sau LRM — mọi test case, cả base lẫn display, mọi currency precision test (0 và 2 decimals).
- [ ] AC-007: Empty/0 cap → native behavior (so sánh totals với module disabled).
- [ ] AC-008: Admin: field chỉ visible/enabled khi `simple_action = by_percent`; validation ≥ 0; đổi action → field ẩn và calculation không bị ảnh hưởng; note giải thích hiển thị; vi_VN + en_US.
- [ ] AC-009: Giá trị cap persist load/save đúng qua admin; rỗng → NULL.
- [ ] AC-010: Shipping discount/free shipping của capped rule không bị cap, không cộng vào N.
- [ ] AC-011: Tax incl/excl config khác nhau → cap đúng monetary discount thực tế của rule (so khớp totals native-vs-capped theo từng config).
- [ ] AC-012: Repeat `collectTotals()` × 3 với quote không đổi → totals identical (assert float equality per currency precision).
- [ ] AC-013: Order item discount == quote item capped; full/partial/multi invoice + full/partial/item/qty refund: `Σ invoiced ≤ order discount`, `Σ refunded ≤ discount invoiced` — toàn AllocationFromOrder, không re-run rule.
- [ ] AC-014: Automatic rule và coupon rule cap giống nhau; `stop_rules_processing` giữ semantics.
- [ ] AC-015: Không modify file nào trong `vendor/`; module enable/disable không ảnh hưởng quote không dùng cap (regression cart totals + checkout OSC e2e).

# 16. Test strategy

**Unit (PHP, `Test/Unit`)** — không cần Magento app: LRM allocator (tổng luôn khớp, tie-break deterministic), factor scaling, minFix-guard, rule-guard theo simple_action, idempotency của pure functions.

**Integration (Magento TestFramework, `Test/Integration`)** — quote thật + DB test:
- A. Đơn item: discount < cap; > cap.
- B. Multi items: nhiều eligible; mixed eligible/ineligible (ineligible giữ 0 contribution, không bị scale); giá khác nhau; qty khác nhau (kể cả discount_step/discount_qty).
- C. Multi rules: 2 rules bình thường; 2 capped; capped + non-capped; stop_rules_processing; coupon rule; automatic rule.
- D. Config: tax incl/excl × price incl/excl; currency precision 0 (VND) và 2 (USD fixture); repeat collectTotals; add/remove/qty change; configurable product; bundle product (dynamic price).
- E. Lifecycle: place order → full invoice / partial / multiple partial; full refund / partial / refund by item / partial qty; assert allocation + invariants (AC-013).

**QC manual (theo testcase skill)** — admin UX matrix (AC-008/009), OSC e2e với capped coupon (Mageplaza OSC — Tier-2 checklist), Mageplaza ExtraFee tương tác totals.

# 17. Implementation plan (đề xuất — chi tiết trong tickets con)

| # | Work item | Mode | Notes |
|---|---|---|---|
| 1 | `Secomm_Promotion` scaffold tối giản + `Secomm_PromotionMaxDiscount` skeleton | C | module.xml, composer, README/CHANGELOG |
| 2 | db_schema column + whitelist + data semantic (NULL/0 unlimited) | B | Tier-2 DB schema → escalate |
| 3 | Cap engine: collector 310 + cap resolver + LRM allocator | A | core; unit tests trước (redistribution math) |
| 4 | Admin UI: ValueProvider plugin + JS field + i18n vi/en | B | |
| 5 | Integration tests (A–E) + idempotency + lifecycle verify | A | evidence `.ai/evidence/` |
| 6 | QC matrix + OSC e2e + docs (module README, `04_CUSTOM_MODULES`, `09_MAGENTO_MODULE_MAP`, BR mới trong `02_BUSINESS_RULES`) | B | |

# 18. Estimate theo role (workflow Mode A — estimation-tracking cập nhật khi ticket tạo)

| Role | Effort | Ghi chú |
|---|---|---|
| SA/TL | 0.5–1 d | spec review, D1–D3 decision, Tier-2 gate (order/pricing) |
| Developer | 6–8 d | scaffold 0.5 · schema 0.5 · cap engine + LRM 2–2.5 · admin UI 1 · integration/lifecycle tests 2–3 · docs 0.5 |
| QC | 2–3 d | matrix §16 + OSC e2e + invoice/refund manual |
| DevOps | 0 | không infra riêng |

---

# Open decisions (cho TL/SA — Level 2)

- **D1 — Hook point:** custom collector sort 310 như spec đề xuất, hay hướng thay thế nào TL muốn cân thêm? (Đã loại 3 hướng, §5.)
- **D2 — API exposure:** thêm `extension_attributes` cho `RuleInterface` Phase 1 (recommend — chống REST-save bỏ field) hay defer (Launchpad admin-only)?
- **D3 — Multishipping semantics:** quote-level per-rule cap (đề xuất) — xác nhận vì Launchpad single-shipment OSC nhưng Launchpad Core tái sử dụng.

# Related

- Feature record: [FEAT-JKZM68](../records/features/FEAT-JKZM68.md)
- Risk tier: AGENTS.md §9/§12 (pricing/order lifecycle Tier-2)
- Source evidence (file:line): `vendor/magento/module-sales-rule/Model/Quote/Discount.php` L132–261 · `Model/RulesApplier.php` L246–480 · `Model/Utility.php` L141–210 · `Model/Validator.php` L391–416, L560–700 · `vendor/magento/module-sales/Model/Order/Invoice/Total/Discount.php` · `.../Creditmemo/Total/Discount.php` · `etc/sales.xml` (3 module)
