# TASK-52CKN3 — Evidence

Date: 2026-10-05 · Mode C · Ticket: SLP-305

## Change set

| File | Change |
|---|---|
| `app/design/frontend/Secomm/launchpad/Secomm_AddressDropdown/templates/hyva/address/edit.phtml` | NEW — verbatim copy của `Secomm_AddressDropdown::hyva/address/edit.phtml` (560 dòng) + `w-full` ×3 (country L174→175, region L205, city L248) + header comment override |
| `app/design/frontend/Secomm/launchpad/web/tailwind/components/wrapper.css` | 2 rule `body.customer-account-index main ...` → `main:has(.account-nav):not(.product-main-full-width, .page-main-full-width) ...` (giữ 24px padding-top trên `main` + `.columns`) |

Module `app/code/Secomm/AddressDropdown` **unchanged**.

## Verification transcript

1. **Mint ID**: `/tmp/project-ai-idgen TASK` (CRLF-strip copy, as secomm) → `TASK-52CKN3`.
2. **Template resolution** (CLI, theme `frontend/Secomm/launchpad` set tường minh — artifact CLI theo TASK-2BASRX):
   `resolver → /var/www/projects/slaunchpad/app/design/frontend/Secomm/launchpad/Secomm_AddressDropdown/templates/hyva/address/edit.phtml` → **THEME OVERRIDE YES**
3. **Build**: `npm run build` as secomm — pass, Done in 880ms.
4. **Compiled CSS**: `main:has(.account-nav):not(.product-main-full-width,.page-main-full-width),main:has(.account-nav):not(...) .columns{padding-top:calc(var(--spacing)*6)}` — present (grep=1).
5. **Asset serve** (curl Host-header `slaunchpad.localhost` qua `127.0.0.1:80` — HTTP; 443 không listen trong env hiện tại):
   - vi_VN: HTTP 200, rule `account-nav` count=1
   - en_US: HTTP 200
   - pub/static vi_VN `styles.css` là **symlink** → không có stale (publisher skip-existing không áp dụng).
6. **Cache**: `cache:flush` (as secomm) sau khi thêm template override.
7. `w-full` trong override: ≥3 select (grep đếm 4 — gồm 1 `w-full` có sẵn).

## Residual / cho TL

- AC-003 visual (select full width + padding trên các trang account) — chờ QC/TL browser check.
- `:has()` — Chrome 105+/Safari 15.4+/Firefox 121+; storefront target 2026, chấp nhận.
- Trang account KHÔNG có sidebar (forgot/reset, login, confirmation) không nhận padding — theo design (có spacing riêng).
- SLP-305 tổng thể (Review pagination, bare CTA, Vault...) — audit đã trình, chờ TL chốt scope.
