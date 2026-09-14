# TASK-7P5RJP (SLP-160 / LA-04) — Social Login: apply Hyva UI style — Verify Results

Ngày verify: 2026-09-11 (round 1–3) + 2026-09-14 (round 4–10). Môi trường: local (slaunchpad.localhost, mode default), Playwright + host-resolver-rules.

## Round 5 (2026-09-14, theo feedback QC "popup không nằm giữa + có thanh scroll") — căn giữa + bỏ scrollbar

Đo được 2 nguồn:
1. Vendor JS (theme copy `modal.phtml`, hàm `changeSocialBtnPosition`) set **inline `transform: translateY(7%) !important`** lên `.white-popup` khi `btnPosition !== ""` — config `social_btn_position` trống nhưng `getConfigGeneral()` trả `null`, và `null !== ""` là true → nhánh LUÔN chạy → card lệch xuống 7% height. Inline !important thắng mọi author CSS → **không fix được bằng CSS**, neutralize đúng 1 token trong theme copy: `setProperty("transform", "none", "important")` (kèm comment; các mode quick_login/popup_slide set transform riêng — không đụng).
2. Dialog thấp hơn viewport 32px (inset/padding ngầm của modal base) trong khi `#test` cao 100vh → tràn 32px → scrollbar. Fix: `dialog.wrap-modal-login { inset:0; margin:0; width/height 100%; padding:0; overflow: hidden auto }` + `#popup_test #test { display:flex !important; align-items/justify-content: center }` (giữ nguyên height 100vh → vùng click-outside còn nguyên) + card `transform:none !important; margin:auto` (auto margin = safe-centering khi card cao hơn viewport trên mobile).

Kết quả đo (`center-probe.cjs`): **offCenter 0 / 0 / 0.008 px; hasScroll false** ở 1280/770/375. Regression cart sau khi đụng modal.phtml (lần 2): cart item + drawer + modal-from-cart + đóng × PASS; probe "full card size" FAIL là do F1 duplicate-id (getElementById bắt clone ẩn 0×0 trên cart page) — không phải regression, visual `modal-centered-*.png` chứng minh render đủ. Evidence: `modal-centered-{1280,770,375}.png`, `center-probe.cjs`, `vcenter-probe.cjs`.

## Round 6 (2026-09-14, theo feedback QC "nút close chưa có style + đóng popup giựt")

Đo được: (1) nút × là glyph trần (legacy `.mfp-close` fs 28px/`#popup_test .mfp-close` không phù hợp card theme) — restyle icon button tròn 36px: nền surface + viền gray-200 + × 600 ink-muted, hover gray-100, `focus-visible` outline primary; (2) **animation disappear 500ms nhưng JS `hideMyDialog()` chạy sau closeTime 300ms → bị cắt ngang ở ~60% animation (giựt)** — probe `disappearAnim: 0.5s` xác nhận; fix: `dialog.wrap-modal-login[class*="-disappear"] { animation-duration: 300ms !important }` đồng bộ mọi effect với closeTime; thêm backdrop fade-out 300ms song song (Chrome 122+, fallback snap như cũ). Timeline sau fix (`close-timeline.cjs`): animation chạy trọn 0→300ms → class dọn ~301ms → dialog close ~660ms (hyva.modal hide) — card zoom-out mượt, không còn nhảy. Evidence: `modal-close-button-{styled,zoom}.png`, `close-probe.cjs` (duration 0.3s OK), `close-timeline.cjs`.

## Round 7 (2026-09-14, feedback "vẫn giật — popup tắt xuống thì giật lên lần nữa mới tắt")

Burst-capture 70ms/frame trong lúc đóng (`close-burst.cjs`) **bắt được đúng khoảnh khắc**: t=281ms animation thu nhỏ xong (opacity 0.02) → **t=351ms class animation bị remove → dialog bật lại FULL SIZE opacity 1** (rect card 241/417) → fade từ từ ~300ms → t≈660ms mới tắt hẳn. Root: sau khi class animation bị remove, base state bị legacy `dialog[open] { opacity: 1 }` ((0,1,1), load sau) đè lên utility `opacity-0` ((0,1,0)) — đúng pattern F5 — khiến dialog hiện trở lại đầy kích thước 1 giai đoạn.

Khắc phục: base opacity 0 với spec (0,2,1) — `dialog.wrap-modal-login[open] { opacity: 0 }` (model "invisible unless animating": keyframes appear/disappear opacity 0↔1 fill forwards là nguồn chân lý visibility). Burst sau fix: t=281 opacity 0.02 → t=351 class remove → **opacity 0 liền mạch, KHÔNG còn frame full-size** → t=702 hidden. Open flow kiểm tra: đang mở opacity = 1 (appear fill forwards) — `open-check.cjs`, `modal-open-after-opacity-fix.png`; căn giữa/no-scroll giữ nguyên (offCenter 0, hasScroll false).

## Round 8 (2026-09-14, feedback "card hết giật nhưng nền đen vẫn giật lên lại")

`::backdrop` base đen tĩnh rơi vào cùng bẫy round 7: sau khi class animation bị remove ở ~301ms (dialog vẫn open đến ~660ms), backdrop quay về base đen 0.5 → "flash đen" 350ms. Fix theo cùng model invisible-unless-animating, chuyển lớp dim sang **phần tử thật `#test`** (full viewport, vừa là vùng click-outside — animate được trên mọi browser, không phụ thuộc hỗ trợ `::backdrop`): base `transparent`, `slp-dim-in 300ms forwards` khi `-appear` (giữ tối suốt lúc mở), `slp-dim-out 300ms forwards` khi `-disappear`; `::backdrop` ép trong suốt vĩnh viễn. Burst đo (`dim-burst.cjs`): mở = 0.5 dark ✓; đóng: 0.33→0.13→0.04→0 đồng nhịp card (300ms) → **sau 350ms transparent ổn định, không flash**; t≈631 open=false. Lưu ý: `scale-0` đã bị remove khỏi `addDialogClass` trong modal.phtml (không phải session này — edit ngoài; rule `scale-0{scale:1}` của overlay thành no-op vô hại, giữ làm bảo hiểm). Evidence: `modal-open-dim-on-test-element.png`, `dim-burst.cjs`.

## Round 9 (2026-09-14, feedback QC "phải tuân theo config của Mageplaza Social Login" + screenshot admin config)

Xác nhận config runtime qua helper (`cfg-probe.php`): **`getSocialBtnPosition()` = `"right"`** (system value, field SLPro `social_btn_position`), **`getStyleManagement()` = `"#3399cc"`** (config màu — khớp admin screenshot "Style Management" — demo là "Default"; `custom_css` system value = template hướng dẫn, chủ yếu no-op). Sửa overlay bỏ các override MÀU đang đè config:

1. **`.social-login-title`**: trả banner màu config (bỏ `background: transparent` của round 2 — chính rule này khiến chữ trắng legacy thành invisible, phải fix ink); giữ shape (padding .5/.75rem + radius .375rem). Chữ trắng trên banner = legacy chuẩn vendor ✓ visible.
2. **Primary buttons modal**: bỏ `background-color/border/color` (trước đây ép `--color-primary`) → css.phtml inject màu config (#3399cc); giữ radius/padding/weight + hover `filter: brightness(.92)` (hợp mọi màu config).
3. **Social Button Position**: thêm rule theo class vendor JS (`social-left` → order -1; `social-top` → order -1 + full row) — admin đổi config là layout theo; Right (hiện tại) = layout mặc định ✓.
4. **Popup Effect** (Zoom): đã config-respecting từ round 6 (sync duration mọi `-disappear`, appear/keyframes vendor nguyên bản).

Probe sau fix (`config-respect-probe.cjs`): title bg `rgb(51,153,204)` = #3399cc ✓, h2 trắng trên banner ✓ visible, nút Login bg #3399cc + radius 8px ✓. Center/no-scroll giữ nguyên (offCenter 0). **Deviation có chủ đích flag TL**: `translateY(7%)` vendor (chạy cho mọi position kể cả right — do `null !== ""`) vẫn neutralize từ round 5 vì mâu thuẫn trực tiếp yêu cầu căn giữa; khôi phục = bỏ comment 1 dòng trong modal.phtml. **Khuyến nghị store config**: Admin set `style_management` = màu brand (vd #14532d-ish green) per store view để popup khớp brand thay vì #3399cc Magento default (hiện local hiển thị đúng config #3399cc). **F7 (chưa rõ nguồn)**: banner có artifact hình vuông mờ ~10% alpha đè chữ "Đ" (micro-shot 3x) — quét DOM/pseudo toàn phần không ra element; không blocker, để QC xác nhận browser thật.

## Round 10 (2026-09-14, feedback "nhận config nhưng nên làm cho nó đẹp hơn") — tìm ra nguồn ô vuông + banner đẹp

**Định danh được F7 (ô vuông đè chữ Đ)**: legacy css gắn lên chính h2 title — `.social-login-title h2 { padding-left: 40px; background: no-repeat 12px center; color: #fff }` + `.social-login-title .login-title/.create-account-title/.forgot-pass-title { background-image: url(images/*-title.png) }` → **icon PNG vendor render 12px-center đè lên chữ** khi banner màu config hiện lại (round 9). Cách phát hiện: `h2.remove()` → cả icon + chữ biến mất (visibility:hidden không ăn do `!important` force-visibility nào đó — dùng remove mới chắc). Khử: `background: none; padding: 0` cho h2 variants.

**Banner đẹp (giữ màu config #3399cc)**: `text-align: center` + `padding: .625rem 1rem` + `radius: .5rem`; h2 `font-size: 1.125rem` căn giữa. Kết quả: banner pill xanh config, chữ trắng giữa, không icon lỗi — `modal-banner-prettified-{1280,770}.png`. Probe: title/nút giữ #3399cc (config), radius 8px.

## Round 3 (2026-09-11, theo feedback QC "layout không cân đối") — form fills cột

Screenshot QC cho thấy form ~360px trong cột 430px + khoảng chết giữa 2 cột + social lệch phải. Trace ancestor chain từng tầng đo được:

| Tầng | w | padding ngang |
|---|---|---|
| `.mp-social-popup` | 430 | 10px/10px (legacy) |
| `.block-container` | 430 | 0 |
| `.block.col-mp` | 430 | **25px/25px** (`#social-login-popup .block-container .block { padding: 20px 25px }` legacy) |
| `.block-content` → form → fieldset | 360→380 | 0 |

Fix trong overlay: collapse padding ngang từng tầng (`mp-social-popup` + block-container/block/block-content + `.block.col-mp`) + form/fieldset `width:100%` → **form 430px full cột**; title thoát `width:200%` legacy (`:first-child` nâng lên 1-5-0). Kết quả: gap giữa 2 cột đều 32px (flex gap), hết vùng chết — `modal-login-balanced-1280.png`, `modal-tab-create-balanced-770.png`, `modal-tab-forgot-balanced-1280.png`.

**Gotcha cascade (F5)**: rule `padding-inline/physical 0` spec 1-4-0 KHÔNG thắng legacy `padding: 20px 25px` 1-2-0 dù match + spec cao hơn (nguyên nhân không xác định đầy đủ qua probe — nghi ứng xử shorthand-vs-longhand sau minify); xử lý: `padding-left/right: 0 !important` cho `.block.col-mp` trong scope `#social-login-popup .mp-social-popup` (display-only, không ảnh hưởng rule khác). Probe tools (`chain-probe.cjs`, `instance-probe.cjs`, `form-probe.cjs`, `balance-probe.cjs`) lưu trong evidence; lưu ý duplicate id F1 làm `querySelector('#social-login-popup …')` bắt nhầm clone trên cart page.

## Round 4 (2026-09-14, theo feedback QC "social login google/facebook vẫn vỡ, style lại text + title") — nút social + divider

Đo được: anchor button **overflow=true** (scrollWidth 197 > clientWidth 186) — wrapper `.actions-toolbar.social-btn` bị legacy co còn 188px + padding-inline 16px×2 làm chữ 14px VI tràn biên button. Fix overlay: nới wrapper full cột + `padding-inline: .75rem` + `width:100%` → **overflow=false** (anchor 234, scrollW 232). Title "Hoặc Đăng nhập bằng" restyle kiểu Hyvä: **divider** — dòng kẻ `::before/::after` 1px 2 bên, text căn giữa (`flex + gap .75rem`). Title "Đăng nhập" căn lề với fields (legacy còn `margin-left` — đổi `margin-block` → `margin: 0 0 1rem`). Mobile 375 đo lại: card 343, form/input/button 311 = khớp content (343−32 padding), không tràn (ảnh cảm giác sát mép do card chiếm gần hết viewport). Evidence: `modal-login-social-restyle-{770,1280}.png`, `modal-mobile-en-social-restyle.png`, probe `btn-probe.cjs` (overflow=false), `mobile-probe.cjs`/`btnlogin-probe.cjs` (không tràn mobile).

## Round 2 (2026-09-11, theo feedback QC/UI review) — fix các điểm vỡ còn lại

Audit toàn bộ tab của modal (login/create/forgot/email) × desktop/mobile phát hiện 4 điểm vỡ, đều fix bằng CSS overlay:

1. **Title form trắng trên nền trắng (invisible)** — `create-account-title`/`forgot-pass-title`/`email` dùng class khác `login-title`, legacy css set `color: #fff` (dành cho banner `#3399cc` của css.phtml). Fix: rule màu ink cho **mọi** `div.social-login-title h2`. Trước: `modal-tab-create-vi-after.png` (title hiện) vs vùng trắng đầu card ở screenshot round 1.
2. **Bố cục 2 cột lệch** — legacy css float `.mp-social-popup` (415px) + `#mp-popup-social-content` width 712px tràn card (bị `overflow:hidden` clip) + `padding-top: 75px` đẩy social xuống. Fix: **flex tường minh** trên `#social-login-popup` (form `1 1 0` min 280px + social `0 0 240px`, reset padding, fake-email chiếm full hàng khi hiện; stack mobile ≤640px).
3. **Checkbox newsletter vỡ 2 dòng** — legacy `#social-login-popup .social-login .fieldset .field { display: block }` (1-3-0, load sau styles.css) đè rule flex 1-2-0. Fix: override cùng chuỗi +1 class = 1-4-0 (`... .fieldset .field.choice`). Tooling note: probe CSS who-wins phải xử lý `CSSStyleRule.cssRules` (luôn truthy ở modern browsers — rule cha bị skip nếu đệ quy không check `.length`).
4. **Social column mobile sau stack** — nút full-width, không clip (`modal-tab-create-mobile-vi-after.png`, `modal-mobile-en-after.png`).

Kết quả round 2: title hiện đúng ink ở cả 3 tab; create tab desktop 2 cột cân (form + social 240px, hết vùng chết); checkbox newsletter 1 dòng cả desktop/mobile; forgot tab (email-link form) sạch; EN mobile (Sign In/Registered Customers/Or Sign In With) sạch — `modal-tab-create-vi-after.png`, `modal-tab-create-mobile-vi-after.png`, `modal-tab-forgot-vi-after.png`, `modal-mobile-en-after.png`.

## Kết quả theo AC (round 1)

### AC-001 PASS — modal hiển thị, không còn collapse 0×0
- Trước fix: dialog `open=true` nhưng `getBoundingClientRect` 0×0 (root cause: Tailwind v4 `scale-0` sinh standalone property `scale: 0`, vendor keyframes chỉ animate `transform` → dialog kẹt scale 0 — modal **chưa bao giờ hiển thị** trên theme này).
- Sau fix (`dialog.wrap-modal-login.scale-0 { scale: 1 }` trong theme overlay): modal render card 760×417 (desktop) với backdrop mờ, đo được qua `regression2.cjs`/`dbg2.cjs`.
- Đóng bằng × PASS (dùng cả trên login page lẫn cart page).
- Evidence: `modal-vi-after.png`, `modal-en-after.png`, `regression-modal-from-cart.png`.

### AC-002 PASS — social buttons trong modal + tab switching
- Nút Google/Facebook render style mới (inline SVG brand icon + Tailwind, 1 dòng, không wrap): marker `inline-flex w-full cursor-pointer` ×2.
- Class hook vendor giữ nguyên (`#mp-popup-social-content`, `.block.social-login-authentication-channel`, `.block-content`, `.actions-toolbar.social-btn`, `<key>-login`) → `showLogin/createBtn/forgotBtn` + position JS không đổi behavior.
- `onclick clickLoginSocial` + popup-window JS giữ verbatim (OAuth thật không test được local — credentials dummy, QC demo).
- Evidence: `modal-vi-after.png` (tab login), `modal-en-after.png`.

### AC-003 PASS — trang login/create/forgot đồng bộ style theme
- Template thật trên các trang này là `Mageplaza_SocialLogin::hyva/form/social.phtml` (được `hyva_default.xml:108-117` swap vào page-blocks login/create + render trong `authentication-popup` child trên mọi trang PAGE_AUTHEN) — đã override; legacy `.btn-social`/`.fa` markup = 0 trên trang (marker check sau deploy).
- Nút render style SLP-185 (border surface + SVG icon + radius-md), không còn width 215px legacy wrap.
- Evidence: `login-page-vi-after.png`, `create-page-vi-after.png`, `forgot-page-vi-after.png`, `forgot-page-en-after.png`, `login-page-mobile-vi-after.png`.

### AC-004 PASS — i18n vi/en
- vi_VN: "Đăng nhập với Facebook/Google", "Hoặc Đăng nhập bằng" (nguồn: `Mageplaza_SocialLogin/i18n/vi_VN.csv` — vendor có sẵn; theme không cần thêm key).
- en_US (`?___store=launchpad_en`): `<html lang="en">`, "Sign in with Facebook/Google", "Or Sign In With" — identity sạch.
- **Không có string mới** → BR-001 thỏa mà không đụng CSV.
- Lưu ý store code: `launchpad_en` (không phải `en` — `?___store=en` silently fail, lang vẫn `vi`).

### AC-005 PASS — regression TASK-QX93G3 (cart)
- `modal.phtml` (SLPro theme copy) **giữ nguyên byte-for-byte** (chỉ đụng CSS overlay + 2 template social buttons) → cơ chế `var popup`/replaceDomElement không thay đổi.
- Probe trên cart page: ATC → cart render item ✓, drawer mở + qty stepper + coupon form ✓ (`regression-cart-drawer.png`), modal sign-in mở từ cart page + đóng bằng × ✓.
- Suite coupon-sync đầy đủ (`TASK-QX93G3/verify-sync.js`) KHÔNG chạy được vì salesrule `QCQUICK10` đã bị Admin disable (per CURRENT_STATE cleanup note) — fixture issue, không liên quan change set.

### AC-006 PASS — dashboard connect buttons (SLP-185)
- Không đụng file nào của dashboard override (`manager.phtml` không thay đổi).

### AC-007 PASS — console
- 0 pageerror trên login/create/forgot/modal (8/8 shots "clean" trong `verify-screenshots.cjs`).
- Trên cart page có 2 lỗi console `Error fetching data: ... not valid JSON` — ambient pre-existing (pattern Mageplaza ExtraFee fetch 302→HTML, đã ghi BUG-KFJ49A/SLP-183 + BUG-NY0M3S F4), không thuộc change set (CSS + template không có fetch mới).

## Findings mới trong quá trình verify (ghi nhận, không fix trong ticket này)

- **F1 — duplicate id `#social-login-popup` (vendor pre-existing)**: trên cart/login page tồn tại 2 element trùng id — clone ẩn 0×0 trong `column.main` (từ `hyva/form/authentication-popup.phtml` + request-info flow) và instance thật trong modal dialog. `getElementById` bắt clone → mọi measure/có thể có code JS phụ thuộc id đều phải lưu ý. Không ảnh hưởng render/modal thật (visual + open/close PASS). Follow-up nếu TL/QC muốn: đổi id của request-info clone.
- **F2 — vendor CSS Hyvä orphan**: `Mageplaza_SocialLogin/view/frontend/web/css/hyva/style.css` (1563 dòng) không được load bởi layout nào; theme overlay (`theme/social-login.css`) đảm nhận vai trò styling form/button trong modal.
- **F3 — static deploy quick strategy không refresh `styles.css` ở mode default**: `setup:static-content:deploy -f` (quick) copy 4895 file/theme nhưng bỏ qua `web/css/styles.css` đã đổi (pub/static giữ bản 09:12); xử lý local: cp tay artifact build sang `pub/static/frontend/Secomm/launchpad/{vi_VN,en_US}/css/styles.css`. Production deploy (cơ chế khác) sẽ materialize đúng từ source — source `web/css/styles.css` đã đúng trong changeset.
- **F4 — store code EN**: `launchpad_en`; `?___store=en` không có hiệu lực (silent, lang vẫn `vi`) — bổ sung cho F3 BUG-NY0M3S về store-switch.

## Scripts
- `verify-screenshots.cjs` — chụp 8 surface (login/create/forgot × vi/en × desktop/mobile + modal).
- `verify-regression-cart.cjs` — probe cart page + drawer + modal từ cart + ambient error census.
