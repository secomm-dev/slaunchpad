# TASK-WXBQYZ (SLP-224) — Evidence: empty coupon submit → warning dưới button Áp dụng

Ngày verify: 2026-09-15 · Env: local `slaunchpad.localhost` (dev mode), Playwright headless (module `/tmp/pw-cal/node_modules`, `--host-resolver-rules=MAP slaunchpad.localhost 127.0.0.1`)

## Change set

| File | Diff |
|---|---|
| `app/design/frontend/Secomm/launchpad/Magento_Theme/templates/html/cart/coupon-form.phtml` | guard `post()`: `!remove && !code.trim()` → warning inline, return trước khi fetch; state `warning`; `@input` clear; `<p>` binding `:class="warning ? 'warning' : 'error'"` |
| `app/design/frontend/Secomm/launchpad/i18n/vi_VN.csv` | +1 key `Please enter a coupon code` → `Vui lòng nhập mã giảm giá` |
| `app/design/frontend/Secomm/launchpad/i18n/en_US.csv` | +1 key identity |

Không đổi `Launchpad_QuickCart` (server-side revalidate giữ nguyên — guard chỉ là UX, không phải security boundary).

## Kết quả e2e — 20/20 PASS (2 lần chạy liên tiếp, `e2e-output.txt` = lần 2)

- vi/T1 submit rỗng → warning `message warning` vàng, text đúng "Vui lòng nhập mã giảm giá", **0 POST** (không fetch `quickcart/coupon/post`)
- vi/position message render ngay dưới button Áp dụng (gap 8px, `m.top >= b.bottom`)
- vi/T2 whitespace-only `"   "` → warning giữ nguyên, 0 POST
- vi/T3 gõ chữ → message ẩn (clear on input)
- vi/T4 mã sai `INVALIDSLP224` → đúng 1 POST → inline `message error` đỏ, text native "Mã giảm giá không hợp lệ. Vui lòng kiểm tra lại mã và thử lại." (path server error không regressed)
- vi/console: 0 error mới (filter baseline — xem lưu ý dưới)
- en (`?___store=launchpad_en`, single navigation): `lang="en"`, string bake trong source dạng `Please\\u0020enter...` (escapeJs encode space — grep text thuần sẽ miss), ATC fetch 200 mang `___store`, runtime click rỗng → warning EN "Please enter a coupon code", 0 POST
- mobile 375px: warning visible, dưới button, không tràn viewport, 0 POST

Static: `php -l` PASS; CSV parse OK 2 locale (str_getcsv, key mới khớp); `.message.warning` có sẵn trong built `styles.css` (không cần build Tailwind); cache đã `cache:clean translate block_html full_page layout config` as secomm trước verify.

## Lưu ý verify (không thuộc change set)

1. **Console "Error fetching data: SyntaxError … not valid JSON" ×2** — baseline script (`slp224-baseline.js`) chứng minh xảy ra khi KHÔNG đụng coupon form (noise pre-existing trên luồng ATC-redirect local).
2. **Store cookie không persist trên `.localhost`** (PSL — nhất quán LL-0026): luồng e2e EN multi-navigation (ATC submit → redirect) mất store → drawer render vi dù URL đầu có `?___store`. Lần đầu chạy FAIL "en trả text vi" do đây (không phải FPC — probe xác nhận FPC key CÓ phân biệt query: EN cached, curl VI URL vẫn trả `vi`). Fix probe: EN suite single navigation + ATC fetch mang `___store`. Sản phụ: quy ước verify EN trên local cho flow multi-navigation phải giữ param trên MỌI navigation (hoặc fetch-ATC).
3. Lần chạy 1 có 3 FAIL đều là artifact probe (đo position lúc message `display:none` → rect 0; 2 mục trên) — đã sửa probe, không đụng code change set. Script lần 1 giữ tại `slp224-e2e.js` (để vết), script chốt `slp224-e2e-v2.js`.

## Chờ TL review

Diff 3 file chưa commit: `coupon-form.phtml` = chỉ diff của task này (file đã commit tại `c11252c5` SLP-158); 2 CSV **vốn modified trước session** (SLP-160/SLP-225 chưa commit) → tách hunk khi commit. Sau commit demo/staging: `cache:flush` (translate/block_html/full_page) — không cần SCD (không thêm asset mới; string server-baked trong phtml).
