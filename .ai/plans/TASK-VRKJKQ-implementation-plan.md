# TASK-VRKJKQ Implementation Plan — Server adapters + outbox delivery + retry

| Field | Value |
|---|---|
| Specification | tickets/TASK-VRKJKQ-tracking-server-adapters.md (embedded Mini Spec) · canonical parent specs/SPEC-FEAT-31X6N2-commerce-tracking.md §6, §7, §10, §11 |
| Mode | A · Tier 2 (external API contract, PII payload, failure isolation) |

> **Status:** Retro-canonical — distilled 2026-08-27. **TikTok live QC xanh** (Event Debug xác nhận). MetaCapiAdapter **đã remove theo DEC-FEAT31X6N2-002** — Magefan Extra sở hữu Meta.

---

## PART 1 — ANALYSIS

| Quyết định | Kết luận | Nguồn |
|---|---|---|
| Adapter contract | `VendorAdapterInterface`: `isConfigured()` + `mapEventName()` (Launchpad→vendor) + `send()` → `DeliveryResult`; `VendorSendException` mang `retryable` — 5xx/timeout retry, 4xx auth fail ngay | spec §6 |
| TikTok endpoint | `POST business-api.tiktok.com/open_api/v1.3/event/track/` — **bug ban đầu `/event/boost/` 404, fix 27/08** | TikTok docs |
| TikTok HTTP-200-với-code≠0 | Logic error vẫn trong 200 — parse `code`/`message`; 40100-40199 = auth → không retry | docs |
| Flush batch/backoff | Batch ≤50 pending due → per-row try/catch; backoff [60,300,1800,7200,21600]s ×5 → failed; stale >7d fail (khớp policy 7 ngày platform — Meta `2804003` đã gặp thật với order 13/08) | spec §6.3 |
| HTTP chỉ trong cron | Observer chỉ insert; [BLOCK] external call off checkout critical path | CODING_RULES |
| Delivery log masked | `events_received`/`code`/`error.message` cắt ngắn; không token/PII plain (IP mask) | spec §10 |

## PART 2 — IMPLEMENTATION TASKS

| # | Task | Files | Status |
|---|---|---|---|
| 1 | Vendor contract + DeliveryResult + exception | `Model/Vendor/VendorAdapterInterface.php`, `DeliveryResult.php`, `VendorSendException.php` | ✅ |
| 2 | TikTok adapter (endpoint `/event/track/`, debug flag, summarize) | `Model/Vendor/TikTokEventsAdapter.php` | ✅ + QC xanh |
| 3 | ~~Meta adapter~~ | ~~`MetaCapiAdapter.php`~~ | ❌ removed DEC-002 |
| 4 | EnqueueService (insert-only, consent + configured gating) | `Model/Delivery/EnqueueService.php` | ✅ |
| 5 | FlushService (batch + backoff + stale + delivery log) | `Model/Delivery/FlushService.php`, `Cron/OutboxFlush.php` | ✅ runtime QC |
| 6 | Backfill console command (QC utility) | `Console/Command/BackfillPurchase.php` | ✅ runtime QC |
| 7 | Integration test mocked HTTP 3 nhánh | — | ⏳ chưa viết |

## Runtime fixes (lessons)

- `new DeliveryLog()` thủ công → fatal DI → `DeliveryLogFactory->create()`.
- Token lưu qua `bin/magento config:set` là **plaintext** (không qua Encrypted backend) → decrypt nổ; `decryptConfigValue()` giờ nhận cả cipher envelope (`^\d+:\d+:`) lẫn plain.
- `summarize()` phải lộ `error.message` của platform — không thì 400 không debug được.
- Console command **không** gọi `setAreaCode` (bin/magento đã set adminhtml — gọi lại là fatal "Area code is already set").

## QC evidence

TikTok: outbox `sent` (code=0) + Event Debug thấy CompletePayment với event_id deterministic — 2026-08-27.
