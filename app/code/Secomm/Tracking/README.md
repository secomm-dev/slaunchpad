# Secomm Tracking

Commerce tracking & advertising measurement for Secomm Launchpad (FEAT-31X6N2 / LC-22): one normalized
event layer feeding GA4 (via the existing Magefan GTM container), Meta (Pixel + Conversions API) and
TikTok (Pixel + Events API), with browser/server deduplication, PII hashing and QC-grade debug logging.

## Purpose

- Measure the commerce funnel — `view_item`, `view_category`, `search`, `add_to_cart`, `begin_checkout`,
  `purchase`, `refund` — across GA4, Meta and TikTok for ad optimization on the VN storefront (VND).
- Send purchase/refund server-side (Conversions API / Events API) so signal survives adblock/ITP and
  benefits from hashed advanced matching, while sharing a deterministic `event_id` with the browser
  pixels so Meta/TikTok deduplicate instead of double-counting.
- Never leak plain customer identifiers: emails/phones are normalized (E.164 VN-aware) and SHA-256
  hashed before anything is persisted or transmitted.

## How it works

```
Magento events ──► EventNormalizer ──► TrackingEvent (contract: SPEC-FEAT-31X6N2 §4)
                                        ├── Browser: window.dataLayer "launchpad_event" (plugin on Magefan GTM output)
                                        │     └── GTM container tags: GA4 · Meta Pixel · TikTok Pixel
                                        └── Server: secomm_tracking_event outbox (insert-only in request scope)
                                              └── cron secomm_tracking_outbox_flush (every minute)
                                                    ├── MetaCapiAdapter    → graph.facebook.com /{pixel_id}/events
                                                    └── TikTokEventsAdapter→ business-api.tiktok.com /open_api/v1.3/event/boost/
Dedup: deterministic event_id shared browser/server (purchase-{increment_id})  — DEC-FEAT31X6N2-001
```

- **Outbox pattern**: observers only insert rows; vendor HTTP calls happen exclusively in the cron
  flush (exponential backoff 1m/5m/30m/2h/6h, max 5 attempts) so tracking can never slow checkout.
- **Consent**: gated on Magento Cookie Restriction Mode (`web/cookie/cookie_restriction`) — marketing
  events are skipped and logged when consent is absent (Phase 1 scope, DEC D5).
- **Debug**: per-vendor test modes (Meta `test_event_code`, TikTok debug flag) plus masked delivery
  rows in `secomm_tracking_delivery` for QC reconcile.

## Configuration

Stores → Configuration → **Secomm Extensions → Commerce Tracking** — every flag defaults OFF:

| Group | Fields |
|---|---|
| General | Enable Commerce Tracking · Debug Log |
| Meta | Enable · Pixel ID · System User Access Token (encrypted) · Test Mode · Test Event Code |
| TikTok | Enable · Pixel ID · Events API Access Token (encrypted) · Test Mode (Debug) |
| Refund | Enable Refund Events (opt-in; one event per creditmemo) |

GA4/GTM needs no credentials here — browser events ride the Magefan GTM container; this module only
surfaces the `launchpad_event` object into `window.dataLayer` for the container tags to consume.

## Data ownership

| Table | Purpose |
|---|---|
| `secomm_tracking_event` | Outbox: normalized event JSON (identifiers pre-hashed), status, attempts, next_attempt_at |
| `secomm_tracking_delivery` | Append-only masked delivery log (one row per vendor send attempt) |

## References

- Spec: `.ai/specs/SPEC-FEAT-31X6N2-commerce-tracking.md` (VALID 2026-08-24)
- Decisions: `.ai/records/decisions/DEC-FEAT31X6N2-001.md` (D1–D6)
- Task breakdown: `.ai/records/features/FEAT-31X6N2.md`
