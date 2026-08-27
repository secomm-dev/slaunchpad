# TASK-E0NG8Z Implementation Plan — Browser pipeline: dataLayer + event_id surface + cookie capture

| Field | Value |
|---|---|
| Specification | tickets/TASK-E0NG8Z-tracking-browser-pipeline.md (embedded Mini Spec) · canonical parent specs/SPEC-FEAT-31X6N2-commerce-tracking.md §5, §7 |
| Mode | B · Tier 2 (checkout page render — QC OSC e2e bắt buộc) |

> **Status:** Retro-canonical — distilled 2026-08-27 từ work đã dev-complete. **Storefront QC pending** (GTM preview chưa chạy).

---

## PART 1 — ANALYSIS

| Quyết định | Kết luận | Nguồn |
|---|---|---|
| Block riêng, không plugin trên Magefan block | `Block/LaunchpadEvent` reference `head.additional` qua `default.xml` — zero class coupling với Magefan (upgrade an toàn), toàn quyền output | D1 + implementation finding |
| Event shape theo page handle | `catalog_product_view`→view_item · category→view_category · `catalogsearch_result_index`→search · cart/OSC handles→begin_checkout; success page render purchase từ checkout session last order | spec §5 |
| event_id nguồn nào | Browser-context: `browserEventId()` = `{event}-{entity}-{Ymd}` (FPC-safe — không session data); success: `purchase-{increment_id}` **identical** server outbox (dedup D3) | spec §7 |
| Cookie matching params | Đọc `document.cookie` push-time trong heredoc JS (`_fbp/_fbc/_ttp/ttclid`) — không round-trip; absent → field absent (không empty string) | AC-3 |
| XSS/CSP | `escapeJs` mọi dynamic vào inline script; render qua `secureHtmlRenderer->renderTag` — CSP-safe, không RequireJS/jQuery (Hyva rule) | §7.2 |
| Scope change DEC-002 | Meta browser pixel không qua pipeline này — Magefan FB Pixel render trực tiếp; GTM tags chỉ còn GA4 + TikTok | DEC-FEAT31X6N2-002 |

## PART 2 — IMPLEMENTATION TASKS

| # | Task | Files | Status |
|---|---|---|---|
| 1 | LaunchpadEvent block + handle detection | `Block/LaunchpadEvent.php` | ✅ |
| 2 | Layout wire head.additional | `view/frontend/layout/default.xml` | ✅ |
| 3 | Cookie capture JS + dataLayer push | trong block heredoc | ✅ |
| 4 | GTM config checklist (config-side) | `.ai/evidence/FEAT-31X6N2/gtm-container-checklist.md` | ✅ doc |
| 5 | Storefront QC (GTM preview 5 events + success + console + F5) | — | ⏳ pending |

## QC pending chi tiết

AC-1..AC-5 Mini Spec: GTM preview thấy `launchpad_event` đủ browser events đúng event_id pattern; success page match server id; F5 không push lại; console sạch; `git diff` 0 file trong `app/code/Magefan/`.
