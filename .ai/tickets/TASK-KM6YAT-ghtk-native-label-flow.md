# TASK-KM6YAT — GHTK order submit qua Magento native shipping-label flow (COD resolver, tracking/label native, no outbox)

**Legacy ID:** SL-016 *(re-identified 2026-08-20 per DEC-027; mapping: .ai/toolkit/legacy-id-map.yaml)*

**Type:** Task (resume + redirect TASK-KV328X theo hướng native — không tách FEAT)
**Priority:** High
**Estimate:** ~16–24h
**Mode:** A (Tier-2: shipping + order/shipment lifecycle + external API — AGENTS §9/§11/§12)
**Placement:** `app/code/Secomm/Ghtk/` + `app/code/Secomm/ShippingCore/` (factory method)
**Risk tier:** Tier 2
**Author:** AI draft · **Date:** 2026-08-17 · **Status:** Dev complete *(2026-08-17: Tasks 1-6 done, 110 unit tests green, DI compile OK — chờ TL code review (Tier 2) + QC manual sandbox; evidence: [TASK-KM6YAT-evidence](../runtime/evidence/TASK-KM6YAT/TASK-KM6YAT-evidence.md))*

## Description

Chuyển `Secomm_Ghtk` sang **Magento native shipping-label lifecycle**: merchant tạo shipment thường → Magento-only, zero GHTK API. Merchant check **Create Shipping Label** → native flow → submit order lên GHTK → `label_id`/tracking persist qua native (`sales_shipment_track` + `shipping_label` PDF). COD amount resolve tách riêng (`CodAmountResolverInterface`); origin reuse chain TASK-NDASAD. Không observer auto-push, không custom Online/Offline framework, không outbox table.

## Acceptance Criteria

### Native label flow
- [x] **AC-1 (Carrier online):** `Ghtk extends AbstractCarrierOnline`; `isShippingLabelsAvailable()` = true; lean `getContainerTypes()`; `requestToShipment()` override — packages aggregated thành 1 GHTK order (weight = Σ package weights, fallback product weights).
- [x] **AC-2 (Normal shipment = no API):** shipment không check label → KHÔNG có bất kỳ GHTK API call nào (không observer, không plugin save-after). `collectRates` độc lập — không dùng GHTK rate ⇒ không tạo GHTK order.
- [x] **AC-3 (Label flow submits):** check Create Shipping Label → POST GHTK Submit Order đúng payload; native preconditions (store info + origin) được native enforce.
- [x] **AC-4 (No false success):** GHTK API fail → `LocalizedException`/response errors → **shipment KHÔNG được save**, admin thấy error rõ; KHÔNG track/labelPersist.
- [x] **AC-5 (Native persistence):** success → `tracking_number` + label PDF (`\Zend_Pdf` generated) → native `LabelGenerator` persist `sales_shipment_track` (carrier ghtk) + `shipment.shipping_label`; snapshot (partner id, label_id, pick_money) vào shipment comment (audit).

### Origin + COD + fee semantics
- [x] **AC-6 (Origin qua provider):** pick side resolve qua `ShippingContextFactory::fromShipment()` → `GhtkOriginProvider` → `PickupAddressResolver` (cùng chain như rate — DEC-TASKNDASAD-001). KHÔNG đọc `shipping/origin/*` trực tiếp trong mapper. Shipper contact (pick_name/pick_tel) từ native `Shipment\Request`.
- [x] **AC-7 (COD resolver):** `CodAmountResolverInterface` tách khỏi mapper: prepaid/non-COD → `pick_money = 0`; COD (method ∈ config list, default `cashondelivery`) → `base_total_due` (deposit/partial payment-safe). KHÔNG hard-code `grand_total`.
- [x] **AC-8 (is_freeship):** luôn `1` trong release này (Magento đã charge shipping tại checkout — carrier không thu thêm; không double-charge).
- [x] **AC-9 (Partial + COD fail-fast):** shipment partial (chưa ship hết order) + COD > 0 → `LocalizedException` rõ ràng, KHÔNG gửi full-order COD nhiều lần. KHÔNG build allocation engine.
- [x] **AC-10 (Declared value + partner id):** `value` = subtotal của item trong shipment; partner ORDER_ID = `ghtk-{order_increment}-{seq}` (deterministic, shipment-id-independent); POST single-attempt (no auto-retry); `ORDER_ID_EXIST` → error + hướng dẫn manual check.

### Tests + config + docs
- [x] **AC-11 (Tests):** 8 nhóm test tối thiểu theo yêu cầu §10 (normal-no-api, label-calls-api, success-tracking, failure-no-false-success, prepaid-0, COD-total_due, partial-COD-fail, origin-via-provider) pass cùng existing suite (84+ tests).
- [x] **AC-12 (Config):** system.xml thêm `cod_method_codes` (multi-select/text, default `cashondelivery`) — không field nào khác; i18n vi+en.
- [x] **AC-13 (Docs/records):** CHANGELOG/README update; DEC-TASKKM6YAT-001 accepted trước implement; TASK-KV328X ticket note redirect; evidence file.

## Out of Scope (explicit)

- Webhook inbound status sync (giữ deferred như DEC-TASKKV328X-001).
- GHTK label PDF fetch từ GHTK endpoint (Q-EXT chưa verify — dùng generated PDF; swap sau).
- `ORDER_ID_EXIST` auto-reconcile (fetch by ORDER_ID) — follow-up sau khi verify API.
- Live tracking lookup (`isTrackingAvailable()` giữ false — tracking number vẫn persist + hiển thị).
- COD allocation engine / split fulfillment / `Secomm_ShippingFulfillment`.
- Ahamove refactor.

## Risks

- **Supersede DEC-TASKKV328X-001** (accepted) — cần SA/TL approve DEC-TASKKM6YAT-001.
- Q-EXT: GHTK order API field contract chưa verify sandbox (mapper defensive; QC verify trước go-live).
- Sync API call trong admin request (5s timeout — cùng nature native carriers).
- Package popup container types cần QC UI thực (lean `PACKAGE` type).
- COD payment method chưa tồn tại trong project (BA backlog) — COD branch inert cho đến khi COD land; prepaid path là đường sống.

## Related

- Plan: [TASK-KM6YAT plan](../plans/TASK-KM6YAT-implementation-plan.md) · Spec: [ghtk-native-label-flow.md](../specs/SPEC-TASK-KM6YAT-ghtk-native-label-flow.md) · Decision: [DEC-TASKKM6YAT-001](../records/decisions/DEC-TASKKM6YAT-001.md) (accepted — supersedes DEC-TASKKV328X-001)
- Resumes/redirects: [TASK-KV328X](TASK-KV328X-ghtk-order-sync.md) · Builds on: [TASK-NDASAD](TASK-NDASAD-shippingcore-origin-contract.md) + DEC-TASKNDASAD-001 · DEC-TASKKV328X-002 partial-resolve (B3/B8/B14 + defaults B1/B4/B6/B7)
