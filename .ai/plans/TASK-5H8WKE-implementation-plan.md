# Implementation Plan: TASK-5H8WKE — Cap engine (MaxDiscountCap collector 310 + CapResolver + LRM allocator)

| Field | Value |
|---|---|
| Specification | tickets/TASK-5H8WKE-maxdiscount-cap-engine.md (`## Mini Spec`, embedded — ID TASK-5H8WKE) · canonical parent specs/SPEC-FEAT-JKZM68-promotion-max-discount.md (FULL, VALID) §4–§11 |
| Author | AI draft |
| Reviewer (TL) | **pending — Tier-2 approval (pricing/quote totals — gate trước Task 1)** |
| Workflow Mode | A (Tier 2 — AGENTS §9/§11/§12) |
| Date | 2026-08-20 |

> **Status: Dev complete (2026-08-20).** TL Tier-2 approved trong chat → execute Task 1–9 một session. Mọi task ✓ — AC-1..AC-10 verified (map bằng chứng trong [TASK-5H8WKE-evidence](../runtime/evidence/TASK-5H8WKE/TASK-5H8WKE-evidence.md)). 2 bug thật fix trong session (resolver memo-leak → stateless; test-data pollution từ exit-skip-finally) + 5 design corrections từ vendor thật (getDiscountData/getRuleLabel; DiscountData setters → in-place; PriceCurrency::round hard-code 2 → Locale Format precision; sales.xml `<item>`; AbstractExtensibleObject OM factories). Chờ TL code review Tier-2 → QC e2e TASK-HPK1WZ.
> Plan derive từ Mini-Spec + spec — không redefine thuật toán (rule spec-first "Plan derive từ Spec").

---

## PART 1 — ANALYSIS (pre-implementation)

### 1.1 Quyết định đã pin (từ DEC-FEATJKZM68-001 + spec — không quyết định mới)

| Câu hỏi | Kết luận | Nguồn |
|---|---|---|
| Hook point? | Custom quote total collector `max_discount_cap` sort **310** qua `etc/sales.xml` — config-only, zero core modification | DEC §1, spec §5 |
| Nguồn contribution? | `item.extension_attributes.discounts[]` native (RuleDiscount{rule_id, DiscountData{amount, base_amount, original_amount, base_original_amount}}) — set **trước** minFix ⇒ chỉ scale DOWN an toàn | DEC §2, spec §7 (verified RulesApplier L350–406: calculate → deltaRoundingFix → setDiscountBreakdown → minFix) |
| Redistribution? | factor = cap/Σ native (base chain authoritative), scale theo **contribution**; LRM 2 chuỗi độc lập base+display; tie-break item_id ASC | DEC §4, spec §7–§8 |
| Eligibility? | KHÔNG viết lại — item eligible của rule = item có breakdown entry (native chỉ ghi amount > 0) | spec §7 |
| Idempotency? | Không persist state riêng; native `Discount::collect()` reset item discount + breakdown mỗi pass (verified L169–185) | spec §11 |
| Item set? | Flat items của **mọi** address (`quote->getAllAddresses()` → `getAllItems()`), filter `noDiscount`/parent như native L197 — quote-level per-rule cap theo D3 | ticket Description, spec §13 multishipping |

### 1.2 Thiết kế class (placement theo ticket)

```
etc/sales.xml                                        collector declaration — sort 310, sau discount
Model/Quote/Address/Total/MaxDiscountCap.php         extends AbstractTotal — collect() orchestration, fetch() no-op
Model/Cap/RuleCapResolver.php                        rule_ids → [cap, simple_action] — 1 collection query, cache per pass
Model/Redistribution/LargestRemainderAllocator.php   PURE: (targets[itemId], cap, precision) → finals[itemId] — không dependency Magento
Test/Unit/Redistribution/LargestRemainderAllocatorTest.php
Test/Unit/Cap/RuleCapResolverTest.php
Test/Unit/Quote/Address/Total/MaxDiscountCapTest.php
```

### 1.3 Technical pins (từ vendor 2.4.8-p5 đã đọc)

- **Collector base:** `Magento\Quote\Model\Quote\Address\Total\AbstractTotal` — `collect(Quote $quote, Address\Total $total)` + `fetch()` trả `$this` (không row — cap nằm trong total `discount` native).
- **Mirror native:** Discount::collect reset-block (L169–185) + aggregate cuối (L240+, `aggregateItemDiscount` dấu âm) — đọc kỹ block aggregate khi code để mirror `total->setDiscountAmount`, `address` discount fields, `subtotal_with_discount`. Item loop filter: `getNoDiscount() || !canApplyDiscount || getParentItem()` (L197).
- **Address-level breakdown:** sau khi scale item breakdown, **rebuild** `address.extension_attributes.discounts[]` per-rule (mirror `RulesApplier::aggregateDiscountPerRule` L351–394 — cộng dồn từ item breakdown đã cap) để REST `V1/carts/totals` / GraphQL hiển thị đúng.
- **Allocator pure:** nhận `precision` là tham số (không gọi PriceCurrency bên trong) → test không cần Magento app; collector lấy precision từ currency của quote qua `PriceCurrencyInterface`. Floor + remainder theo fractional part giảm dần, tie item_id ASC.
- **Resolver:** chỉ load khi có rule_id trong breakdown; `ruleCollectionFactory` addFieldToFilter `rule_id IN (...)` select `rule_id, simple_action, maximum_discount_amount`; guard `(float)cap > 0 && simple_action == by_percent`.
- **di wiring:** collector + resolver qua constructor injection (không preferences mới — không đụng TASK-33J3RP plugins).
- **Test runner:** existing pattern `app/code/Secomm/*/Test/Unit/*Test.php` (Ghtk/AddressDropdown/GhnAddressMapper precedent) — xác nhận lệnh chạy hiện hành khi Task 6 (nếu chưa có phpunit config chung thì theo cách module trước chạy).

### 1.4 Rủi ro đã biết (từ spec §13/§14 + ticket Risks)

- **minFix divergence** (Σ breakdown > item discount cuối): chấp nhận — scale-down-only không phá invariant; nếu gặp case inconsistent nghiêm trọng → KHÔNG workaround, escalate + document.
- **3rd-party collector 300–450**: chưa có trong project hiện tại; QC re-check khi install extension mới (spec §14).
- **Multishipping**: mirror native per-address pattern; OSC single-shipment là primary path; QC multishipping smoke only (spec §13).
- **Mollie/VNPAY payment amount phụ thuộc totals** — mọi sai số lan tới payment ⇒ Tier-2 review + QC e2e (TASK-HPK1WZ) bắt buộc trước production.

## PART 2 — IMPLEMENTATION TASKS

| # | Task | Files | AC | Status |
|---|---|---|---|---|
| 0 | **TL Tier-2 approval** (gate — không code trước gate) | — | — | ⏳ HUMAN |
| 1 | `etc/sales.xml` + collector skeleton (collect no-op ban đầu + fetch no-op) — verify wiring 310 qua totals config + di:compile | `etc/sales.xml`, `Total/MaxDiscountCap.php` | AC-1 | — |
| 2 | `LargestRemainderAllocator` (pure) + **unit tests trước** (bộ case sinh tự động: n items × factor × precision 0/2; invariant Σ == cap; tie-break) | `Redistribution/…` + Test | AC-4, AC-9 | — |
| 3 | `RuleCapResolver` + unit tests (guard action/cap≤0; cache — 1 query/pass; không query per item) | `Cap/…` + Test | AC-3, AC-7, AC-10 | — |
| 4 | `MaxDiscountCap::collect()` orchestration: item set mọi address → breakdown đọc → per-rule N → no-op check → factor + LRM 2 chuỗi → scale item fields (discount_amount/base_discount_amount + 4 field DiscountData; KHÔNG discount_percent) → aggregates (Total/Address dấu âm + subtotal_with_discount) + address breakdown rebuild | `Total/MaxDiscountCap.php` | AC-2, AC-5, AC-6, AC-7, AC-8 | — |
| 5 | Collector unit tests (mock items/breakdown/Total): cap đúng, no-op identical, multi-rule, idempotency logic, minFix guard | `MaxDiscountCapTest.php` | AC-2, AC-3, AC-5, AC-6, AC-7, AC-9 | — |
| 6 | Chạy full unit suite green cùng existing Secomm tests | — | AC-9 | — |
| 7 | Runtime smoke engine-level (bootstrap): rule by_percent cap 50k, cart fixture mini (2 items 400k/600k, 20%) → assert Σ == 50k base+display; no-op case (cap NULL) totals identical; collect ×3 identical; module disable → totals native | throwaway → evidence | AC-1, AC-2, AC-3, AC-6 | — |
| 8 | AC-8 sweep: grep không ghi shipping fields; không override order/invoice/CM | — | AC-8 | — |
| 9 | Records: evidence `.ai/runtime/evidence/TASK-5H8WKE/` · ticket AC tick + pre-review §8.3 · CHANGELOG 1.2.0 | `.ai/` | — | — |

### Sequence & gating

```
Task 0: TL Tier-2 approval ⏳ HUMAN (CLAUDE.md: pricing/order stop-list)
   ↓
Task 1 (wiring — AC-1)  →  Task 2 (LRM + tests TRƯỚC, spec §17)  →  Task 3 (resolver + tests)
   ↓
Task 4 (collect orchestration — core)  →  Task 5 (collector tests)  →  Task 6 (suite green)
   ↓
Task 7 (runtime smoke engine-level)  →  Task 8 (AC-8 sweep)  →  Task 9 (records)
   ↓
AI Pre-review §8.3  →  TL code review Tier-2 ⏳ HUMAN  →  QC e2e OSC = TASK-HPK1WZ · Integration full = TASK-4HYX6Y
```

## Remaining steps (human / next tickets)

1. **TL Tier-2 approval** plan này + implementation (nói "approve" theo session precedent).
2. Sau dev: TASK-67GGPR (admin UI) ∥ TASK-4HYX6Y (integration testsconsume engine) — TASK-4HYX6Y phụ thuộc collector API ổn định.
3. QC e2e (OSC + Mollie payment amount) + docs — TASK-HPK1WZ.

## Verification summary (đối chiếu Mini-Spec)

| Mini-Spec clause | Task / bằng chứng |
|---|---|
| Expected Behavior — Σ final == cap base + display, theo contribution | Task 4 + 5 (AC-2) + Task 7 runtime |
| Expected Behavior — no-op identical native (3 điều kiện) | Task 5 (AC-3) + Task 7 |
| Expected Behavior — idempotent, không persist state | Task 4 design + Task 5/7 (AC-6) |
| Expected Behavior — per-rule độc lập, stop_rules_processing | Task 4/5 (AC-5) |
| Expected Behavior — address breakdown phản ánh cap; fetch không row | Task 4 + Task 1 (AC-1) |
| Expected Behavior — shipping/downstream untouched | Task 8 (AC-8) + spec §10/§12 |
| Constraints — LRM 2 chuỗi, precision, tie-break ASC | Task 2 (AC-4) |
| Constraints — resolver cache per pass | Task 3 (AC-10) |
| Constraints — Tier-2 review + QC e2e | Task 0 + gate cuối + TASK-HPK1WZ |
