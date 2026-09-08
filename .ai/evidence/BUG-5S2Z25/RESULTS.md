# BUG-5S2Z25 (SLP-115) — Verification Results — 2026-09-08

## Scope đã sửa (final)

**Template-only** — KHÔNG có thay đổi layout (lý do: xem record §Gotcha layout):

1. `app/design/frontend/Secomm/launchpad/Magento_Customer/templates/form/forgotpassword.phtml` (mới — child theme):
   - `x-data="initPasswordForm"` + `@submit="onSubmit"`
   - `Object.assign(hyva.formValidation(this.$el), {errors, hasCaptchaToken, onSubmit})` → tự set `novalidate` (tắt HTML5 native validation)
   - Captcha emit (`getValidationJsHtml`) chạy trong `.then()` sau validate thành công, giữ contract `script_token_recaptcha.phtml`
2. `i18n/vi_VN.csv` + `en_US.csv`: +1 phrase `ReCaptcha validation failed. Please refresh the page and try again.`

## Điều tra quan trọng — layout file là THỪA

- Handle `hyva_form_validation` **đã load toàn cục** trên mọi trang Hyvä của site này qua:
  - `app/code/Mageplaza/ExtraFee/view/frontend/layout/hyva_default.xml:24`
  - `app/code/Mageplaza/SocialLogin/view/frontend/layout/hyva_default.xml:25`
- → `hyva.formValidation` + toàn bộ validation rules/messages có sẵn từ trước; trang forgotpassword GỐC (BEFORE) đã render script này (`sourceURL=hyva-advanced-form-validation` count = 1).

## Gotcha: theme layout file cùng tên → mất captcha (deterministic)

A/B sau `cache:flush` toàn bộ (mode=default):

| Biến thể `Magento_Customer/layout/customer_account_forgotpassword.xml` | grecaptcha-container |
|---|---|
| Không có file | 1 |
| Copy y hệt parent | 1 |
| Copy + `<update handle>` (bất kỳ handle) | 0 |
| Copy + block inline | 0 |
| Copy + node rỗng | 0 |

→ File layout đã bị XÓA; mechanism để dành cho điều tra riêng (flag TL). Nếu recreate, nhớ A/B captcha ngay.

## Runtime verify — Playwright headless Chromium

`pw_forgot_test.js` (vi store):

| Check | Kết quả |
|---|---|
| `form#user_forgotpassword` có `novalidate` sau Alpine init | `true` |
| Captcha container trong DOM | 1 |
| Submit rỗng → không navigate, message | `Trường Email là bắt buộc.` |
| Submit `khong-hop-le` → message | `Trường Email phải chứa địa chỉ email hợp lệ (Ví dụ: johndoe@domain.com).` |
| `.field-error` state class | áp dụng |
| Console errors / pageerrors | 0 |

`pw_forgot_test2.js`:

| Check | Kết quả |
|---|---|
| EN store: submit rỗng | `Email field is required.` (identity), không navigate |
| vi: captcha-fail path (sitekey test `123123`, token rỗng) | không POST; flash `Xác thực ReCaptcha thất bại. Vui lòng tải lại trang và thử lại.` (phrase CSV mới — live); nút submit `disabled=true` đúng contract |
| pageerrors | 0 |

## Server-render verify (curl, 2 store)

- `x-data="initPasswordForm"` + `@submit="onSubmit"`: cả vi + en
- captcha emit `this.hasCaptchaToken = $form['g-recaptcha-response']…` nằm trong `.then()` của `onSubmit`: ✓ (captcha flow giữ nguyên)
- Message VI emit `\u`-encoded từ CSV: `Trường %1 là bắt buộc.` + `Trường Email phải chứa địa chỉ email hợp lệ…`: ✓

## Còn lại (QC/TL)

- **AC-003**: `/customer/account/createPassword/` với link reset thật — form đã wire `hyva.formValidation` từ SLP-118; không verify live được vì không tạo reset token (PII policy). QC click link từ email reset thật.
- Flash captcha render 2 element giống nhau — nghi pre-existing double-render của mage-messages + `dispatchMessages` (flow gốc gọi y vậy); QC để ý, xử lý riêng nếu cần.
- 3 dup key CSV pre-existing (`Email`, `Comments`, `Enter your comment here`) — không phải của ticket này.
- 47 key day/month (BUG-GJT6C1) thiếu mirror en_US — flag TL của ticket đó.
