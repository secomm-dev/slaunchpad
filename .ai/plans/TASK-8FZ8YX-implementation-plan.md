# TASK-8FZ8YX Implementation Plan — Purchase/refund observers + consent gating

| Field | Value |
|---|---|
| Specification | tickets/TASK-8FZ8YX-tracking-refund-consent.md (embedded Mini Spec) · canonical parent specs/SPEC-FEAT-31X6N2-commerce-tracking.md §8, §9, §11 |
| Mode | A · Tier 2 (order lifecycle, VNPAY IPN / Mollie webhook interaction) |

> **Status:** Retro-canonical — distilled 2026-08-27 từ work đã dev-complete. **Observer runtime đã chạy thật** (outbox có row purchase qua invoice transition). E2E QC Mollie/VNPAY pending.

---

## PART 1 — ANALYSIS

| Quyết định | Kết luận | Nguồn |
|---|---|---|
| Hook = state transition trên `sales_order_save_after` | Gate `isPaidTransition()`: state mới ∈ {processing, complete} AND `getOrigData('state') !== state mới` — webhook Mollie + IPN VNPAY + admin invoice đều quy về **1 transition duy nhất** fire; save lặp không re-fire | DEC D3 |
| `payment_review` bị loại | Chưa chắc chắn thanh toán — capture pending sẽ vào processing khi confirm | spec §11 |
| `complete` có trong PAID_STATES | Edge: auto-invoice + auto-ship / virtual product nhảy thẳng new→complete | spec §11 |
| Observer contract | Normalize → hash → insert outbox ONLY; try/catch toàn bộ — tracking fail không break order save; debug log tạm đã từng gắn để chẩn đoán (state/orig_state) | §7.2 observer rule |
| Refund opt-in | `sales_order_creditmemo_save_after` + gate config `refund/enabled` (default OFF); 1 CM = 1 event, value = CM grand_total | spec §9 |
| Consent D5 | `CookieRestrictionEvaluator` đọc Cookie Restriction Mode + `user_allowed_save_cookie`; interface swap được cho CMP | DEC D5 |

## PART 2 — IMPLEMENTATION TASKS

| # | Task | Files | Status |
|---|---|---|---|
| 1 | PurchaseFire transition gate | `Observer/PurchaseFire.php` + `etc/events.xml` | ✅ runtime verified |
| 2 | RefundFire + opt-in gate | `Observer/RefundFire.php` | ✅ code / QC pending |
| 3 | Consent evaluator | `Model/Consent/*` + di preference | ✅ |
| 4 | E2E QC Mollie (webhook → 1 row) + VNPAY sandbox (IPN double → vẫn 1 row) | — | ⏳ pending |
| 5 | Creditmemo partial/full + enable/disable matrix | — | ⏳ pending |

## Runtime fixes (lessons)

- `new TrackingEventQueue()` → fatal thiếu DI args → `TrackingEventQueueFactory->create()`.
- Thiếu `const CACHE_TAG` cho `IdentityInterface::getIdentities()` — Undefined constant fatal lúc save đầu tiên.
- Observer im lặng hoàn toàn + log sạch → **check module có trong `app/etc/config.php`** (đã 1 lần biến mất sau reset env).
- Invoice trên order đang `pending`: transition hợp lệ fire; invoice trên order **đã processing** không fire (đã fire trước đó) — đúng thiết kế, đã verify với user qua bảng mux.

## QC note

Meta removed (DEC-002): refund giờ chỉ TikTok nhận. Row `vendor=meta` cũ trong outbox là history.
