---
id: FEAT-31X6N2
type: feature
project_code: SLP
parent: null
legacy_ids: []
title: 'Commerce Tracking & Advertising Measurement — GA4/GTM + Meta CAPI + TikTok Events API'
mode: A                      # PII hashing + checkout OSC + order lifecycle + 3rd-party API contract → Tier-2
specification_level: FULL
spec_status: VALID           # approved 2026-08-24 (user acting as TL/SA, /approve D1–D6) — DEC-FEAT31X6N2-001 accepted
specification_ref: ../../specs/SPEC-FEAT-31X6N2-commerce-tracking.md
risk: high
status: dev-complete            # 6/6 tasks code xong; chờ phpunit + QC matrix + TL review (Tier-2)
created: 2026-08-24
updated: 2026-08-27
ticket_ref:
  external: LC-22            # external PM ticket — canonical ID là FEAT-31X6N2
  tasks:
    - TASK-0TTQRX                   # scaffold module + config + db_schema (Mode C, Tier-2 DB)
    - TASK-NNKTRM                   # event contract: DTO + normalizer + hasher + unit tests (Mode B)
    - TASK-E0NG8Z                   # browser pipeline: dataLayer plugin + event_id + cookie capture (Mode B)
    - TASK-VRKJKQ                   # server adapters Meta CAPI/TikTok + outbox delivery + retry (Mode A)
    - TASK-8FZ8YX                   # purchase/refund observers + consent gating (Mode A)
    - TASK-WY5JRN                   # QC e2e matrix + debug grid + context docs (Mode B)
decisions:
  - DEC-FEAT31X6N2-001          # D1–D6: pixel route, module boundary, purchase hook, GA4 refund/SS defer, consent Phase 1 (accepted)
  - DEC-FEAT31X6N2-002          # Meta rút khỏi module — Magefan FB Pixel(+Extra) sở hữu Meta; Secomm_Tracking chỉ còn TikTok (accepted 2026-08-27)
decision_assessment: material
decision_refs: [DEC-FEAT31X6N2-001, DEC-FEAT31X6N2-002]
decision_approval_summary:
  total: 2
  pending_approval: []
  approved: [DEC-FEAT31X6N2-001, DEC-FEAT31X6N2-002]
  rejected: []
  superseded: []
  last_synced: 2026-08-27
components:
  - CMP-TRACKING              # Secomm_Tracking — event layer + vendor adapters (mới)
source_areas:
  - app/code/Secomm/Tracking/                          # NEW — toàn bộ module
  - app/code/Magefan/GoogleTagManager/                 # inspected — browser dataLayer, KHÔNG modify (plugin only)
  - app/code/Magefan/GoogleTagManagerPlus/             # inspected — add_to_cart/view_item_list observers
  - app/code/Magefan/GoogleTagManagerExtra/            # inspected — search event + ServerTracker hook (defer D6)
  - app/design/frontend/Secomm/launchpad/              # QC regression (không sửa trừ D1 chọn snippet trực tiếp)
changes_project_state: true
changes_architecture: true    # module mới + 2 integration bên ngoài (Meta CAPI, TikTok Events API)
changes_integration: true
changes_known_limitations: true   # đóng gap "không có tracking quảng cáo đa nền tảng"
verified_against_commit: 56d47ade
last_verified: 2026-08-24
supersedes: []
---

# [SLP][FEAT-31X6N2] Commerce Tracking & Advertising Measurement — GA4/GTM + Meta CAPI + TikTok Events API

<!-- CANONICAL RECORD — Full spec: SPEC-FEAT-31X6N2-commerce-tracking.md. Backend-first,
     browser-side tái dùng Magefan GTM; Meta CAPI + TikTok Events API self-build. -->

## Context

Ticket LC-22 (external PM): tracking chuẩn GA4 + Meta + TikTok, browser + server-side, đo funnel + tối ưu quảng cáo VN. Repo đã có `Magefan_GoogleTagManager` 2.8.0 (+Plus/Extra, enabled `app/etc/config.php:401-403`) cover GA4 dataLayer cho đầy đủ 7 commerce events (verified file:line trong spec §1) — kể cả Hyva + Mageplaza OSC layout handles. **Gap:** không có Meta/TikTok (browser pixel lẫn server API), không có dedup contract, PII hashing, refund flow, consent gating, delivery debug.

**Risk:** Tier-2 — chạm PII (hash email/phone), checkout OSC (begin_checkout), order lifecycle (purchase/refund hooks), third-party API contract mới (Meta Graph, TikTok Business) → Mode A.

## Architecture (tóm tắt — canonical trong spec; cập nhật DEC-FEAT31X6N2-002)

```
Magento events ──► EventNormalizer ──► Launchpad Tracking Event (contract §4)
                                        ├─► Browser: window.dataLayer (plugin trên Magefan GTM output)
                                        │     └─ GTM container tags: GA4 · TikTok Pixel
                                        │        (Meta browser pixel: Magefan FacebookPixel — DEC-002)
                                        └─► Server: outbox table ─cron 1'─► VendorAdapters
                                              └─ TikTokEventsAdapter (business-api.tiktok.com v1.3/event/track/)
   Meta (browser + CAPI + dedup): Magefan FacebookPixel + Extra — ngoài module này (DEC-002)

Dedup: deterministic event_id chia sẻ browser/server (purchase-{increment_id}) — áp cho TikTok
PII: SHA-256 normalize+hash tại server (E.164 VN phone) — raw không persist/log
```

- **Browser-side:** tái dùng Magefan dataLayer (GA4); TikTok pixel qua GTM container (D1) — không hardcode snippet theme. **Meta browser do Magefan FB Pixel render trực tiếp** (không qua GTM).
- **Server-side:** outbox + cron 1 phút + exponential backoff ×5 — KHÔNG gọi API trong checkout request (critical path rule). Chỉ TikTok.
- **Dedup:** server-generated deterministic `event_id` render vào success-page dataLayer → TikTok khấu (event_name, event_id); Meta dedup là nội bộ Magefan (eventID của họ, DEC-002).

## Key requirements (feature-level; AC chi tiết AC-001..012 trong spec §13)

- 7 events chuẩn: view_item · view_category · search · add_to_cart · begin_checkout · purchase · refund; value/currency theo display chain; items[] với SKU/qty/price.
- Purchase/refund server-side bắt buộc; browser+server cùng event_id → Meta/TikTok dedup, KHÔNG double-count.
- PII hash SHA-256 (email lowercase-trim; phone E.164 VN-aware); raw không bao giờ persist/log — [BLOCK] rule.
- Refund chỉ gửi khi `refund/enabled` (default OFF); consent gate qua Cookie Restriction Mode Phase 1 (D5).
- Debug/test mode: Meta test_event_code, TikTok debug, delivery table + admin grid (masked) cho QC reconcile.
- Không modify `Magefan/*` · `Mageplaza/*` · core — plugin only.

## Sub-ticket breakdown (spec VALID 2026-08-24 → decomposed)

- **[TASK-0TTQRX](../../tickets/TASK-0TTQRX-tracking-scaffold.md)** — scaffold `Secomm_Tracking` + admin config + db_schema outbox/delivery. → `proposed` (Mode C, Tier-2 DB).
- **[TASK-NNKTRM](../../tickets/TASK-NNKTRM-tracking-event-contract.md)** — event contract: DTO + normalizer + UserDataHasher + unit tests. → `proposed` (Mode B).
- **[TASK-E0NG8Z](../../tickets/TASK-E0NG8Z-tracking-browser-pipeline.md)** — browser pipeline: dataLayer plugin + deterministic event_id + cookie capture + GTM config checklist. → `proposed` (Mode B, QC OSC e2e).
- **[TASK-VRKJKQ](../../tickets/TASK-VRKJKQ-tracking-server-adapters.md)** — Meta CAPI + TikTok Events API adapters + outbox flush/retry + delivery log. → `proposed` (Mode A — Tier 2 API/PII).
- **[TASK-8FZ8YX](../../tickets/TASK-8FZ8YX-tracking-refund-consent.md)** — purchase/refund observers (state transition) + consent gating. → `proposed` (Mode A — Tier 2 order lifecycle).
- **[TASK-WY5JRN](../../tickets/TASK-WY5JRN-tracking-qc-docs.md)** — QC e2e matrix AC-001..012 + admin delivery grid + context docs. → `proposed` (Mode B).

Thứ tự thực thi: TASK-0TTQRX → TASK-NNKTRM → (TASK-E0NG8Z ∥ TASK-VRKJKQ) → TASK-8FZ8YX → TASK-WY5JRN.

## Resolved decisions (2026-08-24, user acting as TL/SA — `/approve` D1–D6 theo khuyến nghị)

- **D1 — pixel route:** Meta/TikTok pixel inject qua **GTM container tags** (không hardcode snippet theme).
- **D2 — module boundary:** **một `Secomm_Tracking` duy nhất** (1 outbox/delivery table; YAGNI per-vendor split).
- **D3 — purchase hook:** **order state transition → processing** — đúng semantics "đã thanh toán", catch cả Mollie webhook + VNPAY IPN async confirm.
- **D4 — GA4 refund:** defer Phase 1 — refund gửi Meta/TikTok server-side; GA4 Measurement Protocol là work item riêng nếu cần.
- **D5 — consent Phase 1:** **Cookie Restriction Mode** (`web/cookie/cookie_restriction` + `user_allowed_save_cookie`).
- **D6 — GA4 server-side (GTM sTAG):** defer — ngoài LC-22 core; Magefan `GoogleTagManagerExtra/ServerTracker` sẵn hook khi cần.
- → [DEC-FEAT31X6N2-001](../decisions/DEC-FEAT31X6N2-001.md) (accepted).

## Risks

- Double-count nếu event_id lệch nguồn browser/server — mitigation: server single source, render xuống dataLayer.
- Magefan upgrade đổi dataLayer format — plugin trên interface ổn định + pin version + e2e test.
- Checkout perf — observer chỉ insert DB, API luôn ở cron (AC-009/AC-011 đo).
- Token/credential lộ — encrypted config backend, không commit.
- VNPAY IPN + Mollie webhook confirm async — D3 state matrix quyết; AC-010 test cả hai.

## References

- Spec: [SPEC-FEAT-31X6N2-commerce-tracking](../../specs/SPEC-FEAT-31X6N2-commerce-tracking.md)
- Magefan GTM: https://magefan.com/magento-2-google-tag-manager
- Meta CAPI: https://developers.facebook.com/docs/marketing-api/conversions-api
- TikTok Events API: https://business-api.tiktok.com/portal/docs?id=7594845771763714