---
id: BUG-5S2Z25
type: bug
title: Forgot/reset password forms use browser-native HTML5 validation (untranslatable bubbles)
project_code: SLP
parent:
external_refs:
  tickets: SLP-115
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: low
status: in_progress
created: 2026-09-08
updated: 2026-09-08
ticket_ref:
affects_version: Magento 2.4.8-p5 + Hyvä 3.x (default theme 1.5.2) + Hyva Theme Module (hyva.formValidation)
decisions: []
decision_assessment: none-material
# Knowledge-consolidation contract (RM-07)
components:
  - app/design/frontend/Secomm/launchpad/Magento_Customer/templates/form/forgotpassword.phtml (mới)
  - app/design/frontend/Secomm/launchpad/i18n/vi_VN.csv + en_US.csv
source_areas:
  - storefront-ui
  - customer-account
  - i18n
changes_project_state: true
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-09-08
supersedes: []
---

# [SLP][BUG-5S2Z25] Forgot/reset password forms use browser-native HTML5 validation (untranslatable bubbles)

<!-- External ticket: SLP-115. Scope: presentation layer của 2 form customer password trên theme Secomm/launchpad. Không đụng controller, POST endpoint, server-side validation, captcha contract hay auth logic. -->

## Summary

Ticket SLP-115 yêu cầu (1) translate trang "Set a New Password" và (2) tắt HTML5 native validation ở form forgot password + reset password. Điều tra cho thấy 2 yêu cầu là **một root cause**: các validation message đang hiện là *browser-native bubble* (text theo locale trình duyệt — không dịch được qua CSV). Khi tắt native validation và chuyển sang `hyva.formValidation`, message render từ theme CSV (key đã có sẵn phần lớn từ các đợt translate trước) → cả hai yêu cầu được giải quyết cùng lúc. Trang forgot password hiện **không** có `novalidate` (live-verify 09-08: input `required` + `type="email"`, form không `novalidate` → bubble ON). Trang createpassword **đã** wire `hyva.formValidation` từ SLP-118 → chỉ verify.

## Mini Spec

### Goal

Cả 2 form customer password (forgot password + reset password) validate bằng `hyva.formValidation` của Hyvä — message hiển thị theo store locale (vi/en) qua theme CSV, style đồng bộ với các form Hyvä khác (register/edit/createpassword) — thay vì browser-native bubble.

### Expected Behavior

- Submit form forgot password với email rỗng → hiện message VI "Trường Email là bắt buộc." (không navigate, không bubble trình duyệt).
- Submit với email sai format → message VI "Trường Email phải chứa địa chỉ email hợp lệ (Ví dụ: johndoe@domain.com)."
- Email hợp lệ → captcha flow chạy **đúng thứ tự cũ**: `getValidationJsHtml` check `g-recaptcha-response` → token rỗng: message "ReCaptcha validation failed..." + khóa nút submit, KHÔNG POST; token có: `$form.submit()` như cũ.
- Reset password (createpassword): behavior giữ nguyên (đã `novalidate` qua `hyva.formValidation` từ trước) — message required/minlength/equalTo/password-strength render VI.
- en_US: message identity (EN), không leak VI.

### Constraints / Rules

- Không sửa `vendor/hyva-themes/*` in-place — tạo override trong child theme `Secomm/launchpad` (chỉ thị như BUG-GJT6C1).
- ~~Theme layout file cùng tên **replace** file của parent theme → file child theme phải copy đủ toàn bộ content~~ — **KHÔNG cần layout file**: handle `hyva_form_validation` đã load global qua Mageplaza compat `hyva_default.xml` (xem Approach #1 + Gotcha layout — tạo file này làm mất reCAPTCHA input khi test thực tế).
- Giữ nguyên captcha contract của `script_token_recaptcha.phtml`: emitted JS tham chiếu biến `$form` trong scope + `this` của Alpine component; giữ `errors`/`hasCaptchaToken`/`dispatchMessages`/disable-button behavior.
- Pattern Hyvä chuẩn (theo `register.phtml`/`edit.phtml`/`resetforgottenpassword.phtml`): `Object.assign(hyva.formValidation(this.$el), {...})` + `@submit="onSubmit"` với `preventDefault` trong handler.
- String mới bổ sung vào **cả** `vi_VN.csv` + `en_US.csv` (BR-001).
- Server-side: không đổi gì (endpoint `*/*/forgotpasswordpost` + captcha server check nguyên vẹn).

### Out of Scope

- Các form khác đang native-on trên cùng trang (login, newsletter, 4 form SocialLogin popup `social-form-*`) — ticket chỉ nêu 2 form password; theo dõi ticket riêng nếu QC muốn.
- Không thêm key fallback `Validation rule "%0" failed.` — không rule nào trên 2 form này rơi vào fallback (mọi built-in rule đều trả message); cân nhắc khi có form dùng custom rule không trả message.
- Không đụng 47 key day/month names đang thiếu mirror ở `en_US.csv` (thuộc BUG-GJT6C1 — flag TL của ticket đó).
- Không đổi sitekey/captcha config (local đang sitekey test `123123` — pre-existing, captcha không pass được local; QC test flow validation bằng cách tạm bỏ qua captcha case hoặc verify qua token mock).

### Acceptance Criteria

- AC-001: Trang `/customer/account/forgotpassword/` (store vi): submit email rỗng/sai format → message validation VI của `hyva.formValidation`, KHÔNG có native bubble, không navigate; form có attr `novalidate` sau khi Alpine init.
- AC-002: Captcha flow regression — captcha fail (không token): message ReCaptcha (VI sau khi thêm CSV) + nút submit bị disable, không POST; captcha pass: submit như cũ.
- AC-003: Trang `/customer/account/createPassword/` (link reset thật): form validate bằng hyva message VI (required/minlength/equalTo/password-strength) — QC verify với link reset email thật (AI không tạo token đọc dữ liệu customer).
- AC-004: Không mất thành phần trang: title "Quên mật khẩu?", block forgotPassword, social login popup, container captcha; en_US identity sạch; console không có JS error mới.

## Root Cause

Template `Hyva_default/Magento_Customer/templates/form/forgotpassword.phtml` dùng `x-data="initPasswordForm()"` + `@submit.prevent="submitForm()"` — KHÔNG qua `hyva.formValidation`, form không có `novalidate` → constraint validation native chặn submit trước khi submit event fire, bubble text do trình duyệt render (browser-locale). Layout `customer_account_forgotpassword.xml` của Hyvä cũng không include handle `hyva_form_validation` (khác `customer_account_createpassword.xml` có include). Native bubble không thể dịch qua CSV → đây là lý do phần "translate" trong ticket không thể xong chỉ bằng CSV.

## Approach

1. ~~Child theme layout + `<update handle="hyva_form_validation"/>`~~ — **BO BỎ sau khi điều tra** (xem Gotcha layout): handle `hyva_form_validation` **đã được load toàn cục** bởi Mageplaza compat (`app/code/Mageplaza/ExtraFee/view/frontend/layout/hyva_default.xml:24` + `app/code/Mageplaza/SocialLogin/view/frontend/layout/hyva_default.xml:25` — `<update handle="hyva_form_validation"/>` trên handle `hyva_default` áp dụng mọi trang Hyvä) → `hyva.formValidation` có sẵn rồi, chỉ cần wire ở template.
2. Child theme template `Magento_Customer/templates/form/forgotpassword.phtml` = bản Hyvä + `Object.assign(hyva.formValidation(this.$el), {errors, hasCaptchaToken, onSubmit})`; captcha check chạy trong `.then()` sau `validate()` thành công, trước `$form.submit()`; thêm `HyvaCsp::registerInlineScript()` theo pattern createpassword.
3. CSV: thêm phrase `ReCaptcha validation failed. Please refresh the page and try again.` vào cả 2 file (surfaced khi captcha fail — chưa có ở cả 2).
4. Verify: cache flush → curl vi/en → Playwright runtime check (novalidate + message VI/EN + captcha-fail path + không navigate) → QC verify AC-003 với link reset thật.

## Test Plan & Evidence

- curl vi/en + Playwright headless (pattern BUG-SRF024): assert `form.novalidate` runtime, submit rỗng/sai format → message VI/EN, không navigation; captcha-fail → flash VI + nút disable + không POST. Evidence `.ai/evidence/BUG-5S2Z25/` (script Playwright + HTML A/B).
- QC manual: AC-003 (link reset thật trên createpassword).

## Gotcha layout: theme layout file cùng tên làm mất reCAPTCHA input (chưa giải thích trọn vẹn — flag TL)

Khi triển khai theo approach ban đầu (tạo child theme `Magento_Customer/layout/customer_account_forgotpassword.xml` = bản Hyvä + `<update handle="hyva_form_validation"/>`), **container captcha `grecaptcha-container-Customerforgotpassword` biến mất khỏi trang** (deterministic, tái lập sau `cache:flush` toàn bộ, mode=default):

| Biến thể file theme layout | captcha container |
|---|---|
| Không có file (baseline) | 1 ✓ |
| Bản copy y hệt parent (không thêm node) | 1 ✓ |
| Copy + `<update handle="..."/>` (bất kỳ handle nào) | 0 ✗ |
| Copy + block `advanced-form-validation` inline | 0 ✗ |
| Copy + 1 node rỗng `<referenceContainer name="content"/>` | 0 ✗ |

- Block/script `advanced-form-validation` **không phải nguyên nhân** (nó đã render sẵn trên mọi trang từ handle global — xem Approach #1); captcha vẫn mất kể cả khi node thêm vào là no-op thuần.
- Cả `getInputHtml` lẫn `getValidationJsHtml` của `Hyva\Theme\ViewModel\ReCaptcha` đều trả rỗng khi mất (viewmodel arg `viewModelRecaptcha` hoặc `layout->hasElement()` lệch — chưa bám được cơ chế chính xác trong thời gian thực thi).
- **Kết luận thực thi**: bỏ file layout, giữ change ở template-only (đã đủ vì handle global). Nếu cần chỉnh layout page này tương lai → **cảnh báo TL**, điều tra riêng trước khi touch.

## Implementation (done 2026-09-08)

- `app/design/frontend/Secomm/launchpad/Magento_Customer/templates/form/forgotpassword.phtml` (mới): `x-data="initPasswordForm"` + `@submit="onSubmit"`; `initPasswordForm` = `Object.assign(hyva.formValidation(this.$el), {errors: 0, hasCaptchaToken: 0, onSubmit})`; captcha emit (`getValidationJsHtml`) chạy trong `.then()` sau validate thành công; fail → không submit + `isSubmitting=false`; pass → `$form.submit()`. Giữ nguyên viewmodel injection, `ForgotPasswordButton`, formkey, captcha input/legal notice. Header docblock ghi ticket ref.
- CSV `vi_VN.csv` + `en_US.csv`: +1 dòng `"ReCaptcha validation failed. Please refresh the page and try again.","Xác thực ReCaptcha thất bại. Vui lòng tải lại trang và thử lại."` (en identity).
- **Không có thay đổi layout** — thư mục `Magento_Customer/layout/` child theme KHÔNG được tạo (gotcha ở trên).

## Verification Evidence (2026-09-08 — `.ai/evidence/BUG-5S2Z25/`)

curl vi/en sau `cache:flush` (template-only state):

| Marker | vi | en |
|---|---|---|
| `x-data="initPasswordForm"` + `@submit="onSubmit"` | ✓ | ✓ |
| captcha container `grecaptcha-container-Customerforgotpassword` | 1 | 1 |
| script `hyva-advanced-form-validation` (global, từ Mageplaza compat) | 1 | 1 |
| captcha JS emit trong `onSubmit` (`g-recaptcha-response`) | ✓ | ✓ |
| message required VI/EN trong emitted script (`\u`-encoded) | "Trường %1 là bắt buộc." | (EN identity) |
| message captcha VI trong emitted script | "Xác thực ReCaptcha thất bại. Vui lòng tải lại trang và thử lại." | — |

Playwright headless Chromium (2 script trong evidence dir):

| Case | Kết quả |
|---|---|
| AC-001 vi: form `novalidate` sau Alpine init | **true** ✓ |
| AC-001 vi: submit rỗng → không navigate + message | **"Trường Email là bắt buộc."** ✓ |
| AC-001 vi: submit `khong-hop-le` → message | **"Trường Email phải chứa địa chỉ email hợp lệ (Ví dụ: johndoe@domain.com)."** ✓ + `.field-error` áp |
| AC-004 en: submit rỗng → identity EN | **"Email field is required."** ✓, không navigate |
| AC-002 vi: captcha fail (sitekey test `123123` → token rỗng) | **không POST** + flash **"Xác thực ReCaptcha thất bại. Vui lòng tải lại trang và thử lại."** (phrase mới — live) + nút submit disable đúng contract `script_token_recaptcha.phtml` ✓ |
| Console/pageerror | **0** mọi run ✓ |

Quan sát phụ: flash captcha hiển thị 2 element giống hệt nhau (nghi pre-existing mage-messages double-render qua `dispatchMessages` — flow gốc cũng gọi y vậy; không phải regression của ticket này — QC để ý khi test).

Còn lại cho QC/TL: **AC-003** — createpassword (`/customer/account/createPassword/` với link reset thật): form đã wire `hyva.formValidation` từ SLP-118 + handle global → kỳ vọng message VI; chưa verify live vì không tạo token (tránh đụng customer data — bị chặn theo policy PII, đúng).

## Update 2026-09-08 (2) — Translate email reset password (mở rộng scope cùng ticket)

Yêu cầu tiếp theo của user trong scope SLP-115: dịch 2 transactional email của flow reset password. Áp dụng cơ chế đã chốt (BUG-Y2PQ6W / memory `magento-email-translation-mechanism`): vendor templates đã bọc sẵn `{{trans}}` → **chỉ cần thêm phrase vào theme CSV, KHÔNG override template** (email locale files không resolve trên 2.4.8).

### Phrase inventory (map config → file vendor — tên ngược đời):

| Email (config id) | File vendor | Trạng thái phrase |
|---|---|---|
| "Forgot Password" `customer_password_forgot_email_template` (email chứa link reset) | `password_reset_confirmation.html` | +4 mới |
| "Reset Password" `customer_password_reset_password_template` (xác nhận đã đổi) | `password_reset.html` | +4 mới |
| Greeting `%name,` / footer `Thank you, %store_name` / button `Set a New Password` | — | đã có từ BUG-Y2PQ6W + SLP-128 |

### CSV +8 phrase/file (vi draft — wording chờ TL duyệt như các đợt trước):

- `Reset your %store_name password` → `Đặt lại mật khẩu %store_name của bạn` (subject)
- `There was recently a request to change the password for your account.` → `Gần đây có yêu cầu đổi mật khẩu cho tài khoản của bạn.`
- `If you requested this change, set a new password here:` → `Nếu bạn là người yêu cầu, hãy đặt mật khẩu mới tại đây:`
- `If you did not make this request, you can ignore this email and your password will remain the same.` → `Nếu bạn không thực hiện yêu cầu này, bạn có thể bỏ qua email này và mật khẩu của bạn sẽ giữ nguyên.`
- `Your %store_name password has been changed` → `Mật khẩu %store_name của bạn đã được thay đổi` (subject)
- `We have received a request to change the following information associated with your account at %store_name: password.` → `Chúng tôi đã nhận được yêu cầu thay đổi thông tin sau đây liên quan đến tài khoản của bạn tại %store_name: mật khẩu.`
- `If you have not authorized this action, please contact us immediately at <a href="mailto:%store_email">%store_email</a>` → `Nếu bạn không thực hiện hành động này, vui lòng liên hệ ngay với chúng tôi qua <a href="mailto:...">...</a>` (CSV escape `""`)
- `or call us at <a href="tel:%store_phone">%store_phone</a>` → `hoặc gọi cho chúng tôi qua <a href="tel:...">...</a>` (CSV escape `""`)

### Verification (`.ai/evidence/BUG-5S2Z25/email-render-verify-2026-09-08.txt` + script trong cùng dir)

Render framework-level `Template::processTemplate()` với `setDesignConfig` store 1 (vi_VN) / store 2 (en_US), fake customer data, sau `cache:flush`:

- **Store 1 (vi)**: 2 email — subject VI ("Đặt lại mật khẩu … của bạn" / "Mật khẩu Main Website Store của bạn đã được thay đổi"); **tất cả body phrase VI PASS**, EN-leak CLEAN, reset link render đúng `customer/account/createPassword/?...token=…`.
- **Store 2 (en)**: identity EN giữ nguyên theo từng template (không leak VI).
- **Harness artifact (như Y2PQ6W)**: subject email Forgot render thiếu store_name trong CLI harness (`Đặt lại mật khẩu  của bạn`) — flow send thật truyền vars qua SenderBuilder đầy đủ; QC xác nhận khi gửi email thật.
- DB check: `email_template` 0 row custom, không config override → vendor template là template live; Mageplaza SMTP không đụng (chỉ transport).
- Out of scope: email "Remind Password" từ admin (`password_new.html`) + các email customer/order còn lại — follow-up danh sách đã có trong BUG-Y2PQ6W §Notes.
