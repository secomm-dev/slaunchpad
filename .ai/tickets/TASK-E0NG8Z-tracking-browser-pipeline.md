# TASK-E0NG8Z — Browser pipeline: dataLayer plugin + event_id surface + cookie capture

**Type:** Task (slice của FEAT-31X6N2 — browser-side)
**Priority:** High
**Estimate:** ~8h
**Mode:** B (plugin lên Magefan output + layout block; không đổi checkout flow logic — nhưng chạy trên trang OSC nên QC e2e bắt buộc)
**Placement:** `app/code/Secomm/Tracking/Model/Pipeline/`, `app/code/Secomm/Tracking/Plugin/`, `app/code/Secomm/Tracking/view/frontend/`
**Risk tier:** Tier 2 (chạm checkout page render — QC OSC e2e + payment test)
**Author:** AI draft · **Date:** 2026-08-24 · **Status:** Dev complete — chờ storefront QC (A1-A7) + TL review (Tier-2 OSC)
**Specification:** MINI — embedded dưới đây · canonical parent: [SPEC-FEAT-31X6N2](../specs/SPEC-FEAT-31X6N2-commerce-tracking.md) §5, §7 · Plan: [TASK-E0NG8Z plan](../plans/TASK-E0NG8Z-implementation-plan.md)

## Approach

> Retro-canonical 2026-08-27 — distilled từ work đã dev-complete.

1. **Chọn block riêng thay vì plugin trên Magefan block:** `Block/LaunchpadEvent` reference `head.additional` qua `view/frontend/layout/default.xml` — ít xâm phạm hơn plugin vì không耦合 vào class Magefan (Magefan upgrade an toàn), và quyền kiểm soát hoàn toàn output. GTM container tags đọc `launchpad_event.*` từ `window.dataLayer`.
2. **Event shape theo page handle:** detect handle trong block (`catalog_product_view` → view_item, `catalog_category_view` → view_category, `catalogsearch_result_index` → search, cart/checkout handles…) — `event_id` dùng `browserEventId()` của normalizer (entity + Ymd, FPC-safe). Success page render `purchase-{increment_id}` từ checkout session last order — identical ID với server outbox (dedup D3).
3. **Cookie capture push-time JS:** plain JS đọc `document.cookie` trong heredoc script (`_fbp/_fbc/_ttp/ttclid`) gắn vào `event.user` **trước** khi push — không server round-trip, không session. Absent cookie → field absent (không empty string) — assert trong test.
4. **Escape mọi dynamic value** vào inline script qua `$escaper->escapeJs()` (XSS rule §7.2); render qua `$secureHtmlRenderer->renderTag()` — CSP-safe, không RequireJS/jQuery (Hyva rule).
5. **GTM config checklist** (config-side, không code): variables `launchpad_event.*` + GA4 event tag + TikTok Pixel tag (event_id từ `launchpad_event.event_id`) — nằm trong README module cho marketer.

Scope change (DEC-FEAT31X6N2-002): Meta browser pixel không qua pipeline này nữa — Magefan FB Pixel render trực tiếp; GTM tags chỉ còn GA4 + TikTok.

**QC pending:** GTM preview verify 5 browser events trên PDP/category/search/cart/OSC + success page event_id match + console sạch + F5 không double-push.

## Description

- `Plugin` trên Magefan dataLayer block output (hoặc block riêng reference `head.additional` — chọn cách ít xâm phạm khi implement, ưu tiên plugin) đẩy `launchpad_event` object vào `window.dataLayer` cho mỗi commerce event: `event`, `event_id` (deterministic từ normalizer TASK-NNKTRM), `value`, `currency`, `items`, `consent` flags.
- Success page (`checkout_onepage_success` + Magefan handle): render sẵn `purchase` event với `event_id = purchase-{increment_id}` — **cùng ID server sẽ dùng** (dedup DEC D3).
- Cookie capture: đọc `fbp`, `fbc`, `ttclid`, `ttp` qua `document.cookie` tại push-time, gắn vào `launchpad_event.user`.
- `Model/Consent/` — `ConsentEvaluatorInterface` + `CookieRestrictionEvaluator` (D5) — browser side chỉ set flags, không chặn push.
- QC config checklist (doc trong README module): GTM container cần variable `launchpad_event.*` + tags GA4 event / Meta Pixel (eventID từ `launchpad_event.event_id`) / TikTok Pixel — checklist cho marketer, không phải code.

## Mini Spec

### Goal

Browser events có `event_id` deterministic khớp server, đẩy qua dataLayer để GTM container tags (GA4/Meta/TikTok) consume — không hardcode pixel snippet, không sửa Magefan in place.

### Expected Behavior

- Trên PDP/category/search/cart/OSC/success: `window.dataLayer` có object `launchpad_event` tương ứng với `event_id` đúng pattern spec §7.
- Success page: `launchpad_event.event_id` = `purchase-{increment_id}` identical với event server sẽ gửi CAPI.
- F5 success page: không push lần 2 (checkout session pattern của Magefan giữ nguyên).
- Cookie restriction ON + chưa accept: push vẫn xảy ra nhưng `consent.marketing=false` (server-side sẽ skip dựa flag này khi có; browser tags tôn trọng Consent Mode config GTM).
- Không JS error console trên Hyva storefront; không RequireJS/jQuery.

### Constraints / Rules

- KHÔNG modify `app/code/Magefan/*` — chỉ plugin/reference (verify bằng git diff scope).
- Plain JS trong phtml; `<?= $escaper->escapeJs(...) ?>` mọi dynamic value vào inline script (XSS rule §7.2).
- FPC-safe: event_id category/product theo `{event}-{entity_id}-{Ymd}` không chứa session data.

### Out of Scope

Server adapters · outbox · refund · GTM container thực tế setup (marketer config-side, có checklist) · Consent Mode v2 deep signals (DEC D5 defer).

### Acceptance Criteria

- [ ] **AC-1:** Trên storefront Hyvä (PDP → category → search → add-to-cart → OSC), GTM preview thấy `launchpad_event` đủ 5 browser events với event_id đúng pattern.
- [ ] **AC-2:** Success page: `launchpad_event` purchase có `event_id=purchase-{increment_id}`, value=grand_total, currency đúng; F5 không push lại.
- [ ] **AC-3:** `document.cookie` có `_fbp` → `launchpad_event.user.fbp` populate đúng; không có → field absent (không phải empty string).
- [ ] **AC-4:** Cookie Restriction ON chưa accept → `consent.marketing=false`; accept → `true`.
- [ ] **AC-5:** Console sạch JS error trên 7 trang test; OSC checkout e2e Mollie test pass (BR-004); `git diff --stat` không file nào trong `app/code/Magefan/`.

**Parent:** [FEAT-31X6N2](../records/features/FEAT-31X6N2.md) · **Spec:** SPEC-FEAT-31X6N2 §5/§7 · **DEC:** DEC-FEAT31X6N2-001 (D1, D5)