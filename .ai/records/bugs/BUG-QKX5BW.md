---
id: BUG-QKX5BW
type: bug
title: "[Social login][Mobile][Admin Dashboard] Vỡ UI button Google connect to login"
project_code: SLP
parent:
external_refs:
  ticket: SLP-185
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: low
status: in_progress
created: 2026-09-09
updated: 2026-09-09
ticket_ref:
affects_version: Magento 2.4.8-p5 + Hyva 3.x + Mageplaza_SocialLoginPro
decisions: []
decision_assessment: pending-tl
components:
  - app/design/frontend/Secomm/launchpad/Mageplaza_SocialLoginPro/templates/hyva/account/dashboard
source_areas:
  - theme-templates
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-09-09
supersedes: []
---

# [SLP][BUG-QKX5BW] [Social login][Mobile][Admin Dashboard] Vỡ UI button Google connect to login

<!-- External ticket: SLP-185. Screenshot QC (slaunchpad-demo.secomm.vn, mobile): block "Kích hoạt kết nối đăng nhập mạng xã hội" trên customer dashboard — nút Google chỉ render tile logo G trắng + dải đỏ với label bị cắt thành "...", không đọc được chữ. -->

## Summary

Nút social (Google) trong block "Activate Social Login Connect" trên customer dashboard render
vỡ: chiều rộng button bị collapse — chỉ còn cột icon 32px (ảnh `g-logo.png` nền trắng) + một dải
nền đỏ `#dd4b39` với label bị cắt thành ellipsis `...`. Xảy cả mobile lẫn desktop (nổi bật trên
mobile vì card hẹp).

Template render là **child theme override**
`app/design/frontend/Secomm/launchpad/Mageplaza_SocialLoginPro/templates/hyva/account/dashboard/manager.phtml`
(copy của vendor `Mageplaza_SocialLoginPro::hyva/account/dashboard/manager.phtml`, khác đúng 1 class
`lg:w-1`→`w-full` từ SLP-11) — tức markup vendor gốc đã broken dưới theme Hyvä/Tailwind này.

### Root cause

1. **Markup Bootstrap-3 era không được style đủ trong theme Tailwind**:
   - `<a class="btn btn-block btn-social">` — class **`.btn-block` không tồn tại** trong bundle
     `web/css/styles.css` (grep = 0) → anchor không có `width:100%`, chiều rộng rơi vào
     shrink-to-fit của cha.
   - `.btn` của Hyvä (`--btn-bg` tokens, `justify-content:center; gap; border-width:2px`) xung đột
     ngữ nghĩa với `.btn` Bootstrap mà CSS social kỳ vọng.
   - Cha của button là `div.block-content` với inline `float:left` đặt **bên trong một flex
     container** (`style="display:flex;flex-wrap:wrap"` trên div `x-data`) → float bị bỏ qua, phần
     tử trở thành flex item; `.actions-toolbar.social-btn` lại set `width:90%` (media ≤768px) →
     percentage width trong shrink-to-fit → width tính ra gần như bằng nội dung tối thiểu.
   - CSS social được port trong bundle: `.btn-social` = `padding-left:44px; white-space:nowrap;
     overflow:hidden; text-overflow:ellipsis` + icon cột tuyệt đối 32px (`.btn-social>:first-child`,
     nền `g-logo.png` cho google) → khi width cha collapse: còn đúng tile icon 32px + dải đỏ với
     `...` — khớp chính xác screenshot SLP-185.
2. **FontAwesome không được load trong Hyvä** → glyph `.fa-*` trống; Google hiển thị được nhờ rule
   ảnh `.btn-google .fa-google{background:url(images/g-logo.png)}` (asset publish tại
   `pub/static/frontend/Secomm/launchpad/*/Mageplaza_SocialLogin/css/images/g-logo.png` từ module
   `Mageplaza_SocialLogin` non-Pro). Mọi network khác sẽ mất icon.
3. **Dead/duplicated markup kế thừa từ vendor**: vòng lặp `x-for` thứ hai render thêm một bộ nút
   `px-4 py-2 rounded text-white` (chữ trắng trên card trắng = nút ma vô hình nhưng vẫn click
   được), kèm 1 block "Popup Modal" + 1 block confirm không bao giờ được trigger (code chết), 2
   instance `initSocials()`.

## Mini Spec

### Goal
- Nút social connect trên customer dashboard hiển thị đúng mọi breakpoint: logo mạng + label đọc
  được ("Google" khi chưa connect / "Disconnect" khi đã connect), không cắt chữ, không tràn/không
  collapse.

### Expected Behavior
- Mỗi social channel đang enable render **đúng 1 nút** trong card "Activate Social Login Connect".
- Chưa connect: click mở popup OAuth (`window.open` với `login_url` + popup params) — hành vi giữ
  nguyên y hệt template cũ.
- Đã connect: nút hiển thị label "Disconnect"; click mở dialog confirm Hyvä modal
  ("Are you sure to disconnect from this social channel?") → Confirm → POST AJAX
  `sociallogin/manager/button` (`type=<btnKey>`) → thành công reload trang; lỗi hiển thị alert.
  Hành vi giữ nguyên y hệt template cũ.
- i18n: mọi string hiển thị qua `__()` + theme CSV (BR-001) — label "Disconnect" + ajax alert
  (trước đây hardcode) phải dịch được theo store.

### Constraints / Rules
- **Không sửa vendor** `app/code/Mageplaza/` — fix chỉ trong child theme override
  (`Secomm/launchpad/Mageplaza_SocialLoginPro/templates/hyva/account/dashboard/manager.phtml`) +
  theme CSV.
- **Không đổi logic auth/OAuth**: `login_url`, `ajaxUrl`, popup params, body POST `type=`, flow
  disconnect giữ nguyên — chỉ rewrite markup hiển thị.
- Tailwind v4 CSS-first: chỉ dùng utility class **tĩnh** (scanner thấy được); không port thêm class
  Bootstrap vào bundle; không tạo `tailwind.config.js`.
- Logo network: tái sử dụng asset đã publish (`Mageplaza_SocialLogin::css/images/g-logo.png` qua
  `getViewFileUrl`); network không có logo → fallback badge chữ cái đầu (không phụ thuộc FontAwesome).
- `x-text` không đi qua `__()` → label "Disconnect" và alert inject server-side vào component
  (`escapeJs`), không hardcode trong JS.

### Out of Scope
- `Mageplaza_SocialLoginPro::hyva/header/modal.phtml` (popup login header — override theme tồn tại
  nhưng khác template, ticket này chỉ dashboard).
- CSS login popup của module (`style.css` Luma-oriented, `hyva/style.css` trong vendor).
- Bật thêm network / cấu hình API keys — store data Admin.
- Sửa vendor template gốc (dead markup vendor giữ nguyên — child override dọn phần của mình).

### Acceptance Criteria
- AC-001: Mobile 375px (dashboard, đã đăng nhập) — nút social hiển thị full label không cắt
  ("Google" hoặc "Disconnect"), logo hiển thị, nút nằm trọn trong card; screenshot.
- AC-002: Desktop 1280px — nút hiển thị đúng, không vỡ layout card; screenshot.
- AC-003: Trạng thái connected — label "Disconnect" + dialog confirm mở đúng; Confirm → AJAX
  disconnect thành công (row `mageplaza_social_customer` bị xóa, trang reload, nút trở về
  "Google"); Cancel không đổi gì.
- AC-004: vi/en — label "Disconnect" + message ajax dịch đúng theo store; en_US identity; CSV
  mirror cả 2 file (BR-001).
- AC-005: Console sạch (không JS error); chỉ MỘT bộ nút render (không còn nút ma từ vòng lặp chết).
- AC-006: Không thay đổi file nào ngoài scope (child theme template + 2 CSV; không đụng vendor,
  không đụng `login_url`/`ajaxUrl`/OAuth).

## Approach

1. **Repro baseline** (Playwright, local `slaunchpad.localhost`): login customer test → dashboard
   → screenshot mobile 375 broken (baseline) + desktop. Customer test + row
   `mageplaza_social_customer` (type=google) tạo bằng bootstrap script as secomm (local dev data).
2. **Rewrite `manager.phtml` (child override)**:
   - Giữ `$modal` (Hyvä Modal viewModel) + `confirmDisconnect()` fetch + `handleClick()` +
     `getPopupParams()` **nguyên vẹn về hành vi** (copy 1:1 phần JS behavior).
   - **1 component `initSocials()` duy nhất, 1 vòng lặp** — xóa vòng lặp chết, popup chết, confirm
     chết, global window function giữ nguyên pattern (modal ở x-data riêng cần gọi).
   - Markup nút: `flex flex-wrap gap-3` container; nút `inline-flex items-center gap-3 … w-full
     sm:w-auto` (static classes); icon `<img>` từ map URL build server-side
     (`getViewFileUrl('Mageplaza_SocialLogin::css/images/g-logo.png')` cho google, fallback badge
     chữ cái đầu cho key khác — không FontAwesome).
   - Label: `x-text="social.connected ? disconnectLabel : social.label"` với `disconnectLabel` +
     `ajaxError` inject qua `escapeJs(__('…'))`.
   - Escape output chuẩn Hyvä (`$escaper`); JSON socials in bằng `@noEscape` (giữ nguyên cơ chế
     vendor `SocialHelper::jsonEncode`).
3. **CSV**: thêm `"Disconnect","Ngắt kết nối"` + `"Ajax error. Please try again!","Lỗi Ajax. Vui
   lòng thử lại!"` vào cả `vi_VN.csv` + `en_US.csv` (wording VI chờ TL duyệt).
4. **Verify A/B** (Playwright): mobile 375 + desktop 1280; 2 trạng thái (chưa connect → popup mở;
   connected → dialog → Confirm → AJAX xóa row → reload); vi/en; console sạch; screenshot +
   DOM assertions → `.ai/evidence/BUG-QKX5BW/`.
5. **Pre-review + record update + working memory**; chờ TL review → QC. Không commit.

## Fix (2026-09-09)

1. **`manager.phtml` (child override) — rewrite markup, giữ nguyên JS behavior**:
   - 1 component `initSocials()` + 1 vòng lặp; xóa vòng lặp chết + "Popup Modal" chết + confirm
     chết kế thừa từ vendor (đo được `ghostCount=0` — render 0 nút, xóa thuần hygiene).
   - Nút: static Tailwind utilities `inline-flex w-full sm:w-auto items-center gap-3 …` — mobile
     full-width stacked, ≥sm auto width inline; không class Bootstrap, không class động.
   - Icon: **inline SVG theo `btnKey`** (google đa sắc; facebook trắng/tròn xanh) + fallback badge
     chữ cái đầu cho network khác. *Deviation so với Constraints ban đầu của record: không tái sử
     dụng `g-logo.png` — module không có ảnh cho facebook, FontAwesome không load trong Hyvä; SVG
     không phụ thuộc asset và không scanner-purge (class nằm trong source phtml).*
   - JS giữ 1:1 vendor (`handleClick`, `getPopupParams`, modal `hyva.modal()`,
     `confirmDisconnect` POST `type=<btnKey>` → reload / `alert(data.message)`). Duy nhất catch
     đổi hardcode VI → `__('Ajax error. Please try again!')` + label "Disconnect" inject qua
     `escapeJs` (x-text không đi qua `__()`). Escape output chuẩn `$escaper`.
2. **CSV theme +2 phrase/file** (BR-001, wording VI chờ TL): `"Disconnect","Ngắt kết nối"`,
   `"Ajax error. Please try again!","Lỗi Ajax. Vui lòng thử lại!"` — en identity.
3. **Build + cache**: `npm run build` (Tailwind v4.3.2) as secomm PASS; `.sm\:w-auto`,
   `.bg-surface`, `.text-ink` có trong `web/css/styles.css`; `cache:flush` as secomm.

## Verification (2026-09-09 — evidence `.ai/evidence/BUG-QKX5BW/RESULTS.md`)

Playwright A/B chromium headless, login customer test, **11/11 PASS**: AC-001 mobile 375
(stacked full-width w=226, không truncate, tap 46px, ghost=0) · AC-002 desktop 1280 (136/119 auto
inline) · AC-003 dialog vi → Cancel giữ connected → Confirm AJAX 200 (DB row bị xóa) → reload về
"Google" → popup OAuth mở tới accounts.google.com (lỗi `invalid_request` do credentials local
demo — store config, out of scope) · AC-004 en identity + vi dịch đúng · AC-005 console sạch ·
AC-006 git scope đúng (manager.phtml + 2 CSV + styles.css build + .ai).

## Notes

- Template hiện tại của JS behavior **không đổi**: `confirmDisconnect()` POST form-urlencoded
  `type=<btnKey>` tới `sociallogin/manager/button` (`Mageplaza\SocialLoginPro\Controller\Manager\Button`
  — xóa rows `mageplaza_social_customer` theo `customer_id` + `type`, trả JSON
  `{success, message}`) — xác nhận bằng đọc controller.
- "Cancel"/"Confirm" + câu hỏi confirm-dialog **đã có sẵn** trong 2 CSV (dòng 19/385/482) từ task
  trước — chỉ thiếu "Disconnect".
- Widget dashboard đăng ký qua `customer_account_index.xml` (block `mageplaza_social_login_manager`,
  sau `customer_account_dashboard_info`) + swap template trong `hyva_customer_account_index.xml`
  (chỉ đọc, không đổi).
