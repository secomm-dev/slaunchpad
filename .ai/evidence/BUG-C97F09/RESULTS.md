# BUG-C97F09 (SLP-207) — Verification results (2026-09-25)

Popup login Hyvä ton trong `customer/startup/redirect_dashboard`.
Moi truong: local `http://slaunchpad.localhost` (MAGE_MODE=default), Chrome 150 + Playwright
(`/tmp/pw-cal`), viewport 1280. Store vi = `default`, en = `launchpad_en` (chuyen qua
`?___store=` — cookie `store` khong doi duoc store view; locale store en local ke thua `vi_VN`
nen message van tieng Viet). Assert store view qua `CURRENT_STORE_CODE`.

## Phuong phap

- **Success path**: `page.route()` mock endpoint login (`customer/ajax/login` /
  `sociallogin/popup/login`) tra `{errors:false}` → khong tao/sua customer nao. Do navigation
  request dau tien cua main frame sau submit (mock khong tao session nen dashboard se bounce ve
  login — chi dich den cua redirect la doi tuong test).
- **Failure path**: server that, account dummy khong ton tai `slp207.nobody@example.com`.
- Script: `verify.js <vi-expect> [en-expect]`, `verify-osc.js`.

## Ket qua

| Run | Config | File | Ket qua |
|-----|--------|------|---------|
| Before fix (layout override tam go) | default = Yes | `results-before-fix-yes.txt` | **2/8** — 6 success case deu reload tai cho (`nav=/`, `/atlas-pouf.html`, `/checkout/cart/`) → **repro bug** |
| After fix | as-found (khong co row = No) | `results-no.txt` | **8/8 PASS** — reload dung trang (AC-002) |
| After fix | default = Yes | `results-yes.txt` | **8/8 PASS** — home/PDP/cart × vi/en → `/customer/account/` (AC-001) |
| After fix | default = Yes, `launchpad_en` = No | `results-scope-vi-yes-en-no.txt` | **8/8 PASS** — vi → dashboard, en → reload (AC-004 store scope) |
| After fix | — | `results-osc.txt` | **PASS** — `/onestepcheckout/` khong load override, popup luma van co, 0 console error |

- AC-003: sai mat khau (moi run) → `errors:true`, message "Tên đăng nhập hoặc mật khẩu không hợp lệ."
  trong popup, khong navigation (`wrong-password-*-flag-no.png`; `before-fix-*.png` = cung case
  tren template vendor).
- AC-005: markup popup giu nguyen (script dung dung selector vendor `#social_login_email`,
  `#social_login_pass`, `#bnt-social-login-authentication`, `#social-login-authentication
  .message-error`); console error duy nhat la PDP `Error fetching data: SyntaxError ... <!doctype`
  co san tu truoc (ExtraFee Hyvä — da ghi o `.ai/evidence/TASK-NWV2MQ/regression.js`), xuat hien
  ca before-fix.
- Render (curl): HTML popup co `launchpadLoginRedirect = {"toDashboard": false|true, "dashboardUrl":
  "http://slaunchpad.localhost/customer/account/"}` (escapeJs), 2 lan
  goi `launchpadLoginSuccessRedirect();`.

## ENV as-found

- Snapshot truoc khi test: `env-snapshot-2026-09-25.txt` (khong co row
  `customer/startup/redirect_dashboard` o bat ky scope nao).
- Da tam set: default = 1, stores/2 = 0 → da DELETE dung 2 row do; diff sau restore vs snapshot = **0 dong**.
- Layout override tam go cho run before-fix → da dat lai (owner secomm), `cache:flush` sau moi buoc.
- `verify-osc.js` them 1 san pham vao gio guest (quote guest local, giong TASK-NWV2MQ regression).
- Khong tao/sua/xoa customer nao.

## Chua cover (QC staging)

- Login that voi account that tren staging (session + dashboard render) — core, khong doi.
- Popup mo tu trang khac ngoai home/PDP/cart (PLP, CMS) — cung template/block global.
