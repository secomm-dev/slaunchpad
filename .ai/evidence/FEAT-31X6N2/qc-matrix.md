# QC Matrix — FEAT-31X6N2 Commerce Tracking

> Checklist e2e theo SPEC-FEAT-31X6N2 §13 (AC-001..AC-012). QC điền evidence từng dòng;
> artifacts (screenshot/console output) filed cùng thư mục này. **Mask PII trước khi chụp.**

**Env:** storefront Hyvä local · checkout Mageplaza OSC · payment Mollie test (+ VNPAY sandbox cho AC-010) ·
Meta Test Events (`test_event_code`) · TikTok Event Debug · admin: Secomm → CORE → Commerce Tracking.

**Pre-conditions:** module enabled (`bin/magento module:status Secomm_Tracking`) · config: General=Yes,
Meta=Yes+Pixel+Token+TestMode · cron chạy (`bin/magento cron:run --group=default`) · cache flush sau config.

---

## A. Browser dataLayer (AC-001, AC-002)

| # | Step | Expected | Pass | Evidence |
|---|---|---|---|---|
| A1 | Mở PDP (product simple) | `window.dataLayer` có `launchpad_event.event=view_item`, `event_id=view_item-{id}-{Ymd}`, price/currency đúng | ☐ | |
| A2 | Mở category page | `event=view_category`, event_id theo category id | ☐ | |
| A3 | Search từ khóa "ao" | `event=search`, `search_term` present | ☐ | |
| A4 | Add to cart → cart page | `event=view_cart` trên cart index; Magefan dataLayer vẫn có `add_to_cart` (không bị ảnh hưởng) | ☐ | |
| A5 | Vào OSC (`onestepcheckout`) | `event=begin_checkout`, items+grand_total khớp quote | ☐ | |
| A6 | F5 PDP 2 lần | event_id GIỮ NGUYÊN (deterministic) | ☐ | |
| A7 | GTM Preview/DebugView | GA4 nhận đủ view_item/view_item_list/search/add_to_cart/begin_checkout, `transaction_id`/value/currency đúng trên purchase | ☐ | |

## B. Purchase + dedup (AC-002, AC-003, AC-004)

| # | Step | Expected | Pass | Evidence |
|---|---|---|---|---|
| B1 | Place order Mollie test → hoàn tất | Success page: `launchpad_event.event=purchase`, `event_id=purchase-{increment_id}` | ☐ | |
| B2 | F5 success page | KHÔNG push purchase lần 2 | ☐ | |
| B3 | Đợi 1-2' (cron) → check outbox grid | row `purchase-{increment_id}` status sent (meta+tiktok nếu bật) | ☐ | |
| B4 | Meta Test Events tab | Purchase browser + Purchase server cùng event_id → cột **Deduplication: deduplicated**, KHÔNG double count | ☐ | |
| B5 | TikTok Event Debug | CompletePayment browser + server cùng event_id → deduplicated | ☐ | |
| B6 | Delivery Log grid | filter event_id → thấy row HTTP 200, summary `events_received=1` | ☐ | |

## C. PII / hashing (AC-005)

| # | Step | Expected | Pass | Evidence |
|---|---|---|---|---|
| C1 | Payload CAPI (Test Events JSON view) | `user_data.em`/`ph` = 64-char hex; KHÔNG có plain email/phone | ☐ | |
| C2 | grep DB payload | `SELECT payload FROM secomm_tracking_event` — không chứa plain email/phone/IP đầy đủ | ☐ | |
| C3 | var/log/secomm_tracking.log | không plain identifier; IP dạng `1.2.*.*` nếu có | ☐ | |

## D. Refund (AC-006)

| # | Step | Expected | Pass | Evidence |
|---|---|---|---|---|
| D1 | Refund=No + tạo creditmemo | KHÔNG có row refund trong outbox | ☐ | |
| D2 | Refund=Yes + partial creditmemo | row `refund-{cm_increment_id}` value=CM grand_total; Meta nhận `Refund` | ☐ | |
| D3 | Full refund (order nhiều CM) | mỗi CM 1 event riêng | ☐ | |

## E. Consent (AC-007)

| # | Step | Expected | Pass | Evidence |
|---|---|---|---|---|
| E1 | Cookie Restriction Mode=On, trình duyệt sạch | `launchpad_event.consent.marketing=false`; KHÔNG consent cookie | ☐ | |
| E2 | Accept cookie restriction | `consent.marketing=true`; server events gửi bình thường | ☐ | |
| E3 | Marketing=false + place order | outbox row bị skip (enqueue consent gate) — log ghi skipped | ☐ | |

## F. Failure isolation + perf (AC-009, AC-011)

| # | Step | Expected | Pass | Evidence |
|---|---|---|---|---|
| F1 | Token Meta sai → place order | order đặt bình thường; row retry theo backoff; sau 5 attempts failed | ☐ | |
| F2 | TTFB checkout success trước/sau (3 lần mỗi) | chênh lệch < 50ms | ☐ | |
| F3 | Console JS sạch trên 7 trang test | no errors | ☐ | |
| F4 | Full OSC e2e Mollie + VN address dropdown (BR-002/004) | pass như trước khi có module | ☐ | |

## G. VNPAY (AC-010)

| # | Step | Expected | Pass | Evidence |
|---|---|---|---|---|
| G1 | VNPAY sandbox order → IPN confirm | purchase fire đúng 1 lần khi state→processing | ☐ | |
| G2 | IPN + return cùng lúc confirm | vẫn 1 row (transition guard + UNIQUE) | ☐ | |

## H. Scope (AC-012)

| # | Step | Expected | Pass | Evidence |
|---|---|---|---|---|
| H1 | `git diff --stat` từ branch trước | 0 file trong `app/code/Magefan/`, `app/code/Mageplaza/`, `vendor/` | ☐ | |

---

## Sign-off

| Role | Name | Date | Result |
|---|---|---|---|
| QC | | | |
| TL | | | |
