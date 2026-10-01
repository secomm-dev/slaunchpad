---
id: BUG-74VGQX
type: bug
title: "[Shipping][Admin] TypeError packed.phtml khi mở shipment view — marker secomm_physical đụng shape packages native"
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
status: in_progress
created: 2026-09-30
updated: 2026-09-30
ticket_ref:
affects_version: Magento 2.4.8-p5 + Secomm_ShippingCore + Secomm_Ghn
decisions: []
decision_assessment: none-material
# Knowledge-consolidation contract (RM-07)
components:
  - app/code/Secomm/ShippingCore
source_areas:
  - shipping-carrier
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: true
verified_against_commit:
last_verified: 2026-09-30
supersedes: []
---

# [SLP][BUG-74VGQX] [Shipping][Admin] TypeError packed.phtml khi mở shipment view — marker secomm_physical đụng shape packages native

## Summary

Admin shipment view 500 với `TypeError: Magento\Framework\DataObject::__construct(): Argument #1 ($data) must be of type array, null given` tại `packed.phtml:16`. Nguyên nhân: `ShipmentPhysicalPersister` (DEC-TASK9Q5ZAK-001) persist snapshot vật lý vào `sales_shipment.packages` dưới marker key `secomm_physical` (shape `{'secomm_physical': [[weightG, l, w, h], ...]}` — string key, **không có `params`**), trong khi template core `packed.phtml` và `Pdf\Packaging` iterate **mọi** entry rồi `new DataObject($package->getParams())` không guard. Claim *"collision-safe by construction"* trong docblock persister chỉ đúng ở write path — ở **read path** admin UI luôn render packages khi column non-empty, nên mọi shipment GHN save qua admin (7 shipments tính đến 2026-09-30) đều làm trang view crash.

## Mini Spec

### Goal

- Admin shipment view + printPackage PDF load bình thường với shipment có marker `secomm_physical` trong `sales_shipment.packages`.
- Marker bị lọc khỏi data **hiển thị** của block `Magento\Shipping\Block\Adminhtml\Order\Packaging` (1 điểm chạm che cả 2 crash sites); data model giữ nguyên.

### Expected Behavior

- Shipment có packages **marker-only**: trang view load 200, packed window không render entry lạ (modal rỗng); PDF không crash.
- Shipment **mixed** (marker + native shape `params`): native packages render như cũ, marker bị lọc (unit test).
- Shipment **không có packages**: hành vi không đổi.
- `ShipmentPhysicalPersister::read()` vẫn trả snapshot cho shipment có marker — GHN retry replay không hồi quy (plugin lọc ở block, không đụng model).

### Constraints / Rules

- Filter **đúng marker key** qua constant `ShipmentPhysicalPersister::PACKAGES_KEY`; không over-filter entry lạ khác (native shape phải nguyên vẹn).
- Plugin read-side only — không đụng data `sales_shipment.packages`, không đụng write path DEC-TASK9Q5ZAK-001 (frozen).
- Plugin `after` (không `around`), `strict_types`, Magento coding standard; không sửa vendor.
- **Amendment** (gộp theo quyết định 2026-09-30): claim *"collision-safe by construction"* của DEC-TASK9Q5ZAK-001 bị thu hẹp — chỉ đúng khi label popup không chạy; read path của admin UI render packages độc lập với `isShippingLabelsAvailable()`. Giải pháp là read-side guard, write design giữ nguyên.

### Out of Scope

- Đổi write format marker sang native shape (đụng DEC-TASK9Q5ZAK-001 frozen + phải migrate 7 shipments hiện có).
- Data migration cho 7 shipments đã persist marker (read-side filter lo hết case cũ).
- Audit các consumer khác của `getPackages()` ngoài template + PDF (đã audit: chỉ 2 crash sites, cùng đi qua block).

### Acceptance Criteria

- **AC-001**: Admin shipment view cho shipment marker-only (entity 17, increment 2000000002) load 200, không TypeError; packed window không render marker.
- **AC-002**: Shipment mixed marker + native — native render bình thường, marker bị lọc (unit test cover).
- **AC-003**: `printPackage` PDF shipment marker-only không crash.
- **AC-004**: `ShipmentPhysicalPersister::read()` vẫn trả physical snapshot cho shipment có marker (unit test hoặc smoke).
- **AC-005**: `setup:di:compile` pass; suite unit ShippingCore green; `bin/project-ai-validate --check-records --check-specs` pass.

## Steps to Reproduce

1. Tạo shipment cho đơn GHN qua admin (điền package information) — observer `sales_order_shipment_save_commit_after` chạy `persist()`.
2. Mở trang shipment view của shipment đó trong admin.

## Expected Behavior

Trang view load bình thường; packed window chỉ hiển thị packages native (nếu có).

## Actual Behavior (Before Fix)

500 — `TypeError: DataObject::__construct(): Argument #1 ($data) must be of type array, null given` tại `packed.phtml:16` (`new DataObject($package->getParams())` với entry marker không có `params`).

## Root Cause Analysis

1. **Write shape tự định danh**: `ShipmentPhysicalPersister::persist()` ghi `{'secomm_physical': [[weightG, l, w, h], ...]}` vào `sales_shipment.packages` (merge, preserve entry native).
2. **Read path không guard**: template `packed.phtml:14-16` và `Pdf\Packaging::_drawPackageBlock()` (dòng 161–179) iterate mọi entry, `new DataObject($package->getParams())` không kiểm tra `params` tồn tại → `getParams()` null → TypeError (PHP 8 strict).
3. **Giả định sai trong design claim**: docblock DEC-TASK9Q5ZAK-001 cho rằng label popup "chỉ chạy khi `isShippingLabelsAvailable()` true" nên hai shape không bao giờ đụng nhau — đúng cho write path, sai cho read path: block `shipment_packed` render trên **mọi** shipment view khi packages non-empty, không phụ thuộc khả năng label của carrier.
4. **Crash site thứ 2 cùng gốc**: `printPackage` PDF dùng `getBlockSingleton(Packaging::class)->getPackages()` — cùng method, cùng pattern unguard.

## Affected Files

- [`app/code/Secomm/ShippingCore/Plugin/Shipping/PackagingBlockPlugin.php`](app/code/Secomm/ShippingCore/Plugin/Shipping/PackagingBlockPlugin.php) — mới: `afterGetPackages` lọc marker.
- [`app/code/Secomm/ShippingCore/etc/adminhtml/di.xml`](app/code/Secomm/ShippingCore/etc/adminhtml/di.xml) — mới: declare plugin.
- [`app/code/Secomm/ShippingCore/Model/Physical/ShipmentPhysicalPersister.php`](app/code/Secomm/ShippingCore/Model/Physical/ShipmentPhysicalPersister.php) — sửa docblock (amendment claim).
- [`app/code/Secomm/ShippingCore/Test/Unit/Plugin/Shipping/PackagingBlockPluginTest.php`](app/code/Secomm/ShippingCore/Test/Unit/Plugin/Shipping/PackagingBlockPluginTest.php) — mới: unit test.
- [`app/code/Secomm/ShippingCore/CHANGELOG.md`](app/code/Secomm/ShippingCore/CHANGELOG.md) — entry Fixed.

## Callers (blast radius)

- `Magento\Shipping\Block\Adminhtml\Order\Packaging::getPackages()` — consumer: `packed.phtml` (shipment view), `Pdf\Packaging::_drawPackageBlock()` (printPackage). Block chỉ chạy adminhtml.
- Không đụng: `ShipmentPhysicalPersister::read()` (lấy packages từ shipment model, không qua block), GHN label retry, collection flow.

## Verification & Test Results

Xem `.ai/evidence/BUG-74VGQX/evidence.md`. Tóm tắt (dev-complete 2026-09-30, chờ TL):

- Unit: `PackagingBlockPluginTest` 4/4 OK; full suite ShippingCore 555 tests OK.
- `setup:di:compile` OK.
- CLI verify (area adminhtml, shipment entity 17): raw model keys `["secomm_physical"]`, `block getPackages()` → `[]`, `persister read()` → snapshot OK (totalWeightG=48600).
- Validator `--check-records --check-specs`: 56 FAIL — baseline pre-existing (giống hệt khi stash thay đổi), BUG này không gây fail mới.
- AC-001 (browser) + AC-003 (printPackage click) để dành QC/TL verify cần admin login.

## Notes for TL Review (Tier 2 — shipping)

> **TL/SA: (chờ review)**

- Fix read-side plugin — không đổi write design DEC-TASK9Q5ZAK-001, không migrate data. 7 shipments hiện có được che bởi filter lúc render.
- Amendment claim: docblock persister đã sửa, ghi nhận ở Mini-Spec Constraints — gộp vào BUG record này theo quyết định 2026-09-30 (không tạo DEC mới).
- Điểm cần TL xác nhận: filter chỉ đúng marker key (constant), các entry shape lạ khác **không** bị ẩn — nếu muốn defensive thêm (drop mọi entry thiếu `params`) thì là scope riêng.
