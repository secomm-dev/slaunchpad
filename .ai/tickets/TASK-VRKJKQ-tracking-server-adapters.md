# TASK-VRKJKQ — Server adapters (Meta CAPI + TikTok Events API) + outbox delivery + retry

**Type:** Task (slice của FEAT-31X6N2 — server-side delivery core)
**Priority:** High
**Estimate:** ~16h
**Mode:** A (external API contract + PII payload + HTTP client — Tier-2 core của feature)
**Placement:** `app/code/Secomm/Tracking/Model/Vendor/`, `app/code/Secomm/Tracking/Model/Delivery/`, `app/code/Secomm/Tracking/Cron/`, `app/code/Secomm/Tracking/Test/Integration/`
**Risk tier:** Tier 2 (third-party API contract, PII payload, failure isolation)
**Author:** AI draft · **Date:** 2026-08-24 · **Status:** Dev complete + QC TikTok xanh *(2026-08-27 **DEC-FEAT31X6N2-002**: MetaCapiAdapter REMOVED — Magefan FB Pixel+Extra sở hữu Meta; endpoint TikTok fix `/event/boost/`→`/event/track/`; TikTok server send QC passed: Event Debug xác nhận CompletePayment + event_id deterministic. Live send Meta của module không còn áp dụng — verify Meta CAPI qua Magefan Extra riêng)*
**Specification:** MINI — embedded dưới đây · canonical parent: [SPEC-FEAT-31X6N2](../specs/SPEC-FEAT-31X6N2-commerce-tracking.md) §6, §7, §10, §11 · Plan: [TASK-VRKJKQ plan](../plans/TASK-VRKJKQ-implementation-plan.md)

## Approach

> Retro-canonical 2026-08-27 — distilled từ work đã dev-complete + runtime fixes.

1. **Adapter contract:** `VendorAdapterInterface` — `isConfigured()`, `mapEventName()` (Launchpad→vendor name), `send()` → `DeliveryResult` (httpStatus + masked summary); `VendorSendException` mang `retryable` flag — 5xx/timeout retry, 4xx auth fail ngay. TikTok `code != 0` trong HTTP 200 cũng tính lỗi logic (40100-40199 auth → không retry).
2. **TikTok payload:** `event_source=web` + `event_source_id={pixel}` + `data[0]` (event/event_time/event_id/user hash/properties.contents). Endpoint **`/open_api/v1.3/event/track/`** — bug ban đầu `/event/boost/` 404 (sai tên từ spec cũ, fix 27/08). Test mode → `is_debug_event: true`.
3. **FlushService cron 1':** batch ≤50 pending due (next_attempt_at null/lte now) → per-row try/catch (1 row lỗi không phá batch) → backoff [60,300,1800,7200,21600]s ×5 attempts → failed; stale >7 ngày fail luôn (khớp policy 7 ngày của platform — Meta reject `2804003` event cũ, đã gặp thật với order 13/08).
4. **Delivery log masked:** mọi outcome ghi `secomm_tracking_delivery` khi Debug Log on — `response_summary` giữ `events_received`/`code`/`error.message` cắt 120-200 ký tự; không token, không PII plain (IP mask). `summarize()` đã fix để lộ `error.message` của Meta/TikTok — trước đây chỉ giữ fbtrace_id, không debug được 400.
5. **Runtime fixes:** `new DeliveryLog()` thủ công → `DeliveryLogFactory`; decrypt token plaintext (set qua `config:set` không mã hóa) → `decryptConfigValue` handle cả cipher envelope lẫn plain (regex `^\d+:\d+:`).

**QC còn lại:** integration test mocked HTTP 3 nhánh (sent/failed-no-retry/retry-backoff) chưa viết — hiện mới verify live TikTok send.

## Description

- `Model/Vendor/VendorAdapterInterface` — `supports(TrackingEvent): bool`, `send(TrackingEvent): DeliveryResult` (http_status, events_received, raw error).
- `Model/Vendor/MetaCapiAdapter` — Graph API `POST /v{v}/{pixel_id}/events` (spec §6.1): payload mapping purchase→Purchase, refund→Refund, action_source, user_data hashed + fbp/fbc, custom_data contents; `test_event_code` khi test mode.
- `Model/Vendor/TikTokEventsAdapter` — `POST business-api.tiktok.com/open_api/v1.3/event/boost/` (spec §6.2): purchase→CompletePayment, refund→Refund (custom), user hashed + ttp/ttclid, properties contents; Access-Token header; debug flag khi test mode.
- `Model/Delivery/OutboxRepository` + `FlushService` — batch ≤50, exponential backoff 1m/5m/30m/2h/6h ×5 attempts → failed + admin system message; 4xx không retry (fail ngay + message).
- `Cron/OutboxFlush.php` — impl thật (TASK-0TTQRX đã đăng ký schedule).
- HTTP client: `Magento\Framework\HTTP\Client` với timeout 10s — KHÔNG bao giờ chạy trong request scope (chỉ cron).
- Delivery log: ghi `secomm_tracking_delivery` mọi outcome (masked response).

## Mini Spec

### Goal

Server-side purchase/refund đến được Meta + TikTok, dedup với browser bằng chung event_id, failure không ảnh hưởng storefront/checkout.

### Expected Behavior

- Outbox row `pending` → cron flush → vendor send → `sent` (2xx) / retry schedule / `failed`.
- Cùng event_id resend (cron chạy 2 lần) → platform dedup, không double-count (Meta events_received vẫn 1).
- Consent `marketing=false` → row `skipped` không gọi API.
- Token sai (401/400) → fail ngay + admin message rõ ràng; KHÔNG retry.
- Cron chết >7 ngày → stale rows mark failed + message.

### Constraints / Rules

- [BLOCK] KHÔNG gọi vendor API trong observer/request — chỉ qua cron flush (CODING_RULES: external calls off checkout critical path).
- Payload chỉ chứa hashed user data (from TASK-NNKTRM DTO) — adapter không nhận raw identifier.
- Log masked: IP `1.2.*.*`, hash giữ nguyên (đã hash sẵn), không log access token.
- Adapter failure throw → catch trong FlushService, không propagate phá cron batch (các row khác vẫn send).

### Out of Scope

Observer insert outbox (TASK-8FZ8YX — task này chỉ consume) · GA4 server-side sTAG (DEC D6 defer) · refund enable flag logic (TASK-8FZ8YX) · admin grid UI (TASK-WY5JRN).

### Acceptance Criteria

- [ ] **AC-1:** Integration test với mocked HTTP (ok/4xx/5xx): đúng 3 nhánh status transition (sent/failed-no-retry/retry-then-failed với backoff schedule assert).
- [ ] **AC-2:** Meta Test Events: server Purchase nhận với event_id = `purchase-{increment_id}` khớp browser → UI hiển thị "Deduplication: deduplicated".
- [ ] **AC-3:** TikTok Event Debug: CompletePayment server + browser cùng event_id → deduplicated.
- [ ] **AC-4:** Consent marketing=false → row skipped, 0 HTTP call (mock assert).
- [ ] **AC-5:** Delivery table log mọi outcome kèm response_summary masked; grep log + table: 0 plain email/phone/IP đầy đủ.
- [ ] **AC-6:** Place order với Meta endpoint simulate 5xx: checkout success TTFB không tăng measurable (async chứng minh).

**Parent:** [FEAT-31X6N2](../records/features/FEAT-31X6N2.md) · **Spec:** SPEC-FEAT-31X6N2 §6/§7/§10/§11 · **DEC:** DEC-FEAT31X6N2-001 (D3 hook consumer, D4 refund Meta/TikTok only)