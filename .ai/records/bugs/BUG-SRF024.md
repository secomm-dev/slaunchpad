---
id: BUG-SRF024
type: bug
title: Admin Delivery Time Date Off calendar opens at wrong position
project_code: SLP
parent:
external_refs:
  tickets: SLP-147
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: low
status: in_progress
created: 2026-09-04
updated: 2026-09-04
ticket_ref:
affects_version: Magento 2.4.8-p5 + Hyvä 3.x (default theme 1.5.2) + Mageplaza DeliveryTime 4.1.4
decisions: []
decision_assessment: none-material
# Knowledge-consolidation contract (RM-07)
components:
  - app/code/Secomm/AdminCalendarFix
  - vendor/magento/magento2-base/lib/web/mage/calendar.js (read-only root cause)
source_areas:
  - admin-ui
  - mage-calendar
changes_project_state: true
changes_architecture: false
changes_integration: false
changes_known_limitations: true
verified_against_commit:
last_verified: 2026-09-04
supersedes: []
---

# [SLP][BUG-SRF024] Admin Delivery Time Date Off calendar opens at wrong position

<!-- External ticket: SLP-147. Scope: admin-only UI fix — jQuery UI datepicker của trường Date Off (Stores → Configuration → Mageplaza → Delivery Time → Date Off) mở sai vị trí khi click icon calendar. Không đụng logic delivery date phía storefront/checkout. -->

## Summary

Trong admin configuration, click icon calendar của ô **Date Off** (field array `Mageplaza\DeliveryTime\Block\Adminhtml\Config\Backend\DateOff`) mở datepicker trôi lên sát đầu trang thay vì neo cạnh ô input. Root cause nằm ở core `lib/web/mage/calendar.js::_overwriteFindPos` (Magento 2.4.8-p1+, upstream issue [magento/magento2#40083](https://github.com/magento/magento2/issues/40083) — chưa có fix chính thức), không phải ở Mageplaza DeliveryTime. Fix qua module riêng `Secomm_AdminCalendarFix` (requirejs mixin `mage/calendar`), không sửa Mageplaza in-place (§7.1) và không sửa `lib/web/` core.

## Mini Spec

### Goal

Datepicker trong admin (mở từ mọi input gắn widget `mage/calendar`, điển hình ô Date Off của Mageplaza DeliveryTime) hiển thị đúng vị trí — neo cạnh ô input đã click — bất kể vị trí input trong trang và trạng thái scroll.

### Expected Behavior

- Khi click calendar icon (hoặc focus input), `#ui-datepicker-div` render ngay dưới ô input (hoặc phía trên nếu không đủ chỗ dưới viewport) — đúng hành vi chuẩn của jQuery UI datepicker trước 2.4.8-p1.
- Đúng cả khi trang đã scroll, cả khi input nằm sâu dưới đáy trang dài (case Date Off là field cuối section `mpdeliverytime`).
- Tọa độ tính theo document (tương thích `position: absolute` trên `body`): `getBoundingClientRect() + window.pageXOffset/pageYOffset` — khôi phục đúng semantics của `$.offset()` mà stock jQuery UI `_findPos` dùng.

### Constraints / Rules

- **Không sửa** `app/code/Mageplaza/*` in-place (§7.1) và **không sửa** `lib/web/` (core) — fix toàn bộ nằm trong `app/code/Secomm/AdminCalendarFix` (`view/adminhtml/requirejs-config.js` + mixin JS).
- Mixin phải override method `_overwriteFindPos` trên widget (re-register `$.widget`), KHÔNG patch trực tiếp `$.datepicker.constructor.prototype._findPos` khi load — vì `_create` của từng instance sẽ ghi đè lại bản override hỏng của Magento.
- Phải cover cả widget `mage.dateRange` (dùng ở date-range filter admin) vì nó extends constructor `mage.calendar` gốc — re-register `mage.calendar` một mình không đổi prototype chain của dateRange.
- Không đổi bất kỳ behavior nào khác của calendar (format ngày, day-off disabling, timezone, i18n).
- Admin-only (`view/adminhtml`); storefront Hyvä không dùng jQuery nên không bị ảnh hưởng.

### Out of Scope

- Không đổi logic nghiệp vụ ngày giao hàng (day off/date off được dùng ở checkout `delivery-information`) — chỉ display admin.
- Không sửa các bug khác của DateOff field array (vd value fill qua Prototype `setValue` theo name thay vì id) — ghi nhận nếu gặp, báo cáo TL riêng.
- Không update Mageplaza DeliveryTime lên version mới (changelog upstream có fix Date Off khác nhưng không phải bug này) — decision riêng của TL.

### Acceptance Criteria

- AC-001: Trên admin local + demo, trang `system_config/edit/section/mpdeliverytime`: click calendar icon ô Date Off (cả row có sẵn lẫn row vừa thêm bằng **Add**) → `#ui-datepicker-div` xuất hiện ngay cạnh ô input (dưới hoặc trên, không cách quá 1 viewport, không trôi về đầu trang).
- AC-002: Scroll trang đến giữa/đáy rồi click → datepicker vẫn neo đúng input (tọa độ `top/left` của `#ui-datepicker-div` ≈ `input.offset()` ±`input.offsetHeight`, chênh lệch kiểm chứng bằng DevTools/Playwright < 5px sau khi tính clamp viewport).
- AC-003: Regression — datepicker admin khác vẫn hiển thị đúng: date-range filter của order grid (widget `mage.dateRange`) và 1 field date chuẩn khác trong admin.
- AC-004: Save config Date Off → giá trị serialized lưu không đổi so với trước fix (không đụng data).
- AC-005: Không có JS error mới trong console admin khi mở trang config và thao tác calendar.

## Root Cause

`lib/web/mage/calendar.js` (Magento 2.4.8-p1+, dòng 105-112) ghi đè `_findPos` của jQuery UI:

```js
_overwriteFindPos: function () {
    $.datepicker.constructor.prototype._findPos = function (obj) {
        let domPosition = obj.getBoundingClientRect();

        return [domPosition.left, domPosition.top];
    };
},
```

`getBoundingClientRect()` trả tọa độ **viewport**, nhưng `_showDatepicker` gán kết quả vào `#ui-datepicker-div` với `position: absolute` trên `body` — tức tọa độ **document**. Mọi scroll offset bị mất nên datepicker luôn bị kéo về phía đầu trang. Ngoài ra `_checkOffset` clamp theo viewport cũng lệch theo. Upstream: [magento/magento2#40083](https://github.com/magento/magento2/issues/40083) (2.4.8-p1, closed "needs update", chưa có PR fix).

Trigger cụ thể của SLP-147: trường Date Off nằm cuối section cấu hình dài (page scroll lớn) — `getBoundingClientRect().top` nhỏ trong khi input ở offset document lớn.

## Approach

1. ✅ Trace code: `DateOff.php` (AbstractFieldArray + element `date`) → `data-mage-init calendar` → `mage/calendar.js` → `_overwriteFindPos` (root cause, đối chiếu jQuery UI `_showDatepicker`/`_checkOffset`/`_findPos` gốc trong `jquery-ui.js`).
2. Module mới `Secomm_AdminCalendarFix`: `view/adminhtml/requirejs-config.js` khai báo mixin `mage/calendar` + `view/adminhtml/web/js/calendar-position-mixin.js` re-register `mage.calendar`/`mage.dateRange` với `_overwriteFindPos` tính tọa độ document. (Pattern mixin theo `Secomm_AddressDropdown`.)
3. `setup:upgrade` + flush cache (CLI as `www-data` — LL-0003).
4. Verify bằng Playwright headless trên local `slaunchpad.localhost/admin`: đo vị trí `#ui-datepicker-div` trước fix (phải repro = lệch) và sau fix (≈ offset input). Regression AC-003/004/005.
5. Pre-review → TL review → QC demo env → estimation log.

## Test Plan & Evidence

- Repro trước fix + verify sau fix bằng script Playwright (`/tmp`, ngoài repo): đo `offset()` của input Date Off so với `#ui-datepicker-div`, có scroll trang. Evidence lưu `.ai/evidence/BUG-SRF024/`.
- Regression: order grid date-range filter; console sạch; save config giữ nguyên value.
- Manual QC trên demo env (TL/QC): đúng case screenshot SLP-147.

## Implementation (done 2026-09-04)

- `app/code/Secomm/AdminCalendarFix` — registration.php, etc/module.xml, `view/adminhtml/requirejs-config.js` (mixin `mage/calendar`), `view/adminhtml/web/js/calendar-position-mixin.js`.
- Mixin re-register **cả `mage.calendar` lẫn `mage.dateRange`** với `_overwriteFindPos` trả document coords. **Gotcha đã sửa**: mixin KHÔNG được khai báo `mage/calendar` làm dependency (mixins plugin inject target vào factory) — khai báo trùng tạo circular dependency, requirejs treo ngầm (không console error), toàn bộ datepicker biến mất.
- Enable qua `module:enable` (config.php +1 dòng — do team commit, AI không commit).

## Verification Evidence (2026-09-04 — chi tiết: `.ai/evidence/BUG-SRF024/RESULTS.md`)

Playwright headless Chromium, A/B trên đúng Date Off grid của ticket, local admin:

| | repro (fix OFF) | verify (fix ON) |
|---|---|---|
| scrollY | 956 | 956 |
| dpDocTop vs input bottom | 390 vs 1346 → **delta −956 (= −scrollY)** | 1346 vs 1346 → **delta 0** |
| `_findPos` | viewport coords (hỏng) | document coords (fix) |
| Kết quả | FAIL — tái hiện SLP-147 | PASS |

- `deltaTop = −scrollY` ở repro = proof trực tiếp viewport-vs-document mismatch của `_overwriteFindPos`.
- Regression `mage.dateRange` (order grid filter, scrolled): mở phía trên input đúng clamp (dp bottom = input top) → PASS.
- AC-001/002/003/005 pass tự động; AC-004 by-design (chỉ JS positioning, không đụng data) — manual QC demo env cho TL/QC.
- Console errors: 0 ở mọi run.

## Follow-ups / Cleanup

- Admin user test `qc01calendar` (local) — xóa sau khi TL/QC release.
- Pre-existing validator FAIL (ngoài scope ticket này): `BUG-EFWXPA` thiếu Out of Scope; 3 spec naming mismatch; 2 plan thiếu Specification reference; `FEAT-ZLP1PF` + `PLAN-TASKN35E28` frontmatter — flag TL xử lý riêng.
- Mystery: `config.php` đã được 1 process ngoài AI thêm dòng `Secomm_AdminCalendarFix` lúc 11:26 (trước khi AI chạy module:enable) — khả năng watcher setup:upgrade; team nên xác nhận không có watcher lạ chạy tự động.

