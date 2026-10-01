---
id: DEC-TASKS52DGA-001
title: 'Generic Offline Shipment P1: reuse core save POST + fulfillment_mode param; metadata = marker JSON secomm_fulfillment trên sales_shipment.packages; ACL native Magento_Sales::ship; eligibility = INVALID_PARCEL + INVALID_CONFIGURATION; nút luôn hiện khi carrier capable'
status: accepted             # user decisions 2026-09-30 (4 vòng AskUserQuestion + plan approval) — TL gate tại review
owners: [tl, sa]
decision_type: architecture
approval_date: 2026-09-30
created: 2026-09-30
last_verified: 2026-09-30
verified_against_commit:
supersedes: []
superseded_by:
work_items: [TASK-S52DGA]
---

# Decision Record: Generic Offline Shipment P1

## Status

Accepted (2026-09-30 — user decisions qua 4 AskUserQuestion + plan approval; TL gate Tier-2 tại
code review). Bổ sung cho cluster TASK-9Q5ZAK / TASK-W5BW4F — KHÔNG supersede, PENDING-first
(DEC-TASKW5BW4F-001) giữ nguyên cho transient.

## Decision Type

Architecture — seam generic mới trong Secomm_ShippingCore (fulfillment mode + offline operational
flow), additive composition; GHN giữ nguyên provider-create integration.

## Decisions

1. **Offline save = reuse core Save POST** (`admin/shipment/order_shipment/save`) + form param
   `shipment[fulfillment_mode]=OFFLINE` (option A). KHÔNG dedicated controller: tránh duplicate
   ShipmentLoader/QuantityValidator/register/DB\Transaction; ACL = native `Magento_Sales::ship`
   (user chọn; task §24 preferred), form-key core. Hidden input rỗng mặc định, JS gắn giá trị khi
   click nút Offline (C3 — nút Submit thường phải byte-identical) + force-uncheck
   `create_shipping_label` + `confirm()`. Crafted POST `create_shipping_label` + offline →
   `Ghn::_doShipmentRequest` throw loud (0 provider call).
2. **Metadata = marker JSON `secomm_fulfillment`** trên `sales_shipment.packages` (user chọn
   marker thay vì bảng `secomm_shipment_fulfillment` — zero schema migration, pattern
   `secomm_physical` của DEC-TASK9Q5ZAK-001). Marker chỉ ghi cho OFFLINE (absence = ONLINE);
   idempotency = read-marker-then-write; `PackagingBlockPlugin` strip thêm marker mới (read-side
   guard BUG-74VGQX). Trade-off đã lộ: không query được bằng SQL.
3. **Eligibility P1 frozen**: `INVALID_PARCEL` + `INVALID_CONFIGURATION` — 2 token deterministic
   mà pre-save gate chặn. Transient (`TECHNICAL_ERROR`, `SERVICE_UNAVAILABLE`) và token
   address-class (`CANONICAL_UNRESOLVED`, `UNSUPPORTED_DESTINATION`, `PROVIDER_MAPPING_MISSING`)
   KHÔNG eligible (address-class thuộc E-B/E-C). Eligible → gate stash eligibility
   (order-scoped backend session, ShippingCore-owned `OfflineEligibilitySession`) + message có
   hint offline; form prefill reason từ stash.
4. **Nút Offline luôn hiện** khi carrier capable (user chọn; không đòi fail online trước).
   Capability contract `CarrierOfflineCapabilityInterface` (getCarrierCode +
   isOfflineCreationEnabled) + pool DI-array trong ShippingCore; `GhnOfflineCapability` đăng ký
   `'secomm_ghn'`. Intent trên carrier non-capable → **reject fail-closed** ở save_before
   (LocalizedException, 0 write).
5. **Gating (provider-submission gating thuộc ShippingCore ownership)**:
   - `GhnShipmentCreateObserver` early-return khi **request intent OR persisted metadata
     OFFLINE** — C1 correctness: commit_after fire trên MỌI save; offline shipment không có
     anchor SUBMITTED để idempotency-guard, nên re-save comment/track phải được metadata-arm
     chặn.
   - `GhnShipmentSaveValidationObserver` skip khi offline intent (offline không submit provider,
     package invalid là chấp nhận được).
   - ShippingCore commit_after observer có in-flight static guard (C2 — own persist re-save
     re-fire).
   - Carrier resolve = raw method prefix match (`code.'_'`), cấm `getShippingMethod(true)` (C4).
6. **COD**: offline → 0 call vào `CodCollectionResolverInterface` (GHN observers gated) → 0
   ledger row → đúng "no GHN COD collection claim". Gap manual/offline collection representation
   trong Secomm_Cod được BÁO CÁO, không fix (cấm redesign Secomm_Cod).
7. **Retry**: không guard (task §15 cấm duplicate guard) — hazard retry-CLI-on-offline
   (snapshot in-limit → tạo GHN order thật cạnh metadata stale) documented, P2 option CLI notice.
8. **Alternatives bị loại**: (a) dedicated ShippingCore controller + ACL
   `Secomm_ShippingCore::offline_shipment` — duplicate core save mechanics + ACL phân biệt mà P1
   không cần; (b) bảng `secomm_shipment_fulfillment` — user chọn marker (zero migration);
   (c) nút chỉ hiện sau failed attempt (session-only) — user chọn always-visible-when-capable;
   (d) full sync pre-commit create — đã loại ở DEC-TASKW5BW4F-001.

## Consequences

- Offline shipment = Magento shipment thật, hết mọi automation không đụng tới (không anchor →
  tracking reconcile/webhook/cron không có candidate; retry chỉ khi operator chủ động chạy CLI).
- Metadata không query được SQL (trade-off marker); consumers của `sales_shipment.packages`
  render-side phải qua plugin strip.
- Nút Offline capabilities-driven: carrier mới đăng ký capability là có offline mà ShippingCore
  không đổi code; GHTK/flatrate không có → hidden + server-side reject.
- Working tree layer lên TASK-W5BW4F (uncommitted, in_review).

## Work Items

- TASK-S52DGA (implementation, FEAT-FQWEQ3 cluster).
