# TASK-NJSGKM (SLP-324) — Verify Results

Ngày verify: 2026-10-06 · Env: local (slaunchpad.localhost, MAGE_MODE=developer, HEAD `0b91d801`) · Tool: Playwright (`/tmp/pw-cal`, host-resolver-rules MAP 127.0.0.1)

## Kết quả AC

| AC | Kết quả | Bằng chứng |
|----|---------|-----------|
| AC-1 — toàn bộ text checkout = Inter | **PASS** | Census toàn DOM sau fix: **0 element non-Inter trong body** (`after-census-body.log`); 11 element đại diện (body, page-title, step-title, summary-title, label, input, select, button, price, link) computed `font-family: "Inter", ui-sans-serif, system-ui, sans-serif` (`baseline-fonts.json` — file này là run SAU fix; run before-fix không thực hiện được do env 500, gap before được chứng minh bằng code analysis: luma scope LL-0011 + audit CSS trong record) |
| AC-2 — Inter self-hosted, zero Google Fonts | **PASS (1 residual vendor)** | `Inter` loaded ✓; woff2 serve từ `Launchpad_Osc::fonts/` (×5 file requested, HTTP 200); request Google còn lại **2 × `fonts.googleapis.com/css?family=Roboto` = `@import` tĩnh trong vendor `Mageplaza_SocialLogin/css/style.css:20`** (initiator xác thực qua CDP `probe-roboto-initiator.js`) — KHÔNG thể khử ở CSS layer; **gstatic woff2 đã biến mất** (Roboto hết được dùng → browser không download font bytes) |
| AC-3 — icon fonts intact | **PASS** | `FontAwesome` ×7 element giữ nguyên; `Luma-Icons.woff2` vẫn được request (pseudo-element select arrow); 0 universal selector trong CSS mới |
| AC-4 — không đụng trang khác | **PASS** | CSS chỉ include qua handle `onestepcheckout_index_index` (sheet list trên page xác nhận; body class page = `checkout-index-index onestepcheckout-index-index page-layout-1column`) |
| AC-5 — regression flow | **PASS 0 regression** | Xem bảng dưới |

## Regression

| Suite | Kết quả | Ghi chú |
|-------|---------|---------|
| TASK-8TXS2P verify.js (vi, 1280+375, 30 checks) | **23/30 — GIỐNG HỆT baseline A/B** | **A/B test**: disable `osc-fonts.css` → rerun → **cùng 23/30, cùng 7 FAIL cùng giá trị** (delta −284.5/−2901, oklch S6) ⇒ 0 regression từ font change. 7 FAIL = pre-existing: 4 × S6 assertion stale vs restyle TASK-NWV2MQ trong tree (bg oklch SLP-160 mới; primary vẫn #3399cc đúng config); P1/P5 = payment radio không render đúng chỗ trong session này (env) |
| BUG-ER121M probe.js (discount alignment) | **PASS — dTop 0, dBottom 0** | `SUFFIX=slp324-after`; khớp after-state 0/0 đã ghi của BUG-ER121M |
| Console | sạch | 1 × "Error fetching data… JSON" = ambient pre-existing (đã chứng minh TASK-WXBQYZ) |

## Phát hiện thêm (flag TL)

1. **Residual `@import` Roboto (vendor)**: `app/code/Mageplaza/SocialLogin/view/frontend/web/css/style.css:20` — sheet này được load trên trang checkout (OSC social login integration). Style guide đạt 100% (không element nào render Roboto), nhưng 2 request css tới Google vẫn phát sinh. Đề xuất ticket riêng: strip import bằng override sheet tại `Launchpad_MageplazaSocialLogin` (hoặc remove + include bản sạch ở checkout) — KHÔNG sửa vendor in-place.
2. **Inter weight 300**: `.page-title` compute `fw=300` nhưng Inter self-host chỉ có 400–700 (theme cũng vậy) → browser khớp về 400. Parity với theme pages, không phải gap của task; nếu design muốn Light 300 thật → mở rộng font subset (ticket riêng, quyết định design).
3. **`.btn-social` Roboto override**: vendor rule `.social-btn .btn-social` (0,2,0) set Roboto; osc-fonts.css thêm rule (0,3,1) — 2 element này là 2 non-Inter cuối cùng, giờ Inter.

## Env notes (task chạy hôm nay)

- Toàn site local 500 lúc bắt đầu: `Secomm_CurrencyPrecision` chưa đăng ký + `setup:upgrade` fail tại `Secomm_Ahamove` (chi tiết `ENV-BLOCKER.md`). User đã tự chạy repair: CurrencyPrecision đăng ký xong (site hết 500) — **residual: Ahamove chưa xong** (index `AHAMOVE_CITY_CITY_ID` vẫn plain `Non_unique=1`, chưa có row `setup_module`; bước `DROP INDEX` chưa có tác dụng) — pre-existing, ngoài scope, cần chạy lại đúng 2 lệnh trong ENV-BLOCKER.md.
- A/B test dùng sed toggle include + `cache:flush` as secomm; đã restore nguyên trạng thái (grep `osc-fonts.css` = 1 dòng active).
