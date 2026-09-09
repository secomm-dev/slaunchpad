# BUG-QKX5BW (SLP-185) — Verification Results — 2026-09-09

## Scope verify

Toàn bộ AC-001..006 trong record (UI nút social connect trên customer dashboard).
Môi trường: local `slaunchpad.localhost`, store vi `default` (id 1) + en `launchpad_en` (id 2).

## Root-cause evidence (trước fix)

- Playwright mobile 375×812 (login `qc-social@example.com`, customer id=7, google connected):
  `.block-dashboard-social-login a.btn-social` — Facebook w=89px (`scrollWidth` 92,
  `truncated=true`), Google "Disconnect" w=97px (`scrollWidth` 100, `truncated=true`) — label
  ellipsis, khớp screenshot ticket ("..." đỏ).
- Screenshot: `baseline-mobile-375.png` — tile `g-logo.png` trắng + dải đỏ cắt chữ.
- Nguyên nhân đo được: `.btn-block` không tồn tại trong bundle (grep = 0) + `.btn` Hyvä xung đột
  + cha `float:left` trong flex container → width collapse; `.btn-social` port CSS có
  `nowrap + ellipsis + overflow:hidden` → cắt label. Chi tiết trong record §Root cause.

## Fix applied

1. `app/design/frontend/Secomm/launchpad/Mageplaza_SocialLoginPro/templates/hyva/account/dashboard/manager.phtml`
   (child override, rewrite):
   - 1 component `initSocials()` + 1 vòng lặp duy nhất; xóa vòng lặp chết, "Popup Modal" chết,
     confirm chết của vendor (render 0 nút — đo được `ghostCount=0` cả trước lẫn sau fix, xóa
     thuần hygiene).
   - Nút: `inline-flex w-full sm:w-auto items-center gap-3 rounded-md border border-gray-300
     bg-surface px-4 py-2.5 text-sm font-medium text-ink shadow-sm` — static utilities, không
     class Bootstrap.
   - Icon: inline SVG theo `btnKey` (google đa sắc, facebook trắng/tròn xanh `#1877F2`); fallback
     badge chữ cái đầu (inline style, không phụ thuộc scanner) cho network khác — thay phương án
     "tái sử dụng g-logo.png" trong record vì module không có ảnh cho facebook và FontAwesome
     không load trong Hyvä.
   - JS behavior giữ 1:1 vendor: `handleClick` (popup `window.open(login_url + '?' + ts, label,
     getPopupParams(500,420))`), modal `hyva.modal()` + `openMyDialog/hideMyDialog` global,
     `confirmDisconnect` POST form-urlencoded `type=<btnKey>` → success reload, fail
     `alert(data.message)` (giữ nguyên chuỗi server trả). Duy nhất `catch` đổi từ hardcode VI
     `'Lỗi Ajax...'` sang `__('Ajax error. Please try again!')` inject qua `escapeJs`;
     label "Disconnect" inject tương tự (x-text không đi qua `__()`).
   - Output escape chuẩn: `escapeHtml` cho title, `@noEscape` cho JSON socials/icons
     (server-generated).
2. CSV theme (+2 phrase/file, mirror BR-001 — wording VI chờ TL duyệt):
   `"Disconnect","Ngắt kết nối"` / `"Ajax error. Please try again!","Lỗi Ajax. Vui lòng thử lại!"`
   (en = identity). Lưu ý: diff CSV vs HEAD +8/file — 6 dòng đầu là thay đổi chưa commit của work
   item khác có sẵn trong working tree, KHÔNG thuộc BUG-QKX5BW.
3. `npm run build` Tailwind v4.3.2 as secomm PASS; utilities mới trong `web/css/styles.css`:
   `.sm\:w-auto`, `.bg-surface`, `.text-ink` (grep 1/1). `bin/magento cache:flush` as secomm OK.

## Playwright A/B verify (verify-check.js, chromium headless)

| AC | Kết quả | Chi tiết |
|---|---|---|
| AC-001 mobile 375 vi connected | **PASS** | buttons `["Facebook","Ngắt kết nối"]`, google w=226 (full-width, stacked), `truncated=false`, `fitsViewport=true`, tap height 46px, ghost=0 |
| AC-002 desktop 1280 vi | **PASS** | Facebook w=136 / Google w=119 (auto, inline), không truncate, không vỡ card |
| AC-003a dialog | **PASS** | "Bạn có chắc chắn muốn hủy kết nối…" hiển thị |
| AC-003b Cancel | **PASS** | giữ connected |
| AC-003c Confirm | **PASS** | AJAX 200 → row `mageplaza_social_customer` bị xóa (DB check total_rows=0) → reload → label về "Google" |
| AC-003d popup OAuth | **PASS** | `window.open` → redirect tới `accounts.google.com` (Google trả `invalid_request` vì OAuth credentials local demo — chain popup + login_url hoạt động; credentials = store config, out of scope) |
| AC-004 vi/en | **PASS** | en: title "Activate Social Login Connect" + "Disconnect" identity, không leak VI; vi: "Ngắt kết nối" |
| AC-005 console | **PASS** | console errors = none toàn bộ flow |
| AC-006 scope | **PASS** | git status: chỉ manager.phtml + 2 CSV (+2 dòng/file) + styles.css (build) + .ai record/evidence |

- Screenshots: `verify-mobile-375-vi-connected.png` (stacked full-width, icon + label đủ),
  `verify-mobile-375-vi-dialog.png` (dialog confirm trắng, Hủy/Xác nhận),
  `verify-desktop-1280-vi.png`, `verify-desktop-en-connected.png` (block en, 375 viewport),
  `baseline-mobile-375.png` (A/B).

## Gotchas (để sau này)

1. **Playwright viewport**: `browserContext.newPage({ viewport })` BỎ QUA option → page chạy ở
   viewport mặc định 1280. Verify "mobile" lần đầu thực ra là desktop (w=156 auto) — đoán sai
   hướng một lúc. Đúng cách: `browser.newContext({ viewport })`. Script debug đích danh
   `debug-width/debug-connected` (đã xóa) xác nhận mobile thật: stacked full-width w=226.
2. **hasText regex**: Playwright match raw textContent (còn `\n` indent) → `/^Google$/` = 0 match
   dù evaluate thấy; dùng `getByRole('link', { name: /^Google$/i })`.
3. Screenshot dialog cần settle ≥600ms — transition fade-in của Hyvä modal bắt giữa chừng thì
   panel trắng chưa render hết, trông như vỡ.
4. Verify ngay sau `cache:flush`: request đầu chậm (cold cache) có thể timeout 15s — dùng vòng
   retry + dump trạng thái thay vì waitFor cứng.

## Còn lại

- Chờ TL review (Mode C) → QC. Chưa commit.
- Test data local: customer `qc-social@example.com` (id 7, password trong setup script) + row
  google đã bị disconnect do flow test — tạo lại bằng
  `sudo -u secomm php setup-test-data.php create`. QC xong có thể xóa customer id 7.
- Wording VI "Ngắt kết nối" + "Lỗi Ajax. Vui lòng thử lại!" chờ TL duyệt.
- Popup OAuth tới Google trả lỗi `invalid_request` trên local — cần OAuth credentials/redirect
  URI đúng của store để hoàn thiện flow thật (store data, out of scope code).
