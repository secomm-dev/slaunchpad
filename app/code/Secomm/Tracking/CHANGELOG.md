# Changelog — Secomm_Tracking

## 1.0.0 — 2026-08-24

### Added (FEAT-31X6N2 — full pipeline)

- **Scaffold (TASK-0TTQRX):** module registration; admin config tree `Secomm Extensions →
  Commerce Tracking` (General / Meta / TikTok / Refund) with every enable flag defaulting
  OFF and vendor tokens stored encrypted; declarative schema `secomm_tracking_event`
  (outbox; UNIQUE event_id+vendor, composite status/next_attempt_at index) +
  `secomm_tracking_delivery` (masked delivery log); `Model\Config` typed reader.
- **Event contract (TASK-NNKTRM):** immutable `TrackingEvent`/`Item`/`UserData` DTOs
  (SPEC §4); `EventNormalizer` (order → purchase, creditmemo → refund, deterministic
  event ids `purchase-{increment_id}` / `refund-{cm_id}` / `{event}-{entity}-{Ymd}`);
  `UserDataHasher` SHA-256 with VN-aware E.164 phone normalization. Unit tests for
  mapping, determinism, E.164 table and PII non-leakage.
- **Browser pipeline (TASK-E0NG8Z):** `LaunchpadEvent` block on head.additional pushes
  `launchpad_event` into `window.dataLayer` for PDP/category/search/cart/OSC/success;
  cookie matching params (fbp/fbc/ttclid/ttp) read client-side at push time; consent
  flags embedded. Zero coupling to Magefan code (DEC D1).
- **Server adapters + delivery (TASK-VRKJKQ):** `VendorAdapterInterface` +
  `MetaCapiAdapter` (Graph API, test_event_code) + `TikTokEventsAdapter` (Events API
  1.3, debug flag); `EnqueueService` (insert-only, consent-gated, failure-isolated);
  `FlushService` cron flush (batch 50, backoff 1m/5m/30m/2h/6h ×5, stale >7d fail,
  non-retryable 4xx fail-fast); masked delivery log + dedicated
  `var/log/secomm_tracking.log` channel.
- **Observers (TASK-8FZ8YX):** `PurchaseFire` on order state TRANSITION into
  processing/complete (DEC D3 — fires once under Mollie webhook + VNPAY IPN double
  confirm); `RefundFire` on creditmemo save gated by opt-in refund flag; both stamp
  consent via `ConsentEvaluatorInterface` (Cookie Restriction Mode, DEC D5) and never
  propagate exceptions into the order flow.
- **Admin + QC (TASK-WY5JRN):** read-only grids under Secomm → CORE → Commerce
  Tracking (Delivery Log, Event Outbox) with vendor/status filters; QC matrix
  (`.ai/evidence/FEAT-31X6N2/qc-matrix.md`) + GTM container checklist
  (`.ai/evidence/FEAT-31X6N2/gtm-container-checklist.md`); i18n vi_VN + en_US.

## 1.1.0 — 2026-08-27

### Changed (DEC-FEAT31X6N2-002 — Meta vendor removed)

- **Removed `Model/Vendor/MetaCapiAdapter.php`** — the licensed Magefan FacebookPixel(+Extra)
  suite owns all Meta tracking (browser pixel + Conversions API + dedup). Same pixel on two
  paths with mismatched event ids was double-counting Purchase.
- DI wiring (`etc/di.xml`): EnqueueService/FlushService adapter lists now TikTok-only.
- Admin config: Meta section removed from `system.xml` + `config.xml` defaults; Meta getters
  and XML path constants removed from `Model\Config`; vendor filter options TikTok-only.
- This module now owns **TikTok only** (server Events API + browser via GTM dataLayer).
- Historical `vendor=meta` rows in outbox/delivery tables remain as-is (no enqueue anymore).
