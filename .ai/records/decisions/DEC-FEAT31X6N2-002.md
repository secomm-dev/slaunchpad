---
id: DEC-FEAT31X6N2-002
legacy_ids: []
title: 'Meta vendor rút khỏi Secomm_Tracking — Magefan FacebookPixel(+Extra) sở hữu toàn bộ Meta (browser + CAPI); Secomm_Tracking chỉ còn TikTok'
status: accepted
owners: [tl]
decision_type: architecture
approval_date: 2026-08-27
created: 2026-08-27
last_verified: 2026-08-27
verified_against_commit: 56d47ade
supersedes: []          # partially amends DEC-FEAT31X6N2-001 D4 context; không supersede toàn bộ
superseded_by:
work_items: [FEAT-31X6N2]
---

# Decision Record: Meta vendor rút khỏi Secomm_Tracking

## Context

FEAT-31X6N2 (LC-22) spec VALID 2026-08-24 thiết kế Secomm_Tracking với 2 vendor adapters: MetaCapiAdapter + TikTokEventsAdapter (DEC-FEAT31X6N2-001). Trong quá trình QC (2026-08-25..27), CAPI Meta của Secomm đã chạy thông (probe `events_received:1`), TikTok Events API đã `sent` + Event Debug xác nhận.

Đồng thời phát hiện repo đã có `Magefan_FacebookPixel` + `FacebookPixelPlus` v2.8.0 (enabled, `app/etc/config.php:400-401`) — team **đã mua license Magefan Extra** cho Conversions API. Cùng Pixel ID `226878939252252` trên 2 đường (Magefan browser + Secomm CAPI server) với eventID random của Magefan (`AbstractPixel.php:84-87`) → Meta **double-count Purchase** (dedup không khớp vì event_id 2 bên khác nguồn sinh).

## Decision (TL quyết ngày 2026-08-27 — "extension đã mua là phải dùng")

1. **Meta:** toàn bộ (browser pixel + CAPI server-side + dedup) do **Magefan FacebookPixel + Extra** sở hữu. Yêu cầu: cài `Magefan_FacebookPixelExtra` và bật Conversions API trong config — không có Extra thì Meta chỉ browser-side.
2. **Secomm_Tracking rút Meta:** đã xóa `MetaCapiAdapter`, DI wiring, admin config section, Config getters, VendorOptions. Module chỉ còn **TikTok** (Events API server + browser qua GTM dataLayer).
3. **TikTok:** vẫn Secomm_Tracking sở hữu (Magefan không có module TikTok trong repo) — module KHÔNG được disable.
4. Data cũ: row `vendor=meta` trong `secomm_tracking_event`/`delivery` giữ nguyên làm history; không enqueue meta mới.

## Consequences

- Scope LC-22 thay đổi: AC-002/AC-003/AC-005 (Meta phần CAPI) giờ do **Magefan Extra** đảm nhiệm thay vì code dự án — QC cần verify Extra CAPI riêng (config + Test Events), không còn qua outbox/delivery log của Secomm.
- Dedup Meta Purchase browser/server là nội bộ Magefan (eventID của họ); Secomm không can dự Meta nữa.
- TikTok dedup contract (D3 event_id deterministic) vẫn nguyên vẹn.
- Spec SPEC-FEAT-31X6N2 §6.1 (MetaCapiAdapter) được đánh dấu superseded-by-Magefan; §6.2 TikTok vẫn hiệu lực.
- Nếu license Extra thực tế chưa có → Meta server-side = 0 cho đến khi mua/cài; TL chấp nhận rủi ro này khi quyết định.

## Affected components

`app/code/Secomm/Tracking/` (removed: `Model/Vendor/MetaCapiAdapter.php`; edited: `etc/di.xml`, `etc/config.xml`, `etc/adminhtml/system.xml`, `Model/Config.php`, `Ui/Component/Listing/Column/VendorOptions.php`).

## Related records

- Feature: FEAT-31X6N2 (amends scope)
- Sibling: DEC-FEAT31X6N2-001 (D1–D6 — phần Meta của các quyết định đó nay do Magefan đảm nhiệm)
- DECISIONS.md index: DEC-FEAT31X6N2-002
