# TASK-37PS41 — Verify: provider-mixin define/factory misalignment

## Root cause (console-confirmed)

User console (2026-10-02, customer address modal edit):
`Uncaught TypeError: schemaCascade.createCascade is not a function — _setupCascade
provider-mixin.js:137`. File header: `define` 4 deps (`jquery`, `mage/utils/wrapper`,
`mage/validation`, `schema-cascade`) vs factory 3 params (`$, wrapper, schemaCascade`) →
param `schemaCascade` = `mage/validation`; `schema-cascade` thật không được gán. Throw SAU
khi `secommCascadeBound` flag set → mọi provider khác cùng trang cũng bị chặn → 2 field City
(song song) ở trạng thái server-rendered (caption "Please select the city" = server-side
`Selector\City.php:37`, xác nhận cascade chưa từng chạy).

## Đã verify

| Check | Kết quả |
|---|---|
| Fix | xoá 2 dead deps (`mage/utils/wrapper`, `mage/validation`) — define 2 deps / 2 params |
| `node --check` | pass |
| Static được serve (unversioned URL) | trả đúng define mới |
| Scan deps/params toàn bộ `web/js` AddressDropdown + VietNamAddress + Launchpad_Osc | chỉ provider-mixin.js dính; đã hết |
| Address data "Phuoc Long" (entity 1) | region_id 1205 hợp lệ (scheme 2025) — prefill sẽ match khi cascade sống |
| Validator before/after | 56 FAIL → 56 FAIL (baseline) |

## Follow-up 2026-10-02 16:24 — re-test vẫn thấy error cũ = browser cache, không phải code

User paste lại CÙNG TypeError nhưng line `137`/`60` — khớp file TRƯỚC fix (file mới `135`/`58`
vì define bớt 2 dòng). Server-side kiểm chứng: curl **đúng URL versioned browser dùng**
(`version1790930849`) trả nội dung FIX; header `Cache-Control: public` không max-age →
browser heuristic-cache bản cũ, không revalidate. → không có bug code mới.

Remedy: `bin/magento setup:static-content:deploy -f --area adminhtml --language en_US`
(5067 files, 100%) → `pub/static/deployed_version.txt` = **1790933087** — URL versioned đổi,
browser fetch lại toàn bộ JS admin từ đầu, không cần hard-reload. Verify sau deploy: cả
version cũ lẫn mới serve define mới; template `source-city.html` 200 trên version mới.
(Lưu ý khi curl test: URL versioned phải có chữ `version` — `/static/version1790933087/...`,
viết `/static/1790933087/...` sẽ rơi vào static.php fallback lỗi "Requested path is wrong".)

## Browser (user re-test, reload thường sau version bump)

AC-1 console sạch; AC-2 1 field City (select) ward vi + "Phuoc Long" preselect; AC-3 đổi
region reload cascade / save-reload giữ / unmapped country fallback native input.
Observation out-of-scope ghi trong record: global poll từ mọi provider instance + root flag
không clear trong destroy() — TL quyết sau.
