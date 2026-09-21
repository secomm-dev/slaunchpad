# Implementation Plan: TASK-PWHG0V — GHN-E3 Magento Tracking/Admin Integration + Label Boundary

## Metadata

| Field | Value |
|-------|-------|
| Task | TASK-PWHG0V (parent FEAT-FQWEQ3) — GHN-E3, 3 sub-slices A/B/C |
| Mode | A (Admin mutation UI + public carrier tracking → Tier-2; plan approval = signoff) |
| Specification | Mini-Spec (embedded) trong [records/tasks/TASK-PWHG0V.md](../records/tasks/TASK-PWHG0V.md) — MINI, VALID |
| Contract source | Magento vendor thật (`AbstractCarrierOnline::getTrackingInfo/isTrackingAvailable/isShippingLabelsAvailable`, `Tracking\Result/Status/Error`, `details.phtml`/`progress.phtml` field contract — audit §A dưới) + matrix `.ai/evidence/TASK-FMBBSD/ghn-api-contract-matrix.md` §12 print (NOT_USED — probe trong slice) + E1/E2 services |
| Reuse (KHÔNG redesign) | lifecycle taxonomy · webhook architecture · Cancel/Return services (`GhnCancelService`/`GhnReturnService`/`GhnActionOutcome`) · physical package architecture · address resolution · `GhnTrackingFetcher` + `GhnStatusMapper` + `ShipmentTrackingProcessor` |
| Out of Scope | GHN-F cutover · RATE type 5 · fallback · VietMap · COD/refund/RMA · polling mới · MQ · frontend portal · label implementation trừ khi audit outcome A |

## Vendor audit kết quả (§3/§23 — installed Magento 2.4.8-p5, KHÔNG theo trí nhớ)

- `getTracking($tracking)` KHÔNG được declare ở core (gọi dynamic từ `getTrackingInfo()`) — carrier
  tự declare; trả `\Magento\Shipping\Model\Tracking\Result` chứa `Status`/`Error`, hoặc `false`.
- `Status` = DataObject; template `tracking/details.phtml` tiêu thụ: `getTracking`, `getCarrierTitle`,
  `getErrorMessage` (set → generic "not available" row), `getTrackSummary` (Info row), `getUrl`,
  `getStatus`, `getSignedby/getDeliveryLocation/getShippedDate/getService/getWeight`,
  `getDeliverydate/getDeliverytime`; `progress.phtml` tiêu thụ `getProgressdetail[]`
  = `deliverydate/deliverytime/deliverylocation/activity`.
- `Error::getErrorMessage()` core hardcode generic — Error result = safe unavailable message (§10).
- Admin tracking: "Track this shipment" → cùng frontend popup route (explicit request — §30 OK);
  `isTrackingAvailable=true` thêm GHN vào admin Add-Tracking dropdown + popup routing.
- Label: `isShippingLabelsAvailable=true` sẽ kích hoạt core "Create Shipping Label" flow →
  `_doShipmentRequest` per-package loop — xung đột kiến trúc GHN-D observer; chỉ bật nếu probe
  artifact thật + preserve `secomm_physical` (hard invariant).

## Approach

E3-A: `GhnTrackingResultBuilder` (GHN-owned) wrap fetcher → `Status`/`Error` result; `Ghn::getTracking`
delegate; flip `isTrackingAvailable` sau implement + runtime; feed processor sau fetch thành công;
sanitized progressdetail (status label + time only). E3-B: Adminhtml `Controller/Adminhtml/Shipment/{Cancel,ReturnShipment}`
+ adminhtml `routes.xml` (frontName `secomm_ghn`) + `etc/acl.xml` (`Secomm_Ghn::ghn` →
`shipment_actions` → `cancel_shipment`/`return_shipment`) + block/template buttons trên
`sales_shipment_view` (local-state visibility, POST form + confirm + form key) + message map §19 +
reconcile sau SUCCESS (try/catch non-fatal). E3-C: sandbox probe `v2/shipping-order/print`
(canceled order trước; fresh probe nếu bị từ chối) + audit response shape (URL vs PDF bytes) →
verdict A/B/C; lean expectation B — giữ `isShippingLabelsAvailable=false`, document adapter
tương lai.

## Files affected

| File | Change |
|------|--------|
| `Model/Tracking/GhnTrackingResultBuilder.php` | new — fetch → Magento Result (Status/Error) + processor feed |
| `Model/Carrier/Ghn.php` | `isTrackingAvailable()` → true + `getTracking()` implement; `isShippingLabelsAvailable` giữ false (docblock cites audit verdict) |
| `i18n/en_US.csv` + `i18n/vi_VN.csv` | new — normalized status labels + admin action/reason/message strings |
| `etc/adminhtml/routes.xml` | new — frontName `secomm_ghn` (admin area) |
| `etc/acl.xml` | new — Secomm_Ghn::ghn tree |
| `Controller/Adminhtml/Shipment/Cancel.php` + `ReturnShipment.php` | new — POST-only, form key, ACL, ownership resolve (shipment_id → provider row), service delegate, §19 messages, redirect, reconcile non-fatal |
| `Block/Adminhtml/Shipment/View/Actions.php` + `view/adminhtml/layout/sales_shipment_view.xml` + `templates/shipment/actions.phtml` | new — buttons/forms, local-state visibility matrix §16 |
| `Model/Tracking/ShipmentReconciler.php` | new (thin) — fetcher → processor per shipment, dùng cho webhook-less sync sau admin action (E1 pipeline, KHÔNG path song song) |
| `Test/Unit/...` | builder/carrier/controllers/block/reconciler tests (§38/§39/§40) |
| `CHANGELOG.md` 0.8.0 · evidence `.ai/evidence/TASK-PWHG0V/` · CURRENT_STATE · memory | admin |

## Verification

- Suites: Ghn scoped + cross-module (Ghn/ShippingCore/VietNamAddress/Ghtk) 0F/0E; E1/E2 green.
- `setup:di:compile` 0 lỗi stream này (external legacy failures document riêng); validator 0 fail
  stream này.
- Grep gates: 0 order mutation · 0 tracking-state write (ngoài processor) · 0 fake-label artifacts.
- Runtime (dev store + sandbox): Tracking A/D/E (+B/C khi state khả thi) qua getTracking thật;
  Admin Cancel/Return POST thật nếu admin session curl khả thi (fallback: unit-locked contracts +
  rendered-page smoke, ghi rõ trong report).

## Risk & open points cho TL

1. `Error` result core hardcode generic message — custom reason KHÔNG render được trong popup
   template; chấp nhận (§10 chỉ yêu cầu safe unavailable message).
2. Admin URL segment `returnShipment` (PHP reserved word — tránh class `Return`).
3. Label probe trên cancelled order có thể bị provider từ chối → tạo fresh probe order (cancel sau).
