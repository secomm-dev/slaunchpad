---
id: TASK-PWHG0V
type: task
title: 'Phase GHN-E3 — Magento Tracking/Admin Integration + Label Boundary'
project_code: SLP
parent: {type: feature, id: FEAT-FQWEQ3}
mode: A
specification_level: MINI
spec_status: VALID            # Embedded Mini-Spec dưới đây — consume E1/E2 services, 0 redesign lifecycle/webhook/physical/address
plan: ../../plans/TASK-PWHG0V-implementation-plan.md
risk: high                    # Admin mutation UI (POST) + public carrier tracking — Tier-2; plan approval = signoff
status: in_progress           # activated 2026-09-16
priority: medium
decision_assessment: material
decisions: [DEC-TASK9Q5ZAK-001]
components:
  - CMP-GHN
  - CMP-SHIPPING
source_areas:
  - app/code/Secomm/Ghn/
changes_project_state: true
created: 2026-09-16
updated: 2026-09-16
owner: [dev]
related_tickets: [TASK-GKHXY1, TASK-4ATBC4, TASK-9Q5ZAK]
---

# [SLP][FEAT-FQWEQ3][TASK-PWHG0V] Phase GHN-E3 — Magento Tracking/Admin Integration + Label Boundary

**Prerequisites**: GHN-C closed · GHN-D closed · GHN-E1 closed (r2) · GHN-E2 closed (r2-aligned) ·
address-shipping.md v6 SSOT.

## Progress Log

- **2026-09-16 — activated, audit-first.** 3 sub-slices: E3-A tracking integration,
  E3-B admin Cancel/Return, E3-C label capability audit (conditional implementation; fake label
  forbidden). KHÔNG redesign: lifecycle taxonomy / webhook architecture / Cancel-Return domain
  logic / physical package architecture / address resolution.

- **2026-09-16 — dev-complete, chờ TL review.** E3-A: `getTracking()` implement qua
  `GhnTrackingResultBuilder` (1 provider query, CÙNG mapper, feed processor §11, safe Error cho
  not-found/transport §10, curated display §7/§8); `isTrackingAvailable=true` sau runtime proof;
  fetcher raw mở whitelist (expected_delivery_time + log[] {status,time=updated_date}). KHÔNG
  tracking URL (soft-404 SPA). E3-B: admin controllers POST-only + ACL riêng + block/template
  visibility matrix §16 local-state + message map §19 + `ShipmentReconciler` (non-fatal) sau
  SUCCESS; cancel CLI parity (reconcile sau SUCCESS). E3-C: verdict **SUPPORTED_BUT_DEFERRED** —
  GHN có provider-hosted Print Order (gen-token → print URLs A5/80x80/52x70, token ~30 phút);
  KHÔNG bật native label vì print flow chưa adapt Magento shipment-label contract + bật đổi
  create-trigger + invariant `secomm_physical` → `false` BY DESIGN.

- **2026-09-16 — doc-correction (TL directive, documentation-only + 1 const theo exception
  clause).** Re-read official GHN Print Order docs (developer.ghn.vn + mirror developer.ghn.dev
  khớp nhau): flow hiện hành = `POST /shiip/public-api/v2/a5/gen-token` ({order_codes max 10k
  all-or-nothing, item_index?}) → `data.token` (~30') → provider-hosted print URLs
  `/a5/public-api/printA5|print80x80|print52x70?token=`. Route probe với token invalid → 401
  envelope (auth gate). `v2/shipping-order/print` (E3 probe đầu) KHÔNG phải flow hiện hành —
  404 khi token hợp lệ, docs không liệt kê. Sửa: evidence §4 (rationale mới theo template TL),
  CHANGELOG 0.8.0, CURRENT_STATE, memory; `GhnEndpoints::PRINT_ORDER` (endpoint chết, 0
  consumer) → `GEN_PRINT_TOKEN = 'v2/a5/gen-token'` (material-contradiction exception §3 —
  không có adapter nào được implement). Positive print-token/runtime validation DEFERRED đến
  khi có sandbox token hợp lệ + live order — không phải điều kiện đóng E3 (§6). Verdict giữ
  **SUPPORTED_BUT_DEFERRED**, `isTrackingAvailable=true` + `isShippingLabelsAvailable=false`
  GIỮ NGUYÊN; invariant `secomm_physical` giữ nguyên; external issues (legacy compile-unblock,
  config.php restore, CANONICAL_UNRESOLVED) tách rời khỏi E3 scope.
  di.xml: `CommandListInterface` registration fix (8/8 commands live). Gates: Ghn **312**/0F/0E
  (+39), cross-module **998**/0F/0E, compile GREEN (sau legacy logger fix cross-stream + config.php
  module restore — incidents §R tại evidence), validator 0 fail stream này, grep gates sạch.
  N-Defect ghi nhận: create mới trên dev store fail CANONICAL_UNRESOLVED (address data drift —
  external §R.3); sandbox token cần user rotate (§R.6). Evidence `.ai/evidence/TASK-PWHG0V/`.

- **2026-09-17 — pre-review fix pack (TL directive), 4 findings fixed.** (1) **CRITICAL layout
  formkey**: `sales_shipment_view.xml` trỏ `Magento_Backend::widget/formkey.phtml` (file KHÔNG
  tồn tại → template-not-found crash Shipment View cho GHN shipments; runtime smoke trước đó
  render block trực tiếp nên không bắt được) → sửa thành `admin/formkey.phtml` + layout-wiring
  unit test (XML attrs + class/template existence; negative-proven: sai path → 2 failures).
  (2) **Exception boundary**: builder fetch catch `GhnApiException` thôi (provider failure →
  safe Error; programming Error/TypeError propagate fail-loud); reconcile paths catch
  `\Exception` (best-effort — webhook owns eventual truth); dead `statusOf` catch bỏ (map()
  non-throwing). Tests: auth/ratelimit → Error; TypeError → propagate (builder + reconciler).
  (3) **Reason validation server-side**: >255 chars (`mb_strlen`) → reject + message + redirect,
  KHÔNG truncate, KHÔNG gọi service (tests 255 multibyte accepted / 256 rejected). (4)
  **`GhnActionOutcomeNotifier`** (shared presentation-only message map §19) — controllers compact
  (validate → resolve → service → notify → reconcile-if-success → redirect); semantics giữ
  nguyên. KHÔNG đổi: factories migration, block memoization, carrier literal, inline CSS, label
  adapter (§15 defer). Gates: Ghn **326**/0F/0E; cross-module **1012**/0F/0E; compile GREEN;
  validator 0 fail stream này; Throwable grep: E3 paths sạch (duy nhất webhook controller E1 —
  deliberate 500→retry design, E1 r2 reviewed). Runtime: layout-factory render form_key PRESENT
  + hidden branch 0 bytes + 256-char reason 0 provider call.

## Embedded Mini-Spec

### Goal

Operational Magento integration cho Secomm_Ghn: (A) carrier `getTracking()` + `isTrackingAvailable=true`
chỉ sau runtime proof — reuse 100% `GhnTrackingFetcher`/`GhnStatusMapper`/E1 pipeline (1 mapper,
KHÔNG Magento-only mapping); (B) Admin Shipment View actions Cancel/Return delegate thẳng E2
services (ACL + POST + form key + confirm; không order/shipment mutation, không refund/restock);
(C) label capability audit theo Magento label contract thật — nếu GHN không có artifact tương
thích thì `isShippingLabelsAvailable=false` GIỮ NGUYÊN; KHÔNG fake label (blank/placeholder/
HTML-pretend/screenshot đều cấm).

### Expected Behavior

1. **E3-A**: `Ghn::getTracking(string $tracking): Result|false` — tracking number = GHN
   `order_code` (KHÔNG client_order_code/shipment id/increment id); provider query qua E1 Order
   Info fetcher → Status result (tracking, carrier_title, track_summary = normalized label i18n +
   "GHN status: <raw>", deliverydate/time nếu provider cung cấp, progressdetail[] sanitized chỉ
   status+time — KHÔNG raw payload/PII); business not-found/timeout/5xx → `Error` result an toàn
   (KHÔNG exception, KHÔNG crash Shipment View); thành công có thể feed `TrackingUpdate →
   ShipmentTrackingProcessor` (reconcile qua E1 — KHÔNG persistence path song song). Tracking URL
   chỉ thêm nếu verify được official public URL — KHÔNG đoán pattern. 0 track row mới.
2. **E3-B**: `POST /admin/secomm_ghn/shipment/cancel` + `/returnShipment` (input = shipment_id;
  resolve provider identity NỘI BỘ từ `secomm_ghn_shipment` — KHÔNG nhận order_code từ request) →
   `GhnCancelService`/`GhnReturnService` → outcome → admin message per §19 text (KHÔNG raw provider
   response; UNKNOWN_RESULT KHÔNG có nút retry) → redirect back Shipment View. Visibility từ local
   state (SUBMITTED + order_code + latest normalized status): hide Cancel khi terminal
   DELIVERED/RETURNED/CANCELLED/LOST/DAMAGED; hide Return khi CANCELLED/RETURNED — provider vẫn
   là authority. Cancel reason UI = enum GHN-CO001/CO002/CO003/GHN-CANCEL-OTHER với merchant label
   i18n + optional reason. Sau SUCCESS chạy reconcile E1 (fetcher → processor) trong try/catch
   (thất bại reconcile không break admin flow; webhook là nguồn cuối).
3. **E3-C**: audit label capability theo current official GHN Print Order docs (đã verify
   2026-09-16: `POST /shiip/public-api/v2/a5/gen-token` → `data.token` → provider-hosted print
   URLs A5/80x80/52x70; endpoint `v2/shipping-order/print` trong matrix §12 KHÔNG phải flow
   hiện hành) + Magento label contract vendor thật
   (`_doShipmentRequest`/`LabelGenerator`/`requestToShipment`). Outcome A/B/C theo brief §25;
   verdict = SUPPORTED_BUT_DEFERRED (`false` BY DESIGN — print flow chưa adapt Magento label
   contract + invariant `secomm_physical`). Positive print-token runtime validation deferred
   (cần sandbox token hợp lệ + live eligible order) — thuộc GHN Label Adapter follow-up.

### Constraints / Rules

- KHÔNG đột biến Magento business state: 0 `$order->setState/setStatus/cancel`, 0 shipment-entity
  cancel, 0 refund/restock/notification; 0 write trực tiếp `secomm_carrier_tracking_state`
  (E1 processor là writer duy nhất).
- `isTrackingAvailable=true` CHỈ sau khi `getTracking()` implement + runtime-tested (không flag
  tạm); `isShippingLabelsAvailable` độc lập với tracking (§27).
- Admin mutations: POST + form key + ACL (`Secomm_Ghn::shipment_actions` → `cancel_shipment`/
  `return_shipment`) + confirm; KHÔNG mutation qua GET; không retry tự động.
- Shipment View render KHÔNG gọi provider sync (local state only — §30); tracking popup/action
  mới gọi provider.
- Không track row trùng lặp (§28): GHN-D TrackAttacher dedupe theo số — reuse; legacy shipments
  thiếu track → repair path = retry CLI hiện có (re-attach dedupe), KHÔNG auto-modify setup:upgrade.
- Log allowed: shipment id, order_code, action, normalized outcome, provider status; cấm:
  token/secret/phone/address/raw payload.
- 1 mapper duy nhất (`GhnStatusMapper`) cho webhook + fetcher + admin tracking display.
- KHÔNG taxonomy mới (§32); KHÔNG frontend tracking portal custom (§31).

### Out of Scope

Legacy Secomm_GiaoHangNhanh cutover (GHN-F) · RATE type 5 · fallback · VietMap · COD policy ·
refund/RMA/customer-return portal · OMS · polling cron mới · MQ · custom frontend tracking ·
label implementation nếu audit không đạt outcome A.

### Acceptance Criteria

- AC-1: `getTracking()` runtime-proven trên sandbox order thật (A: success; D: provider unavailable
  → Error an toàn; E: unknown code → not-found an toàn; B/C delivered/LOST hiển thị đúng khi có
  state khả thi).
- AC-2: `isTrackingAvailable=true` + `getTracking` sử dụng cùng `GhnStatusMapper` (unit-locked);
  0 track row mới; failure không crash.
- AC-3: Admin Cancel POST → `GhnCancelService` (unit-locked delegation); 4 outcome types render
  đúng message; ACL + form key verified; 0 order mutation.
- AC-4: Admin Return POST → `GhnReturnService`; label nút "Request GHN Return"; 0 RMA/refund.
- AC-5: Label audit có verdict A/B/C với evidence thật (endpoint probe + Magento contract);
  `isShippingLabelsAvailable` phản ánh đúng verdict; KHÔNG fake label.
- AC-6: Regression xanh (Ghn/ShippingCore/VietNamAddress/Ghtk); compile 0 lỗi stream này;
  validator 0 fail stream này; grep gates (0 order mutation / 0 tracking-state write / 0 fake
  label); unrelated working-tree failures document riêng.
