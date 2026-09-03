# Spec: Secomm Commerce Tracking & Advertising Measurement — GA4 + Meta CAPI + TikTok Events API

Specification ID: SPEC-FEAT-31X6N2
Feature ID: FEAT-31X6N2
Specification Level: FULL

> **Status:** VALID — approved 2026-08-24 bởi user acting as TL/SA (`/approve` D1–D6 theo khuyến nghị) → [DEC-FEAT31X6N2-001](../records/decisions/DEC-FEAT31X6N2-001.md).
> **Amendment 2026-08-27 — DEC-FEAT31X6N2-002:** Meta vendor **đã rút khỏi module** (browser + CAPI do Magefan FacebookPixel + Extra sở hữu — license đã mua). §6.1 MetaCapiAdapter **superseded**; module chỉ còn TikTok (§6.2). AC-002/AC-003/AC-005 phần Meta chuyển sang verify qua Magefan Extra. TikTok sections vẫn hiệu lực nguyên vẹn.
> **Mode A** · Tier 2 (PII hashing, checkout OSC, order lifecycle, third-party API contract) · Ticket nguồn: LC-22 (external PM).
> Build-vs-Buy: **hybrid** — GA4/GTM browser-side tái dùng `Magefan_GoogleTagManager` (đã cài, xem §1); Meta CAPI + TikTok Events API **self-build** trong `Secomm_Tracking` (không có vendor module thỏa requirement dedup + hash + debug).
> Phiên bản inspect: Magento 2.4.8-p5, Magefan GTM 2.8.0 (source `app/code/`).

---

# Purpose

Merchant cần đo funnel commerce (view → add_to_cart → begin_checkout → purchase → refund) và tối ưu quảng cáo trên 3 nền tảng GA4 (qua GTM), Meta (Pixel + Conversions API) và TikTok (Pixel + Events API), cho storefront VN (VND). Yêu cầu cốt lõi: **một event model chuẩn hoá** → dispatch browser + server → **dedup bằng `event_id`** → không lộ plain PII → có debug/reconcile. Không double-count giữa browser và server.

# Scope

- **Module mới `Secomm_Tracking`** (backend): Launchpad tracking event model + normalizer, server-side vendor adapters (Meta CAPI, TikTok Events API), outbox delivery + retry, refund flow, consent gating, debug logging (masked), admin config.
- **Event contract** (§4): 7 commerce events — `view_item`, `view_category` (view_item_list), `search`, `add_to_cart`, `begin_checkout`, `purchase`, `refund`.
- **Browser-side**: tái dùng Magefan GTM dataLayer cho GA4; surface `event_id` + Meta/TikTok event payloads vào `window.dataLayer` (qua plugin, không sửa Magefan in place) để GTM container tags (GA4 tag, Meta Pixel tag, TikTok Pixel tag) consume.
- **Server-side**: Meta CAPI + TikTok Events API purchase/refund (và các conversion events theo config), SHA-256 hashing user data, dedup `event_id`.
- **Regression QC**: Hyvä storefront + Mageplaza OSC end-to-end.

# Out of Scope

Ad campaign setup/optimization · Consent-Management Platform riêng (CookieYes/OneTrust…) · Attribution/BI warehouse (GA4 BigQuery export…) · server-side GA4 qua GTM Server Container sTAG riêng (D6 — defer trừ khi TL quyết định gồm; Magefan `GoogleTagManagerExtra` đã có `ServerTracker` hook nếu sau này bật) · Google Ads Enhanced Conversions · Meta `Advanced Matching` browser-side form fields · offline conversions import · multi-store config profile (single-store assumption BR-TBD-001) · campaign-level ROAS dashboard.

# Actors

Marketer/Ads operator (config vendor credentials, đọc dashboard) · QC (reconcile qua Meta Test Events + TikTok Debug + debug log) · Shopper (Hyvä storefront, Mageplaza OSC checkout) · Magento (catalog/checkout/order events) · GTM container (GA4/Meta/TikTok tags) · Meta Graph API · TikTok Business API · TL/SA (review open decisions).

---

# 1. Current state (verified trên repo)

**Đã có (không xây lại):**

| Thành phần | Bằng chứng | Ghi chú |
|---|---|---|
| GTM container loader (GA4) | `app/code/Magefan/GoogleTagManager/view/frontend/templates/js_code.phtml` (GTM snippet L144–147; Hyva customer-data path L175–191) | Hỗ trợ Hyva + Mageplaza OSC: layout handles `onestepcheckout_index_index.xml`, `hyva_checkout_index_index.xml`, `checkout_onepage_success.xml` |
| Purchase dataLayer (browser) | `Block/DataLayer/Purchase.php:59-70` — `checkoutSession->getLastRealOrder()`; `Model/DataLayer/AbstractOrder.php:60` — `transaction_id` = increment_id | GA4 `purchase` trên success page đã có |
| Begin checkout / view_item / view_cart | `Model/DataLayer/BeginCheckout.php`, `ViewItem.php`, `ViewCart.php` | |
| add_to_cart / remove_from_cart / view_item_list | `Magefan_GoogleTagManagerPlus` — observers `checkout_cart_product_add_after`… (`etc/events.xml`), `Model/DataLayer/ViewItemList.php:60` | |
| search | `Magefan_GoogleTagManagerExtra` — block `SearchTerm` trên `catalogsearch_result_index.xml` | |
| Unique event id seed | `Model/AbstractDataLayer.php:382-385` — `event + '_' + hash` → `magefanUniqueEventId` | Non-deterministic giữa browser/server → **không dùng cho dedup CAPI**; thay bằng deterministic ID (§7) |
| GTM server-side push hook | `GoogleTagManagerExtra/Observer/SalesOrderSaveAfter.php:75` → `ServerTracker->push()` | Chỉ activate khi config GTM Server Container — hiện OFF |

**Chưa có (gap):** Meta Pixel/CAPI, TikTok Pixel/Events API, refund event, dedup contract, PII hashing, consent gating, delivery log/reconcile. Không có `fbq`/`ttq` hardcoded nào trong theme `Secomm/launchpad` (verified grep).

**Config state:** `Magefan_GoogleTagManager` = 1 trong `app/etc/config.php:401-403` nhưng chưa verify được `core_config_data` (container ID có thể chưa set) — cần check ở task scaffold.

# 2. Business gap

- Đo funnel đa nền tảng: GA4 đủ browser-side qua Magefan, nhưng **Meta/TikTok cần server-side** (CAPI/Events API) để chống mất tín hiệu do adblock/ITP + tăng match quality (hashed email/phone + fbp/fbc/ttclid) → ROAS tốt hơn cho VN fashion ads.
- Double-count risk: browser Pixel và server API cùng fire `Purchase` cho một order → Meta/TikTok khấu trừ revenue nếu không dedup bằng cùng `event_id` + event name.
- Refund: không vendor module trong repo gửi refund signal (verified: không có refund trong Magefan DataLayer/Events) — ads platforms cần tín hiệu này để loại converted value khỏi learning.

# 3. Proposed architecture

```
Magento events (observers/layout blocks)
  view_item · view_category · search · add_to_cart        (browser-context only)
  begin_checkout                                          (browser-context)
  purchase  ← order state transition (server)             (D3: hook point)
  refund    ← creditmemo save_after (server)
        ↓
Secomm_Tracking :: EventNormalizer  → Launchpad Tracking Event (contract §4)
        ↓                                     ↓
BrowserPipeline                        ServerPipeline (outbox table + cron flush)
  plugin trên Magefan dataLayer           VendorAdapterInterface
  → window.dataLayer.push({launchpad_event})   ├─ MetaCapiAdapter      (Graph API)
  → GTM container tags: GA4 / Meta / TikTok    ├─ TikTokEventsAdapter  (Business API)
                                                └─ (extensible: future vendor)
Dedup: deterministic event_id cả 2 pipeline (§7) · Consent gate (§8) · Debug log masked (§10)
```

**Module boundary** (mới, `app/code/Secomm/Tracking/`):

```
etc/module.xml                    sequence: Secomm_Base?, Magento_Sales, Magento_Quote, Magento_CatalogSearch
etc/config.xml + adminhtml/system.xml    enable flags per vendor, credentials, test mode, debug
etc/db_schema.xml + whitelist    bảng outbox `secomm_tracking_event` + log `secomm_tracking_delivery`
etc/events.xml                   refund (creditmemo_save_after), purchase (D3), add_to_cart…
Model/Event/                     Event DTO + EventNormalizer + ItemBuilder
Model/Hash/                      User Data Hasher (SHA-256, normalize email/phone E.164)
Model/Vendor/                    VendorAdapterInterface + MetaCapiAdapter + TikTokEventsAdapter
Model/Pipeline/                  BrowserEventProvider (plugin data cho dataLayer block)
Model/Delivery/                  OutboxRepository, FlushService (cron), RetryPolicy
Model/Consent/                   ConsentEvaluatorInterface + CookieRestrictionEvaluator
Cron/OutboxFlush.php             每 1 phút (pattern như AbandonedCart cron)
Logger/                          masked debug logger (db + file)
Test/Unit + Test/Integration
README.md + CHANGELOG.md         (per CODING_RULES [WARN])
```

**Không modify:** `Magefan_GoogleTagManager*` (chỉ plugin/preference), `Mageplaza/*`, Magento core, theme templates trừ khi cần hook dataLayer (ưu tiên qua plugin + layout XML reference).

# 4. Event contract (canonical)

Canonical event name = GA4 commerce name. Một Launchpad event = JSON:

```json
{
  "event": "purchase",                      // view_item|view_category|search|add_to_cart|begin_checkout|purchase|refund
  "event_id": "purchase-100000123",         // deterministic — §7
  "event_time": 1740000000,                 // unix seconds UTC
  "currency": "VND",
  "value": 1650000.0,
  "order_id": "100000123",                  // increment_id khi có
  "items": [{"item_id": "SKU-RED-M", "item_name": "…", "item_category": "…", "price": 550000.0, "quantity": 3}],
  "user": {
    "email_sha256": "…", "phone_sha256": "…",           // hashed tại normalize-time (§6.4)
    "external_id_sha256": "…",                           // customer_id hash
    "fbp": "fb.1.…", "fbc": "fb.1.…", "ttclid": "…", "ttp": "…",  // cookie params khi có
    "client_ip": "…", "client_user_agent": "…"          // browser events / order remote_ip
  },
  "consent": {"analytics": true, "marketing": true},
  "source": "browser|server"
}
```

**Vendor name mapping** (adapter tự map, normalizer không biết vendor):

| Launchpad | GA4 | Meta (Pixel+CAPI) | TikTok (Pixel+Events API) | Server-side? |
|---|---|---|---|---|
| view_item | view_item | ViewContent | ViewContent | off (default) |
| view_category | view_item_list | ViewCategory (custom) | ViewContent (custom params) | off |
| search | search | Search | Search (custom) | off |
| add_to_cart | add_to_cart | AddToCart | AddToCart | off (default) |
| begin_checkout | begin_checkout | InitiateCheckout | InitiateCheckout | off |
| purchase | purchase | Purchase | **CompletePayment** | **on** |
| refund | refund | Refund (custom event) | Refund (custom event — TikTok không có native) | **on** (chỉ khi enabled) |

- `value` = tổng thực thu theo **order currency** (grand_total; refund = tổng refund của creditmemo đó); `currency` = order currency code (VND display / USD base nếu khác — lấy display chain cho merchant reconcile).
- Items cho Meta: `custom_data.contents[] {id=sku, quantity, item_price}`; TikTok: `properties.contents[] {content_id, content_type="product", quantity, price}`.
- [ASSUMPTION] Refund partial: 1 creditmemo = 1 event `refund`, `event_id = refund-{creditmemo_increment_id}`, value = subtotal refund thực tế của creditmemo đó (không aggregate). Full refund qua nhiều CM = nhiều event — platforms tự cộng.

# 5. Browser-side plan

1. **GA4 (GTM)**: giữ nguyên Magefan dataLayer hiện có (đủ events §4). Việc cần làm: verify mapping names GA4-ready trong GTM container (dataLayer variable → GA4 tag) — phần config GTM account, không code.
2. **Meta/TikTok Pixel**: **inject qua GTM container tags** (không hardcode snippet vào theme — tránh CSP + 1 injection point). `Secomm_Tracking` plugin trên Magefan dataLayer output (hoặc block riêng reference `head.additional`) đẩy thêm `launchpad_event` object vào `window.dataLayer` cùng `event_id` deterministic.
3. Success page purchase: `event_id` server deterministic được render sẵn vào dataLayer (block trên `checkout_onepage_success` layout, đọc order của checkout session — không thêm query hitting DB ngoài last order).
4. Hyvä compat: dataLayer push là plain JS trong phtml block — không RequireJS/jQuery; cookie đọc `fbp/fbc/ttclid/ttp` qua native `document.cookie` tại push-time (không cần server round-trip).
5. Mageplaza OSC: `begin_checkout` từ layout handle `onestepcheckout_index_index` (Magefan đã handle — chỉ verify trong QC).

# 6. Server-side adapters

## 6.1 Meta CAPI (`MetaCapiAdapter`)
- Endpoint: `POST https://graph.facebook.com/v19.0/{pixel_id}/events` (chọn API version mới nhất ổn định lúc implement).
- Auth: system-user access token (config, encrypted — `Magento\Framework\Encryption\Encryptor` cho config backend model).
- Payload: `data[]` (event_name mapped §4, event_time, event_id, action_source=`"website"` cho events có browser counterpart / `"server_integration"` nếu server-only, user_data hashed + client_ip + client_user_agent, custom_data {currency, value, contents, order_id}), `access_token`, `test_event_code` khi test mode.
- Response: `events_received`, per-`fbtrace_id`; log vào delivery table.

## 6.2 TikTok Events API (`TikTokEventsAdapter`)
- Endpoint: `POST https://business-api.tiktok.com/open_api/v1.3/event/boost/` (Events API 1.3; đánh giá 2.0 khi implement nếu GA).
- Auth: header `Access-Token` (long-term token từ TikTok Business Center).
- Payload: `event_source`="web", `event_source_id`={pixel_id}, `data[]`: `event` (mapped §4), `event_time` (giây), `event_id`, `user` {email/ph SHA-256, ttp, ip, user_agent}, `properties` {currency, value, contents, order_id}, `page` {url, referrer} khi có.
- Test mode: `is_debug_click_id`/sandbox header theo docs hiện hành.

## 6.3 Delivery — outbox + cron
- Observer chỉ **normalize + insert outbox row** (`secomm_tracking_event`: event JSON, vendor targets, status `pending`, attempts, next_attempt_at). **Không** gọi API đồng bộ trong request/observer — checkout critical path không bị chặn (CODING_RULES: external calls off checkout critical path).
- Cron `secomm_tracking_outbox_flush` **mỗi phút** (pattern giống AbandonedCart cron — đã có tiền lệ schedule 1 phút trong project) gửi batch ≤50, retry exponential backoff (1m/5m/30m/2h/6h — 5 attempts) rồi `failed` + admin notification (UPSERT vào system message).
- Idempotency re-send: cùng `event_id` bị Meta/TikTok dedup phía platform → resend an toàn.

## 6.4 Hashing / normalization (`Model/Hash/UserDataHasher`)
- `email`: trim + lowercase + SHA-256 hex.
- `phone`: normalize về **E.164 VN-aware** — strip spaces/dashes/dots; bỏ leading zeros; nếu 10-số VN bắt đầu 0 → `+84` + 9 số cuối; rồi SHA-256 hex. (Meta khuyến nghị E.164; TikTok nhận E.164 hash.)
- `external_id`: customer_id string → SHA-256.
- **Rule [BLOCK]:** hasher nhận input từ server-side entity (order billing address, customer) — KHÔNG nhận raw identifier từ client request; raw value không log, không persist — hash ngay trong normalizer before outbox insert.

# 7. Dedup strategy

- **event_id deterministic theo nghiệp vụ, server-generated:**
  - `purchase` → `purchase-{order_increment_id}`
  - `refund` → `refund-{creditmemo_increment_id}`
  - browser-context events → `{event}-{entity_id}-{Ymd}` vd `view_item-{product_id}-{Ymd}` (mỗi pageview 1 lần — generate tại block render, trùng với dataLayer push).
- Browser success-page dataLayer và server CAPI call cho cùng purchase **dùng chung `event_id`** → Meta/TikTok dedup browser+server trên (event_name, event_id). GA4 không nhận server duplicate (browser-only qua GTM) → không cần dedup.
- Magefan `magefanUniqueEventId` (hash random) **không dùng** cho Meta/TikTok tag — GTM variable cho Meta/TikTok tags đọc `launchpad_event.event_id` do Secomm_Tracking đẩy.
- Refund chỉ server → không có browser counterpart → không cần dedup, nhưng vẫn set `event_id` để retry an toàn.

# 8. Consent / privacy hooks

- `ConsentEvaluatorInterface::allows(string $scope): bool` với scope `analytics` | `marketing`.
- Phase 1 impl: `CookieRestrictionEvaluator` — đọc Magento **Cookie Restriction Mode** (`web/cookie/cookie_restriction`) + cookie `user_allowed_save_cookie`. Khi restriction OFF → cho phép tất cả (default VN market). [ASSUMPTION: đủ cho Phase 1 vì CMP riêng out of scope — confirm với TL D5.]
- Gate: browser — dataLayer push vẫn xảy ra nhưng set `consent` flags vào event; GTM container dùng Consent Mode (GA4) / Meta `cookieconsent` param… config phía GTM. Server — marketing events (CAPI/TikTok) bị drop khi `marketing=false`; ghi log `skipped_consent`.
- KHÔNG gửi hashed identifier khi `marketing=false`. IP/user-agent vẫn có thể giữ cho analytics-only theo config (default: drop).

# 9. Refund flow

- Observer `sales_order_creditmemo_save_after` → chỉ khi config `secomm_tracking/refund/enabled = 1` (default OFF theo AC "Refund flow được gửi khi enabled").
- Value = creditmemo `subtotal + discount_refunded`? — **không**: dùng `base_grand_total`-display tương ứng của CM theo display chain (`grand_total`), đúng số tiền hoàn cho khách. Partial refund → event riêng theo [ASSUMPTION] §4.
- Gửi GA4 `refund` (browser KHÔNG có — GA4 refund là server semantics; gửi qua dataLayer admin? Không — GA4 refund event cần Measurement Protocol server call). **[ASSUMPTION]: GA4 refund bỏ qua trong Phase 1** (GA4 refund event yêu cầu MP secret, giá trị thấp cho ads measurement — scope LC-22 là advertising measurement). Meta/TikTok nhận refund qua CAPI/Events API. → D4 confirm.

# 10. Debug / test mode

- Admin config per-vendor: `test_mode` (Meta `test_event_code` — events vào Test Events tab thay vì live; TikTok debug flag), `debug_log` enable.
- Bảng `secomm_tracking_delivery`: `event_id`, vendor, direction (server), request payload **masked** (hash values giữ nguyên vì đã hash; IP mask `1.2.*.*`), HTTP status, response summary (`events_received`), attempts, created_at. Browser events chỉ log khi debug_log ON và chỉ metadata (event, event_id, page) — không log full payload khỏi phình DB.
- File log `var/log/secomm_tracking.log` (masked) cho QC grep nhanh.
- Admin grid (Secomm CORE menu tồn tại — `Secomm_Base` admin shell) đọc delivery table: filter theo event_id/order — công cụ reconcile QC.

# 11. Failure / edge cases

| Case | Behavior |
|---|---|
| Meta/TikTok API down / 4xx/5xx | Outbox retry backoff ×5 → `failed` + admin message; KHÔNG ảnh hưởng order placement (async) |
| Token hết hạn / sai | Response 401/400 → mark `failed` ngay (không retry mù), admin message rõ |
| Order placed nhưng Mollie payment chưa xong | Server purchase chỉ fire khi state đủ điều kiện (D3) — không đếm order abandoned |
| VNPAY IPN confirm async | Như trên — state transition observer bắt |
| Guest checkout | email/phone từ billing address của order (server-side) — vẫn match được CAPI |
| Cookie restriction ON, chưa consent | Marketing events skipped + log; GA4 consent mode qua GTM |
| Double-render success page (F5) | Browser purchase push 1 lần/session (checkout session clear sau success — Magefan pattern); server đã fire đúng 1 (state guard); platform dedup event_id dự phòng |
| Refund khi refund disabled | Không event — đúng config |
| Multi currency (base USD / display VND) | value theo display chain + currency code đi kèm; hash không liên quan |
| Cron chết | Events dồn outbox `pending` — gửi bù khi cron chạy lại; stale >7 ngày → `failed` + message |
| PII trong log | [BLOCK] masked tại logger — hash sẵn + IP mask; unit test assert không có plain email/phone trong log output |

# 12. Compatibility risks

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Magefan GTM dataLayer đổi format khi upgrade version | M | M | Plugin trên interface ổn định (`AbstractDataLayer::eventWrap` output array); feature test e2e; pin version |
| OSC (Mageplaza) layout handle khác default checkout | L | M | Magefan đã có `onestepcheckout_index_index.xml`; QC bắt buộc e2e OSC (BR-004) |
| Hyvä CSP chặn inline pixel | M | M | Inject qua GTM (single origin script); CSP whitelist entry nếu cần |
| FPC cache dataLayer stale (category page) | M | M | view_category event_id theo page + ngày; GTM tag dùng event-push trigger, không phụ thuộc block cache TTL |
| Checkout perf regression | L | H | Observer chỉ insert DB; API calls ở cron — không bao giờ trong request |
| Config credentials lộ | M | H | Encrypted backend model; KHÔNG commit config với token; env-specific |
| Order state logic trùng với VNPAY/Mollie flow | M | H | D3 quyết định hook chính xác; QC với cả 2 payment (Mollie test + VNPAY sandbox) |

# 13. Acceptance criteria

- [ ] AC-001: Trên storefront Hyvä, lần lượt view product → category → search → add cart → vào OSC checkout → place order (Mollie test): GTM container nhận đầy đủ `view_item`, `view_item_list`, `search`, `add_to_cart`, `begin_checkout` trên `window.dataLayer` với `event_id` + `value`/`currency` đúng.
- [ ] AC-002: Sau order success, GA4 (DebugView/GTM preview) nhận `purchase` với `transaction_id` = increment_id, `value` = grand_total display, `currency` = order currency.
- [ ] AC-003: Meta Test Events (test_event_code) nhận browser `Purchase` (Pixel) VÀ server `Purchase` (CAPI) **cùng `event_id`** → Meta UI hiển thị "Deduplication: deduplicated" và KHÔNG double-count.
- [ ] AC-004: TikTok Event Debug nhận browser + server `CompletePayment` cùng `event_id` → hiển thị deduplicated.
- [ ] AC-005: Payload CAPI/Events API chứa `em`/`ph` dạng SHA-256 hex (32 bytes hex); grep payload + `secomm_tracking.log` + delivery table KHÔNG tìm thấy plain email/phone/IP đầy đủ.
- [ ] AC-006: Khi `refund/enabled = 1`, tạo creditmemo (partial + full) → server gửi `refund` event (Meta `Refund`, TikTok custom) với value = CM grand_total, `event_id = refund-{cm_increment_id}`; khi disabled → không event nào.
- [ ] AC-007: Consent — bật Cookie Restriction Mode, shopper chưa accept: marketing events bị skip (log `skipped_consent`); sau accept: events gửi bình thường.
- [ ] AC-008: Debug log + admin delivery grid lọc được theo order/event_id, hiển thị status từng vendor (sent/failed/skipped) + response summary — QC reconcile được mà không cần ssh DB.
- [ ] AC-009: Simulate Meta API 5xx (test hook) → outbox retry theo backoff, sau 5 attempts `failed` + admin message; **order placement + success page KHÔNG bị chặn/lag** (thời gian response không đổi measurable).
- [ ] AC-010: VNPAY sandbox flow — IPN confirm → server purchase fire đúng 1 lần (không fire khi order pending, không fire 2 lần khi IPN + return cùng confirm).
- [ ] AC-011: Regression — OSC e2e với Mollie test payment + VN address dropdown (BR-002/BR-004) pass; no JS error console; checkout response time không tăng >50ms.
- [ ] AC-012: Module `Secomm_Tracking` không modify file nào trong `Magefan/*`, `Mageplaza/*`, `vendor/*` (diff-scan trong QC).

# 14. Test strategy

- **Unit**: normalizer mapping (7 events × 3 vendor), hasher (email/phone VN cases: `0901234567`→`+84901234567`, `+84…`, spaces, uppercase), event_id determinism, backoff schedule, consent evaluator.
- **Integration**: outbox insert từ observers (purchase state transition, creditmemo), flush service với mocked HTTP (ok/4xx/5xx), retry-to-failed, no-plain-PII assertion trên persisted JSON.
- **E2E QC manual** (AC-001..AC-007, AC-010, AC-011): môi trường local + Meta Test Events code + TikTok debug; Mollie test mode + VNPAY sandbox.
- **Perf sanity**: ab/k6 so sánh checkout success TTFB trước/sau (AC-009/AC-011).

# 15. Implementation plan (đề xuất — decompose thành task records sau spec VALID)

1. **TASK scaffold** (Mode C): `Secomm_Tracking` module skeleton + config/admin XML + db_schema (outbox + delivery) + i18n vi/en.
2. **TASK event contract** (Mode B): Event DTO + normalizer + hasher + unit tests (server-side pure logic).
3. **TASK browser pipeline** (Mode B): plugin dataLayer + success-page event_id + cookie capture; GTM container variable/tag setup doc (config-side checklist).
4. **TASK server adapters + delivery** (Mode A — Tier 2 PII/API): MetaCapi + TikTokEvents adapters, outbox flush cron, retry, admin message; integration tests mocked HTTP.
5. **TASK refund + consent** (Mode A — Tier 2 order lifecycle): creditmemo observer, consent evaluator, gating tests.
6. **TASK QC/docs** (Mode B): e2e matrix (AC-001..012), debug grid verify, project-context updates (03/05/06, integration table + API contracts).

Thứ tự: 1 → 2 → (3 ∥ 4) → 5 → 6.

# 16. Estimate theo role (workflow Mode A)

| Role | Ước lượng |
|---|---|
| SA/TL review spec + decisions | 2h–4h |
| Dev (scaffold+contract+browser) | 14h–20h |
| Dev (adapters+delivery+refund+consent) | 18h–26h |
| QC e2e (Meta/TikTok test consoles, Mollie/VNPAY) | 10h–16h |
| Docs/context update | 2h–4h |
| **Total** | **46h–70h** |

---

# Open decisions (Level 2 — TL/SA)

- **D1 — Pixel injection route:** Meta/TikTok pixel qua GTM container tags (khuyến nghị — 1 injection point, CSP-friendly) HAY snippet trực tiếp trong theme? Ảnh hưởng CSP + maintainability.
- **D2 — Module boundary:** một `Secomm_Tracking` duy nhất (khuyến nghị — cohesion cao, 1 outbox) HAY tách `Secomm_TrackingMeta`/`TrackingTikTok`? (Không có module thứ 3+ trên roadmap tracking — YAGNI trừ khi TL thấy need.)
- **D3 — Server purchase hook point:** (a) state transition đến processing/payment-review (khuyến nghị — đúng semantics "đã thanh toán", catch cả Mollie webhook + VNPAY IPN), (b) `checkout_onepage_controller_success_action` (đơn giản nhưng miss webhook-only confirm), hay (c) `sales_order_save_after` + status filter (rủi ro re-fire)? Cần SA xác nhận state matrix của Mollie/VNPAY trên 2.4.8-p5.
- **D4 — GA4 refund:** bỏ qua Phase 1 (khuyến nghị — cần MP secret, ngoài advertising-measurement core) hay include qua Measurement Protocol?
- **D5 — Consent scope Phase 1:** Cookie Restriction Mode là consent gate đủ (khuyến nghị), hay cần hook sâu hơn ( Consent Mode v2 signals cho GA4 trước khi có CMP)?
- **D6 — GA4 server-side (GTM Server Container):** defer (khuyến nghị — out of LC-22 core; Magefan GTMExtra ServerTracker đã có sẵn hook khi cần) hay include?

# Related

- `.ai/records/features/FEAT-31X6N2.md` — canonical record
- `project-context/03_ARCHITECTURE_AND_INTEGRATIONS.md` (integration list sẽ + Meta CAPI, TikTok Events API sau implement)
- `project-context/05_API_CONTRACTS.md` (sẽ bổ sung endpoints §6)
- Magefan GTM docs: https://magefan.com/magento-2-google-tag-manager
- Meta CAPI: https://developers.facebook.com/docs/marketing-api/conversions-api · TikTok Events API: https://business-api.tiktok.com/portal/docs?id=7594845771763714