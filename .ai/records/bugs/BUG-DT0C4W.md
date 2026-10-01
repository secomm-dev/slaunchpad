---
id: BUG-DT0C4W
type: bug
title: "[Shipping][Admin] Modal Show Packages trống (chỉ Cancel/Print) + layout sales_shipment_view là dead handle — section GHN/Fulfillment chưa bao giờ render"
project_code: SLP
parent:
external_refs:
  ticket:
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: medium
status: in_review
created: 2026-09-30
updated: 2026-09-30
ticket_ref:
affects_version: Magento 2.4.8-p5 + Secomm_ShippingCore + Secomm_Ghn
decisions: []
decision_assessment: none-material
components:
  - app/code/Secomm/ShippingCore
  - app/code/Secomm/Ghn
source_areas:
  - shipping-carrier
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: true
verified_against_commit:
last_verified: 2026-09-30
supersedes: []
related_tickets: [BUG-74VGQX, TASK-S52DGA, TASK-W5BW4F]
---

# [SLP][BUG-DT0C4W] [Shipping][Admin] Modal "Show Packages" trống + dead layout handle `sales_shipment_view`

## Summary

User report 2026-09-30: modal "Show Packages" trên shipment view chỉ có nút Cancel/Print,
không có thông tin. Investigate lộ **2 root cause**:

1. **Nút hiện sai** — `view/form.phtml:123` gate nút trên `$shipment->getPackages()` **RAW**;
   với shipment của project này column luôn chứa marker Secomm (`secomm_physical`,
   `secomm_fulfillment`) nên luôn truthy, trong khi modal đọc data **đã strip marker**
   (BUG-74VGQX + DEC-TASKS52DGA-001) → luôn rỗng (packed.phtml loop 0 rows;
   `Magento_Shipping/js/packages` chỉ có Print/Cancel).
2. **Dead layout handle** — layout `sales_shipment_view.xml` (Ghn: `ProviderStatus` + `Actions`
   từ TASK-W5BW4F/trước đó; ShippingCore: `FulfillmentStatus` từ TASK-S52DGA) **không bao giờ
   load**: handle thật của trang là `adminhtml_order_shipment_view` (route id `adminhtml` trong
   `Magento_Shipping/etc/adminhtml/routes.xml`); `sales_shipment_view` chỉ là **block name**
   (`View.php:68`). Anchor cũng sai: children của root `Widget\Form\Container` không được echo
   (container template chỉ echo child `form`). Hệ quả: section "GHN Shipment", nút
   Cancel/Return GHN (Actions — dead từ module đầu, thực tế chỉ chạy CLI), section
   "Fulfillment" chưa bao giờ xuất hiện trên trang view.

## Mini Spec

### Goal

- Nút "Show Packages" CHỈ hiện khi shipment có NATIVE packages (label flow) — không bao giờ
  mở modal rỗng.
- Các section read-only render đúng trên trang shipment view (handle + anchor đúng).

### Expected Behavior

- Shipment markers-only / không packages: KHÔNG còn nút Show Packages; thông tin package xem
  ở section "GHN Shipment" (bảng Confirmed packages) / "Fulfillment" trên cùng trang.
- Shipment có native packages (label-flow carrier): nút + modal như core.
- Handle `adminhtml_order_shipment_view`: `ProviderStatus`, `Actions` (kèm formkey),
  `FulfillmentStatus` render theo đúng visibility matrix từng block (template gate giữ nguyên).

### Constraints / Rules

- Không swap/override template core `packed.phtml` (freeze DEC-TASKW5BW4F-001 — modal giữ hành
  vi core); chỉ đổi visibility của nút qua plugin `afterGetShowPackagesButton` (read-side).
- Strip marker dùng chung 1 helper (`PackagingBlockPlugin::stripMarkers`) — không drift giữa
  modal data và button visibility.
- Actions visibility matrix (canShowActions/canCancel/canReturn + tracking state) không đổi —
  chỉ bị "lộ" lên trang nhờ fix handle.

### Out of Scope

- `printPackage` PDF cho markers-only (render rỗng không crash — BUG-74VGQX scope cũ).
- Render facts Secomm BÊN TRONG modal (user chọn ẩn nút thay vì swap template).

### Acceptance Criteria

- **AC-001**: Shipment markers-only (17/19) → `getShowPackagesButton()` = `''` (probe CLI).
- **AC-002**: Handle `adminhtml_order_shipment_view` chứa 3 block Secomm; render-according-
  to-visibility PASS trên shipment 14 (Actions 2648 bytes — Cancel/Return lần đầu hiện),
  15/17/19 (matrix đúng) — probe 7/7 mỗi shipment.
- **AC-003**: Shipment native packages → nút giữ nguyên (unit `FormShowPackagesPluginTest`).
- **AC-004**: Suites ShippingCore + Ghn green; compile OK; validator baseline 56 không đổi.

## Affected Files

- `Secomm/ShippingCore/Plugin/Shipping/PackagingBlockPlugin.php` — extract
  `public static stripMarkers()` (helper dùng chung).
- `Secomm/ShippingCore/Plugin/Shipping/FormShowPackagesPlugin.php` — mới: ẩn nút khi không có
  native packages.
- `Secomm/ShippingCore/etc/adminhtml/di.xml` — register plugin mới.
- `Secomm/{Ghn,ShippingCore}/view/adminhtml/layout/sales_shipment_view.xml` →
  `adminhtml_order_shipment_view.xml` + re-anchor vào `referenceContainer extra_shipment_info`
  (dưới block `form`, echo tại `view/form.phtml:162`).

## Callers (blast radius)

- `Magento\Shipping\Block\Adminhtml\View\Form::getShowPackagesButton()` — consumer duy nhất:
  `view/form.phtml:123-125`.
- Layout rename: cả 3 block Secomm chuyển sang trang thật; không đụng block name nào của core.
- Actions (Cancel/Return) giờ render trong `extra_shipment_info` — form POST + formkey +
  confirm như template sẵn có; ACL controller không đổi.

## Verification & Test Results

Xem `.ai/evidence/BUG-DT0C4W/` (probe + evidence.md). Tóm tắt (dev-complete 2026-09-30):

- Unit: ShippingCore 596 OK (+2: FormShowPackagesPluginTest 5 — thực tế +5 tests mới tổng sau
  refcount), Ghn 446 OK; plugin tests 11 OK.
- `setup:di:compile` OK (interceptor cho plugin mới — DI trap quen thuộc).
- CLI probe (`probe_view_layout.php`): shipment 14/15/17/19 — **28/28 PASS** (block tồn tại +
  render-according-to-visibility + button hidden khi markers-only).
- Browser smoke (Case A/B/C) dành cho QC/TL — hướng dẫn trong evidence.md.

## Notes for TL Review (Tier 2 — shipping)

> **TL/SA: (chờ review)**

- **Actions (Cancel/Return GHN) lần ĐẦU render trên shipment view** sau fix handle — visibility
  matrix (ActionsTest) không đổi nhưng đây là hành vi mới nhìn thấy trên UI; visibility per
  shipment: SUBMITTED+non-terminal → Cancel/Return; FAILED (không order code) → ẩn; tracking
  CANCELLED/DELIVERED → theo matrix.
- Modal giữ nguyên hành vi core (freeze DEC-TASKW5BW4F-001) — thay đổi duy nhất là ẨN nút khi
  modal chắc chắn rỗng (user decision 2026-09-30).
- Shipment 19/17: section GHN Shipment + Fulfillment giờ hiện đúng trên trang — phần bù UX cho
  việc ẩn nút.
