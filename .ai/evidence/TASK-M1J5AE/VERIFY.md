# TASK-M1J5AE — Verify: source city cascade không ghi value

## Phương pháp

Headless Playwright (chromium, `--host-resolver-rules=MAP fashion-launchpad.localhost
127.0.0.1`) điều khiển admin THẬT: temp admin user `claude_debug` (đÃ DISABLE sau probe —
không có CLI delete trong 2.4.8) → Sources grid → Edit Default Source → đọc state component
qua `uiRegistry` (`inventory_source_form.inventory_source_form.address.city`) + capture POST
save. Script: `probe-source-city.js`; raw states: `probe-result.json`.

## Kết quả BEFORE fix

- Console: `ReferenceError: $component is not defined` trong
  `event: {change: $component.onLevelChange…}` (2 lần render) → `onLevelChange` không bao giờ fire.
- Pick ward: `selected="An Dong"` nhưng `value=""` → POST save payload **KHÔNG có key `city`**
  (`region_id`, `country_id` có mặt) → DB `city=NULL`.
- Empty-city save KHÔNG bị client validation chặn (POST vẫn fire) — giải thích save 16:31.
- 4 console errors (2 foreach error + 2 "Failed to load template").

## Kết quả AFTER fix ($component → $parent)

| Check | Kết quả |
|---|---|
| Pick ward → `value()` | `"An Dong"` ngay lập tức |
| Save → DB | `inventory_source.city = 'An Dong'` |
| Reload → prefill | `value="An Dong"`, `selected="An Dong"` (168 options) |
| Console sau fix | **0 errors** (cả noise "Failed to load template" biến mất) |

## Ghi chú kỹ thuật

- `$component` chỉ được inject bởi `scope` binding — element template của UI engine KHÔNG
  có; `$parent` trong context `foreach` = chính component.
- "Failed to load the template" = first-render race của KO engine (template request MUỘN —
  đợi GraphQL schema); hết xuất hiện khi foreach error không còn phá render cycle.
- Admin route cần secret key: probe lấy keyed URL từ menu/grid DOM; fieldset Address render
  LAZY — phải expand trước khi select tồn tại.
- Server-side đã loại trừ trước đó: repro `SourceRepository` (adminhtml area) lưu city OK.
- Cleanup: `admin_user.claude_debug` is_active=0; temp files /tmp đã xoá.
