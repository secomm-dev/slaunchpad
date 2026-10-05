# Evidence — TASK-W5BW4F (GHN create failure surfacing 2 lớp)

Ngày: 2026-09-30 · Mode B · DEC-TASKW5BW4F-001 · Secomm_Ghn only

## 1. Bug gốc (reproduce từ data thật)

- Shipment entity 17 (order 21, increment 2000000002, 2026-09-30 10:57:41): snapshot
  `{"secomm_physical":[[48600,300,300,300]]}` — 1 package 48.6kg, 300×300×300cm (nhập tay).
- `var/log/secomm_ghn.log:558`: `GHN shipment create unavailable. {"status":"UNAVAILABLE","reason":"INVALID_PARCEL"}`
  — **0 HTTP call** (chỉ `calculate_fee` trước đó); `secomm_ghn_shipment` entity 9 =
  `FAILED / INVALID_PARCEL / ghn_order_code NULL`. Admin không thấy gì; "Show Packages" rỗng
  (hành vi BUG-74VGQX).
- Trigger: 300cm > limit 200cm/cạnh (`GhnShipmentConstraints::MAX_SIDE_CM`,
  config `carriers/secomm_ghn/create_max_side_cm`).

## 2. Thay đổi (đã implement)

Layer 1 — pre-commit block: `GhnShipmentSaveValidationObserver` trên
`sales_order_shipment_save_before` (fresh save only, `entity_id <= 0`, gate raw method prefix
`secomm_ghn_`) + `GhnCreateParcelValidator` (shared với service: row usability + limits qua
interpreter) + `PostedPhysicalPackages` (shared reader). Throw `GhnCreateValidationException`
(= LocalizedException; core `Shipping\Controller\Adminhtml\Order\Shipment\Save` catch tại
`:181` → message đỏ + redirect).

Layer 2 — loud post-commit: `GhnCreateOutcomeNotifier` + `GhnCreateReasonLabel`;
`GhnShipmentCreateObserver` non-SUCCESS → admin error message + shipment comment (observer
vẫn never-throws; comment save nằm trong in-flight guard nên không re-fire loop).

Section: `ProviderStatus` block + `provider_status.phtml` + layout `sales_shipment_view.xml`
(cạnh block Actions); packages đọc qua `ShipmentPhysicalPersister::read()` (reuse, không đụng
core modal). i18n en_US + vi_VN.

Service refactor: `resolvePhysicalData()` delegate `GhnCreateParcelValidator::fromPostedRows`
(missing-data message = canonical của validator); constructor: validator vào,
`StoreWeightConverter` ra.

## 3. Unit tests

`vendor/bin/phpunit --bootstrap dev/tests/unit/framework/bootstrap.php app/code/Secomm/Ghn/Test/Unit`
→ **OK (438 tests, 211129 assertions)** — gồm:
- `GhnCreateParcelValidatorTest` (5): valid rows / zero-field + package number / limit
  violation 300cm / valid pass / canonical missing message.
- `GhnShipmentSaveValidationObserverTest` (7): 300cm block / empty row block / missing rows
  block (canonical message) / valid pass / **existing shipment re-save never blocked** /
  non-GHN pass / no-shipment ignore.
- `GhnCreateOutcomeNotifierTest` (4): UNAVAILABLE message (status + reason human + retry hint)
  / COD_REJECTED decision message / unknown token verbatim / failure comment text.
- `GhnShipmentCreateObserverTest` +2 (unavailable → message + comment; success → silent), 2
  construction sites cập nhật (service validator arg; observer notifier arg).

ShippingCore suite: **OK (555 tests)** — 0 thay đổi.

## 4. Compile

`php bin/magento setup:di:compile` → Generated successfully (observer + block mới).

## 5. E2E code-level (CLI, area adminhtml)

- Dispatch `sales_order_shipment_save_before` với fresh shipment (order 21,
  `secomm_ghn_secomm_ghn`) + POST rows 300×300×300 @ 48.6kg →
  **BLOCKED — "GHN create: package #1 length is 300 cm — above the 200 cm per-side limit."**
- Dispatch với order 1 (`flatrate_flatrate`) + cùng rows → **PASS-THROUGH**.
  (Orders 13/20 cũng `secomm_ghn_secomm_ghn` — dispatch đầu tiên block là đúng hành vi.)
- `ProviderStatus` trên shipment 17: `canShow=yes`, status `FAILED`, reason
  "Package data missing, empty, or above the GHN per-package limits (weight / per-side cm).",
  packages `[[48600,300,300,300]]`, `needsRetry=yes`.

## 6. Validator records

`bin/project-ai-validate --check-records --check-specs` → 56 FAIL = **baseline pre-existing**
(stash-verified 2026-09-30, xem memory); `grep W5BW4F` → 0 hit: TASK + DEC records pass.

## 7. Còn lại cho QC/TL (browser, cần admin login)

- AC-001 UI: tạo shipment GHN với package 300cm → save bị chặn, message đỏ, không có
  shipment mới (check `sales_shipment` + `secomm_ghn_shipment`).
- AC-003: shipment view các shipment legacy (11–17) → section "GHN Shipment" hiện status
  FAILED + reason + packages + hint retry CLI.
- AC-004 (happy path): tạo shipment GHN với package hợp lệ trên sandbox → GHN gọi, row
  SUBMITTED, track attach; nếu sandbox fail transient → message đỏ + comment.
