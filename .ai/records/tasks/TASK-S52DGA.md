---
id: TASK-S52DGA
type: task
title: Generic Offline Shipment P1 — lối thoát vận hành cho carrier create hard failures
project_code: SLP
parent: {type: feature, id: FEAT-FQWEQ3}
mode: A
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: medium
status: in_review
created: 2026-09-30
updated: 2026-09-30
external_refs: {}
legacy_ids: []
ticket_ref:
decisions: [DEC-TASKS52DGA-001]
decision_assessment: material
decision_refs: [DEC-TASKS52DGA-001]
related_tickets: [TASK-W5BW4F, TASK-9Q5ZAK]
components: [CMP-SHIPPING, CMP-GHN]
source_areas:
  - app/code/Secomm/ShippingCore/
  - app/code/Secomm/Ghn/
changes_project_state: true
changes_architecture: true
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-09-30
supersedes: []
---

# [SLP][TASK-S52DGA] Generic Offline Shipment P1 — lối thoát vận hành cho carrier create hard failures

## Summary

Khi admin tạo shipment cho order đi GHN mà package vi phạm hard constraint deterministic
(vd cạnh 300cm > limit 200cm), layer 1 của TASK-W5BW4F chặn save với lỗi đỏ — đúng về dữ liệu,
nhưng merchant vẫn phải hoàn tất đơn (tự book portal GHN / carrier ngoài / tự giao). Task này
thêm lối thoát tường minh **Create Offline Shipment**: tạo Magento shipment THẬT (native save),
KHÔNG gọi GHN API, KHÔNG provider anchor, KHÔNG retry/reconciliation, KHÔNG claim COD —
fulfillment thủ công. Seam generic thuộc **Secomm_ShippingCore** (fulfillment mode + metadata +
gating + capability contract); GHN chỉ đăng ký capability + consult gate. Xây trên working tree
TASK-W5BW4F (uncommitted, in_review).

## Mini Spec

### Goal

- Admin có hành động tường minh **Create Offline Shipment** khi create bị block bởi deterministic
  constraint — Magento shipment thật, không provider side-effect nào.
- OFFLINE shipment không bao giờ vào GHN create/retry/reconciliation/COD claim — cả lúc save
  lẫn mọi re-save sau đó (comment/track).
- Metadata fulfillment_mode shipment-scoped, carrier-neutral, thuộc ShippingCore.

### Expected Behavior

- Form tạo shipment cho order carrier capable (hiện tại: GHN) hiển thị section Offline với
  optional note; nút [Create Offline Shipment] yêu cầu confirm, post **cùng core save endpoint**
  với `shipment[fulfillment_mode]=OFFLINE`.
- Offline save: gate pre-save GHN SKIP validation; shipment tạo qua cơ chế native hoàn toàn
  (ShipmentLoader → register → DB\Transaction); comment "Offline shipment created (reason)"
  ghi kèm transaction; commit_after persist metadata marker `secomm_fulfillment` trên
  `sales_shipment.packages` + physical facts (nếu có posted rows, KHÔNG validate) + 1 structured
  log line.
- Nút Offline bị chặn attempted trên carrier non-capable (flatrate/GHTK) → fail-closed reject,
  0 write.
- Shipment view offline: FulfillmentStatus block (ShippingCore — Mode/Intended Carrier/Provider
  Shipment: Not Created/Reason/Note) + ProviderStatus (GHN) offline banner; giữ bảng packages.
- Online path byte-identical: nút Submit thường không đổi (hidden input rỗng mặc định).
- Khi online attempt bị chặn bởi token eligible, gate stash eligibility (order-scoped session)
  → form prefill reason + message có hint "…or use Create Offline Shipment".

### Constraints / Rules

- Reuse core Save POST (`admin/shipment/order_shipment/save`) — KHÔNG controller mới, ACL =
  `Magento_Sales::ship` (task §24 preferred), form-key core.
- Metadata = marker JSON `secomm_fulfillment` trên `sales_shipment.packages` (user 2026-09-30;
  zero migration, pattern `secomm_physical`); `PackagingBlockPlugin` strip thêm marker mới.
- Gating: `GhnShipmentCreateObserver` early-return khi **request intent OR persisted metadata
  OFFLINE** (commit_after fire trên mọi save — re-save comment/track không được gọi GHN);
  ShippingCore commit_after observer có in-flight static guard; carrier resolve = raw method
  prefix match (`secomm_ghn_`), cấm `getShippingMethod(true)`.
- Offline-eligible tokens P1: `INVALID_PARCEL`, `INVALID_CONFIGURATION` (transient
  TECHNICAL_ERROR/SERVICE_UNAVAILABLE không bao giờ eligible); list frozen trong DEC.
- KHÔNG đụng: checkout rate/fallback, Shipping Coverage, Secomm_Cod (offline → 0 call vào
  CodCollectionResolverInterface — manual-collection gap được BÁO CÁO, không fix), GHTK,
  FulfillmentCore, vendor code; không ACL mới; không retry guard (task §15 — cấm duplicate
  guard; hazard retry-CLI-on-offline được document).
- Không auto-offline; không fake tracking; no PII trong log; i18n en_US (identity) + vi_VN.

### Out of Scope

- Retry admin button, dedicated offline ACL, Secomm_Cod manual-collection representation.
- CANONICAL_UNRESOLVED / UNSUPPORTED_DESTINATION / PROVIDER_MAPPING_MISSING làm offline-eligible
  (thuộc E-B/E-C theo DEC-TASKW5BW4F-001 mục 5).
- Bảng `secomm_shipment_fulfillment` riêng (user chọn marker JSON 2026-09-30).
- Retry CLI notice cho offline shipment (P2 option, nêu ở final report).
- OMS / routing / cartonization / auto package split / auto carrier switch.

### Acceptance Criteria

- **AC-001**: Offline save (GHN order, package 300cm): shipment tạo, comment + marker metadata +
  physical facts; 0 row `secomm_ghn_shipment`, 0 `secomm_cod_collection`, 0 track, 0 GHN POST
  (unit + E2E local).
- **AC-002**: Re-save offline shipment (add comment/track) → KHÔNG GHN create mới (unit —
  metadata-arm test).
- **AC-003**: Nút Submit thường → online path không đổi (gate vẫn chặn 300cm; valid → GHN create
  như cũ); hidden input rỗng mặc định (unit).
- **AC-004**: Intent trên carrier non-capable → LocalizedException, 0 write (unit).
- **AC-005**: Online attempt bị chặn token eligible → session stash + message hint; form prefill
  (unit).
- **AC-006**: Shipment view: legacy/online không đổi; offline hiện FulfillmentStatus + banner
  ProviderStatus (unit).
- **AC-007**: Suite Ghn (438 baseline) + ShippingCore (555) + Cod green; `setup:di:compile` OK;
  validator baseline 56 FAILs không đổi.

## Affected Files

ShippingCore (seam mới, additive): `Api/Shipment/{FulfillmentMode, CarrierOfflineCapabilityInterface}.php`,
`Model/Shipment/{OfflineCapabilityPool, FulfillmentModeResolver, FulfillmentMetadataPersister,
OfflineEligibilitySession}.php`, `Observer/{ShipmentOfflineIntentValidationObserver,
ShipmentOfflineFulfillmentObserver}.php`, `Plugin/Shipping/PackagingBlockPlugin.php` (strip thêm),
`Block/Adminhtml/Shipment/NewOfflineControl.php`, `Block/Adminhtml/Shipment/View/FulfillmentStatus.php`,
`ViewModel/Shipment/OfflineControl.php`, 2 templates, `etc/{events.xml, di.xml}`, `i18n/*.csv` (mới).
Ghn (small additive): `Model/Shipment/GhnOfflineCapability.php` (mới), `etc/di.xml`,
`Observer/GhnShipment{SaveValidation,Create}Observer.php`, `Block/Adminhtml/Shipment/View/ProviderStatus.php`
+ template, `i18n/*.csv`.

## Verification & Test Results

Dev-complete 2026-09-30 — xem `.ai/evidence/TASK-S52DGA/evidence.md`. Tóm tắt:

- Ghn **446 OK** (438 + 8 mới), ShippingCore **594 OK** (555 + 39 mới), Cod 48 OK; compile OK;
  validator **56 FAIL = baseline**, 0 mới.
- Code-level E2E trên dev DB (cơ chế save native như core Save): shipment tạo + marker
  `secomm_fulfillment` OFFLINE + facts `[[48600,300,300,300]]` + comment; **0** anchor
  `secomm_ghn_shipment`, **0** COD claim, **0** track; re-save không sinh GHN create (C1);
  resolver đọc OFFLINE. ALL PASS.
- ADMIN_SMOKE: BLOCKED_BY_ENVIRONMENT (không browser) — Case A/B/C documented cho chạy tay.
- Defect giữa-dev đã fix + test: persist-tự-chặn qua save_before re-fire → `OfflineRecordingState`
  guard; observer thiếu HttpRequest; Session\Proxy không mockable → Session; getRawMessage
  → getMessage (rendered).

## Notes for TL Review (Tier 2 — shipping/order)

> **TL/SA: (chờ review)** — DEC-TASKS52DGA-001

- Hazard đã biết (documented, không guard theo task §15): retry CLI trên offline shipment có
  snapshot in-limit sẽ tạo GHN order thật cạnh metadata OFFLINE stale — P2 option CLI notice.
- COD gap: order ship-offline hoàn toàn không có ledger row (thu hộ thủ công vô hình với
  Secomm_Cod) — báo cáo, không fix.
- Confirm dialog là UI-only (crafted POST vẫn qua nếu có ACL ship + formkey + carrier capable).
- Marker JSON: metadata không query được (trade-off user chọn); PackagingBlockPlugin phải strip
  marker mới (nếu thiếu → fatal core packaging popup, BUG-74VGQX pattern).
