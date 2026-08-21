# Implementation Plan: TASK-4HYX6Y — Integration tests (quote matrix A–D + lifecycle E + idempotency)

| Field | Value |
|---|---|
| Specification | tickets/TASK-4HYX6Y-maxdiscount-integration-tests.md (Mode A; spec-frontmatter chỉ parent FULL spec — ticket không có Mini-Spec riêng vì BẢN THÂN ticket đã là test-matrix mapping 1:1 spec §15/§16) · canonical parent specs/SPEC-FEAT-JKZM68-promotion-max-discount.md (FULL, VALID) §12/§15/§16 |
| Author | AI draft |
| Reviewer (TL) | **pending — Tier-2 approval (order/invoice/creditmemo lifecycle + test-DB creation — gate trước Task 1)** |
| Workflow Mode | A (Tier 2 — AGENTS §9/§11/§12) |
| Date | 2026-08-21 |

> Plan derive từ ticket + spec §16 test strategy + TASK-5H8WKE evidence (đặc biệt 4 fixture lessons từ smoke round 2). Không redefine thuật toán — engine đã frozen ở TASK-5H8WKE (dev complete + smoke 24/24).

---

## PART 0 — INFRA FEASIBILITY (phải giải trước, đặc thù project)

**Thực trạng:** `dev/tests/integration/` tồn tại (framework + phpunit.xml.dist) nhưng **chưa từng configure**: `etc/` chỉ có `.dist` (không có `install-config-mysql.php` / `config-global.php` thật). Các module Secomm/ZaloPay có `Test/Integration` files nhưng **không evidence nào chứng minh đã chạy qua TestFramework** (lần chạm gần nhất: load IpnTest fail `Class AbstractController not found` vì infra không setup).

**Option 1 (plan chính — theo ticket): Magento TestFramework + DB test riêng**
- Tạo DB test mới `slaunchpad_test` (MYSQL SERVER local; KHÔNG đụng DB dev `slaunchpad`) — **cần TL duyệt vì tạo database mới**
- `cp dev/tests/integration/etc/install-config-mysql.php.dist` → điền creds DB test; `config-global.php` tương tự
- First run: framework INSTALL Magento sạch vào DB test (chậm ~10–20p, một lần) — sau đó sandbox per-test (rollback transaction)
- Ưu: đúng ticket ("TestFramework, DB test thật"), isolation chuẩn, chạy lại được vô hạn
- Nhược: setup ban đầu + test DB must stay out of backups/deploys; .gitignored config files

**Option 2 (fallback): engine-level bootstrap pattern** (đã chứng minh với TASK-5H8WKE smoke round 2 — 24/24)
- Bootstrap `app/bootstrap.php` + fixtures thật trong DB dev + cleanup `finally` (throw không exit)
- Ưu: không cần infra mới; đã có 4 lessons documented
- Nhược: KHÔNG isolation (dùng DB dev — pollution risk), không phải "TestFramework" như ticket pin, khó repeat 3× an toàn → chỉ dùng nếu Option 1 blocked

**TL quyết Option ở gate.** Các Task dưới viết cho Option 1 (chỉ chỗ khác Option 2 mới chú thích).

## PART 1 — ANALYSIS

### 1.1 Fixture builder (budget ~⅓ thời gian — theo ticket Risks)

Tái sử dụng **4 lessons từ TASK-5H8WKE evidence** (đã trả giá discovery):
1. MSI salability: product save + SourceItem (source `default`, IN_STOCK) + `IndexerRegistry->get('cataloginventory_stock')->reindexList(ids)` + **reload qua repository** (in-memory object thiếu stock-status)
2. Quote phải **persist trước khi assert breakdown** (items NULL-id → `RulesApplier::discountAggregator` key-collision — behavior native)
3. Rule actions conditions: `setActionsSerialized(json_encode([...]))` — JSON không PHP serialize; object-graph `addCondition` không sống qua save; fail-loud check sau save
4. Plugin signature changes ⇒ `di:compile` (n/a cho integration run bình thường nhưng ghi nhớ)

Builder classes (fixture namespace, không phải production code):
```
Test/Integration/Fixture/ProductBuilder.php        simple/configurable/bundle + MSI + reindex + reload
Test/Integration/Fixture/RuleBuilder.php           by_percent/by_fixed/cart_fixed, cap set, sku-restrict, coupon, stop_rules
Test/Integration/Fixture/QuoteBuilder.php          guest quote, addProduct, persist, address VN, recalc helper
Test/Integration/Assertion/TotalsInvariant.php     Σ final eligible == min(Σ native, cap) base+display; breakdown map so sánh theo precision (không naive == float)
```

### 1.2 Case matrix → test classes (map ticket A–E → AC)

| Group | Test class | Cases (từ ticket) | AC |
|---|---|---|---|
| A đơn item | `QuoteSingleItemTest` | discount < cap no-op identical native · discount > cap final == cap | AC-1/2 |
| B multi items | `QuoteMultiItemTest` | khác giá/qty, discount_step/discount_qty · mixed eligible/ineligible (ineligible không scale) · 40k+60k cap 50k → 20k/30k · configurable (child price) · bundle dynamic (children-calculated qua `aggregateDiscountBreakdown`) | AC-1/2 |
| C multi rules | `QuoteMultiRuleTest` | 2 rules thường · 2 capped độc lập · capped + non-capped · stop_rules_processing · coupon rule · automatic rule | AC-1 |
| D config/recompute | `QuoteRecomputeTest` | tax incl/excl × catalog price incl/excl (≥2 config) · currency precision 0 (VND) + 2 (fixture USD store view) — LRM invariant từng chuỗi · add/remove item · change qty · coupon add/remove · collectTotals ×3 identical (float theo precision) · customer group switch | AC-1/2/4 |
| E lifecycle | `OrderLifecycleTest` | place order → full/partial/multi-partial invoice · full/partial credit memo · refund by item · partial qty refund — assert: order item == quote capped; Σ invoiced ≤ discount; Σ refunded ≤ invoiced; **đổi rule/disable sau place order → invoice/CM numbers không đổi** | AC-3/5 |

Bundle dynamic-price = path rủi ro nhất (ticket Risks): nếu native breakdown không đủ thông tin → **KHÔNG workaround — escalate root-cause** (spec §13).

### 1.3 Technical pins

- **Base class**: `Magento\TestFramework\TestCase\AbstractController`? — KHÔNG cần controller; dùng `\PHPUnit\Framework\TestCase` + static fixture fixtures per class, hoặc TestFramework annotations. Base chung tự viết: `AbstractCapTestCase` (bootstrap helpers + precision-aware compare `assertEqualsWithDelta(…, 0.5 × 10^-precision)`).
- **Currency 2-decimals case**: tạo store view USD riêng trong fixture (hoặc đổi currency default per test bằng config fixture) — pin: quote currency codes persisted quyết định precision (TASK-5H8WKE C2 fix) — test cả 2 đường (persisted + fallback).
- **Native baseline** cho no-op identity: chạy collect với cap NULL trước, snapshot totals/breakdown, set cap, reset flag, collect lại, so sánh (pattern smoke round 2).
- **Flaky guard (AC-1 3×)**: không `sleep`, không phụ thuộc id auto-increment; cleanup fixture qua TestFramework transaction sandbox tự rollback (Option 1).

## PART 2 — IMPLEMENTATION TASKS

| # | Task | Files | AC | Status |
|---|---|---|---|---|
| 0 | **TL Tier-2 approval** plan + **quyết Option 1/2** (test DB mới nếu Option 1) | — | — | ⏳ HUMAN |
| 1 | Infra setup: install-config + test DB + first bootstrap + 1 hello-world integration test GREEN (chưa fixture gì của module) | `dev/tests/integration/etc/*` (gitignored) | — | — |
| 2 | Fixture builders + invariant assertions + 1 smoke case A (cap 50k 2 items) green | Fixture/*, Assertion/* | AC-1 (partial) | — |
| 3 | Group A + B đơn giản (khác giá/qty, mixed eligibility, 20k/30k) | QuoteSingleItemTest, QuoteMultiItemTest | AC-1/2 | — |
| 4 | Group B nâng cao: configurable + bundle dynamic (escalate nếu breakdown thiếu) | QuoteMultiItemTest | AC-1/2 | — |
| 5 | Group C multi rules (6 case) | QuoteMultiRuleTest | AC-1 | — |
| 6 | Group D config/recompute matrix (tax × price incl/excl, USD precision 2, recompute, ×3) | QuoteRecomputeTest | AC-1/2/4 | — |
| 7 | Group E lifecycle 7 kịch bản + đổi-rule-sau-place-order | OrderLifecycleTest | AC-3/5 | — |
| 8 | Stability: full suite ×3 green (không flaky) · coverage gaps đánh dấu · evidence + ticket AC + pre-review §8.3 | evidence dir | AC-1/6 | — |

### Sequence & gating

```
Task 0: TL Tier-2 approval + Option decision ⏳ HUMAN
   ↓
Task 1 (infra — nếu Option 1 fail → quay lại TL với evidence, cân nhắc Option 2)
   ↓
Task 2 (fixture builder) → 3 → 4 (bundle = checkpoint escalate) → 5 → 6
   ↓
Task 7 (lifecycle — Tier-2 nature: invoice/CM) → 8 (stability + records)
   ↓
AI Pre-review §8.3 → TL review Tier-2 ⏳ HUMAN → TASK-HPK1WZ (QC e2e + docs)
```

## Remaining steps (human / next tickets)

1. **TL**: approve plan + chọn Option infra + duyệt tạo DB test (nếu Option 1).
2. Sau TASK-4HYX6Y: TASK-HPK1WZ (QC docs + OSC e2e + UI manual) — ticket cuối của FEAT-JKZM68.

## Verification summary (đối chiếu ticket AC)

| Ticket AC | Task / bằng chứng |
|---|---|
| AC-1 A–E pass, 3× không flaky | Task 3–7 + Task 8 (repeat) |
| AC-2 invariant Σ == min(Σ native, cap) base+display | Assertion/TotalsInvariant dùng xuyên suốt; no-op case assert identity |
| AC-3 lifecycle 7 kịch bản + invariants allocation | Task 7 |
| AC-4 ×3 identical theo precision | Task 6 (D) + AbstractCapTestCase compare |
| AC-5 đổi rule sau place order → numbers không đổi | Task 7 (case cuối) |
| AC-6 evidence + coverage gap không im lặng | Task 8 + evidence dir TASK-4HYX6Y |
