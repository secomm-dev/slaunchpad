# SLP-225 — [UI][Forgot password] translate — Diff Summary + Pre-review

Date: 2026-09-15 · Mode C · Branch: `dev/development/anhchong`

## Files changed (bởi task này)

| File | Change | Lý do |
|---|---|---|
| `app/design/frontend/Secomm/launchpad/i18n/vi_VN.csv` | +2 dòng (sau dòng 340) | Thêm bản dịch cụm rate-limit (`%1` giữ nguyên placeholder) + cụm `Please correct the email address.` |
| `app/design/frontend/Secomm/launchpad/i18n/en_US.csv` | +2 dòng (cùng vị trí) | Identity entry theo BR-001 (strings phải có ở cả 2 CSV) |

> Lưu ý: 2 file này vốn đã modified từ trước (SLP-160 / quickview — +5 dòng khác). Diff của task này chỉ là 2 dòng mỗi file ở trên. `pub/static/` là generated (SCD), không review.

## Root cause (2 cụm, 2 nguyên nhân khác nhau)

1. `Please enter a valid email address (Ex: johndoe@domain.com).` — client-side `$.mage.__()` (`lib/web/mage/validation.js:638`). **CSV đã có sẵn bản dịch** từ trước; root cause là `js-translation.json` chưa từng được deploy (thiếu static content deploy). Đã fix bằng SCD `vi_VN en_US` (local).
2. `We received too many requests for password resets. Please wait and try again later or contact %1.` — server-side, `Magento_Security` `SecurityChecker/{Frequency,Quantity}.php` (string concat 2 dòng → grep 1 dòng không thấy), throw `SecurityViolationException`, SocialLogin `Popup/Forgot.php` pass message vào JSON popup; `%1` = `trans_email/ident_support` store config. CSV thiếu → đã thêm.
3. Bonus cùng controller: `Please correct the email address.` (server-side) cũng chưa có → thêm luôn.

## Pre-review checklist (AGENTS.md §8.3)

- [x] Code matches approach đã duyệt (chỉ i18n + SCD, không đổi logic)
- [x] No changes outside scope (diff = 2 dòng/CSV; không đụng vendor/Mageplaza checkout logic)
- [x] No hardcoded values (`%1` giữ nguyên placeholder; support email lấy từ store config — không hardcode)
- [x] N/A error handling (no code change); no security issues (no code change)
- [x] BR-001 respected (cả vi_VN + en_US)
- [x] Tests: HTTP end-to-end 2 POSTs (thấy `command-output.txt`) + theme dict lookup + `js-translation.json` content check
- [x] Performance: N/A (dictionary lookup); SCD là thao tác deploy đã có sẵn trong quy trình
- [x] Regression risk: chỉ thêm dictionary entries — phrase đã có bản dịch cũ từ Mageplaza module CSV sẽ bị theme override (priority theme > module là chuẩn Magento — đã xác nhận với `Please correct the email address.`: "Vui lòng kiểm tra lại địa chỉ email." thay "Vui lòng sửa địa chỉ email." của module)

## Còn lại cho TL / QC / DevOps

- **TL**: approve diff (2 dòng CSV × 2 file) + phê duyệt handoff deploy demo
- **DevOps/demo** (CI/CD TBD — cần người thực hiện): commit CSVs → pull trên demo → `bin/magento setup:static-content:deploy -f vi_VN en_US` (production mode không cần `-f`) → `cache:flush` (translate cache) → QC
- **QC browser** (demo hoặc local): popup Quên mật khẩu trên `/onestepcheckout` store vi — submit email `test` (không hợp lệ) → message validation tiếng Việt; POST 2 lần liên tiếp cùng email → error box rate-limit tiếng Việt; store en → English; regression: popup login/create account, trang `/customer/account/forgotpassword`
