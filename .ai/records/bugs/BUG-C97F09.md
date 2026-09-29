---
id: BUG-C97F09
type: bug
title: "[Login] Config 'Redirect Customer to Account Dashboard after Logging in' = Yes khong hoat dong o popup login"
project_code: SLP
parent:
external_refs:
  ticket: SLP-207
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: low
status: in_progress
created: 2026-09-25
updated: 2026-09-25
ticket_ref:
affects_version: Magento 2.4.8-p5 + Hyva 3.x + Mageplaza_SocialLogin/SocialLoginPro
decisions: []
decision_assessment: pending-tl
components:
  - app/code/Launchpad/MageplazaSocialLogin
source_areas:
  - auth-login-redirect
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit: db0c9514
last_verified: 2026-09-25
supersedes: []
---

# [SLP][BUG-C97F09] [Login] Config 'Redirect Customer to Account Dashboard after Logging in' = Yes khong hoat dong o popup login

<!-- External ticket: SLP-207. "config login options: redirect customer to account dashboard
     after logging in = yes nhung khong thay hoat dong". Moi truong bao loi chua ro (local DB
     khong co row customer/startup/redirect_dashboard = default 0). -->

## Summary

Admin **Stores > Configuration > Customers > Customer Configuration > Login Options > Redirect
Customer to Account Dashboard after Logging in** (`customer/startup/redirect_dashboard`) = Yes,
nhung khach dang nhap o storefront van o lai trang hien tai.

### Root cause (trace code `/task` 2026-09-25)

Config chi duoc ap dung o login **full-page** (`customer/account/loginPost` →
`Magento\Customer\Model\Account\Redirect::processLoggedCustomer()`). Storefront Hyva dang dung
**popup login AJAX** cua Mageplaza (`sociallogin/general/authentication_popup = 1`; header
"Sign In" render `onclick="onClick()"`):

| Buoc | File | Hanh vi |
|------|------|---------|
| Endpoint AJAX | `vendor/magento/module-customer/Controller/Ajax/Login.php:200` / `Mageplaza/SocialLoginPro/Controller/Popup/Login.php:166` | Chi tra `redirectUrl` khi `redirect_dashboard = 0` + co redirect cookie; khi = 1 **khong tra URL dashboard** (by design core: AJAX login danh cho checkout) |
| JS popup Hyva | `Mageplaza/SocialLogin/view/frontend/templates/hyva/popup/form/authentication.phtml:230,298` | Login thanh cong → **luon `window.location.reload()`** |

Luong khac da dung, khong doi: login full-page; OAuth Google/Facebook (`AbstractSocial::_loginPostRedirect()`
mac dinh `customer/account`); popup checkout OSC (luma, template khac); popup minicart checkout
(`authentication-popup.phtml`, redirect rieng toi checkoutUrl).

## Mini Spec

### Goal

Popup login email/password tren storefront Hyva ton trong config core
`customer/startup/redirect_dashboard` giong login full-page.

### Expected Behavior

1. Config = **Yes** (store scope): login popup thanh cong → trinh duyet chuyen toi account
   dashboard (`Magento\Customer\Model\Url::getDashboardUrl()` = `customer/account/`) cua store
   hien tai, tu **moi trang** mo popup (home/PLP/PDP/cart...) — mirror core full-page khi
   `processLoggedCustomer()` chay.
2. Config = **No**: hanh vi giu nguyen nhu hien tai (`window.location.reload()`).
3. Login sai (response `errors: true`): hien loi trong popup, khong redirect, khong reload — nhu cu.
4. Gia tri config doc theo store scope; render trong HTML popup (FPC cache theo store — doi config
   can refresh cache nhu moi config storefront khac).

### Constraints / Rules

- KHONG sua vendor `app/code/Mageplaza/**` in place (06_KNOWN_CONSTRAINTS; README
  `Launchpad_MageplazaSocialLogin`: moi fix nam trong module nay).
- KHONG doi logic xac thuc/session/endpoint — chi doi dieu huong client-side sau khi server tra
  thanh cong.
- Giu nguyen markup/class/id cua popup (CSS SLP-160/203/211/246/259 bam theo).
- Override chi ap dung Hyva (`hyva_default` handle); luma scope (OSC checkout) khong bi anh huong.
- PHP 8.2+, `strict_types`, khong ObjectManager, ViewModel qua `$viewModels->require()`.

### Out of Scope

- Popup **Create account** (redirect sau dang ky — config khac `customer/create/...`).
- OAuth social login + config rieng SocialLoginPro "Redirect Page After Successful Login"
  (`sociallogin/general/redirect_url`) — luong OAuth da ve dashboard; viec gop 2 config la de xuat rieng.
- Loi vendor: option "Customer Dashboard" cua SocialLoginPro map sang `customer/account/login`.
- Honor `data.redirectUrl` khi config = No (vendor JS bo qua) — giu nguyen de khong doi hanh vi No.
- Popup checkout OSC (luma) + popup minicart checkout.
- Set gia tri config tren staging/production (Admin/DevOps).

### Acceptance Criteria

- **AC-001**: Config Yes → login popup thanh cong tu home / PDP / cart → URL cuoi = `<store base>/customer/account/`.
- **AC-002**: Config No → login popup thanh cong → reload dung trang dang dung (URL khong doi).
- **AC-003**: Login sai mat khau (server that) → message loi hien trong popup, URL khong doi.
- **AC-004**: HTML popup render dung flag + dashboard URL theo store (vi `default`, en `launchpad_en`).
- **AC-005**: Markup popup khong doi (form `#social-form-login`, `x-data`, class) — khong loi console moi.
- **AC-006**: Diff chi gom `app/code/Launchpad/MageplazaSocialLogin/**` + record/evidence; 0 file `app/code/Mageplaza/**`.

## Approach

Mode C — template override client-side trong `Launchpad_MageplazaSocialLogin`:

1. `ViewModel/LoginRedirect.php` — `isRedirectToDashboard()` (isSetFlag
   `CustomerUrl::XML_PATH_CUSTOMER_STARTUP_REDIRECT_TO_DASHBOARD`, store scope) +
   `getDashboardUrl()` (`Magento\Customer\Model\Url::getDashboardUrl()`).
2. `view/frontend/templates/hyva/popup/form/authentication.phtml` — copy vendor template, chi doi:
   them 1 helper `launchpadLoginSuccessRedirect()` (Yes → `location.href = dashboardUrl`, No →
   `reload()`) va goi o 2 nhanh success (`initCustomerLoginFormWithValidation` dang dung +
   `initCustomerLoginForm` dead code giu dong bo).
3. `view/frontend/layout/hyva_default.xml` — `referenceBlock social-login-popup-authentication`
   → template moi. `module.xml` da sequence sau `Mageplaza_SocialLogin`.
4. Khong can `setup:upgrade` (module da enable); `cache:flush` (layout + FPC) chay as secomm.
5. Verify (evidence `.ai/evidence/BUG-C97F09/`): snapshot config → Playwright (Chrome 150) mock
   response endpoint login (`errors:false`) cho Yes/No, server that cho case sai mat khau
   (khong tao/sua customer data); render check 2 store; restore config as-found.
6. README + CHANGELOG module; pre-review → **TL review** (auth surface — chi redirect client-side,
   khong doi xac thuc) → QC staging voi account that.

## Fix (2026-09-25)

Module `app/code/Launchpad/MageplazaSocialLogin` (vendor `Mageplaza_*` nguyen ven):

- `ViewModel/LoginRedirect.php` — `isRedirectToDashboard()` (store scope) + `getDashboardUrl()`.
- `view/frontend/templates/hyva/popup/form/authentication.phtml` — copy vendor; diff chi gom
  docblock + `$viewModels->require(LoginRedirect::class)`, object `launchpadLoginRedirect`
  (flag + URL qua `escapeJs`), helper `launchpadLoginSuccessRedirect()` va 2 dong
  `window.location.reload()` → `launchpadLoginSuccessRedirect()`.
- `view/frontend/layout/hyva_default.xml` — `referenceBlock social-login-popup-authentication`
  → template module.
- `README.md` (Fix 2 + ghi chu re-diff khi upgrade Mageplaza), `CHANGELOG.md`.

Khong can `setup:upgrade`/`di:compile` (module da enable, khong plugin moi; ViewModel resolve
runtime) — chi `cache:flush` as secomm.

## Verification (2026-09-25 — evidence `.ai/evidence/BUG-C97F09/RESULTS.md`)

- **Before fix** (config Yes, override tam go): 6/6 success case reload tai cho → repro bug.
- **AC-001 PASS**: Yes → home/PDP/cart × vi/en → `/customer/account/` (8/8).
- **AC-002 PASS**: No (as-found) → reload dung trang (8/8).
- **AC-003 PASS**: sai mat khau (server that) → loi trong popup, khong navigation.
- **AC-004 PASS**: default Yes + `launchpad_en` No → vi dashboard, en reload (FPC theo store).
- **AC-005 PASS**: selector vendor giu nguyen; console error duy nhat = PDP ExtraFee co san
  (ca before-fix). OSC `/onestepcheckout/` khong load override, popup luma van co.
- **AC-006 PASS**: change set = `app/code/Launchpad/MageplazaSocialLogin/**` + record/evidence;
  0 file `app/code/Mageplaza/**`. Cac file `M` khac trong working tree (Ahamove db_schema,
  config.php, hyva-themes.json, vendor_path.php, bin/magento) co tu truoc session.
- **ENV as-found**: diff DB vs snapshot = 0; khong tao/sua customer (success path mock endpoint).

## Notes

- Staging: Admin set Yes o scope can dung + refresh cache (config + FPC) sau khi deploy code.
- QC staging voi account that: login popup tu home/PLP/PDP/cart → dashboard; No → reload.
- De xuat rieng (Out of Scope): gop config SocialLoginPro "Redirect Page After Successful Login"
  voi config core cho luong OAuth; loi vendor option "Customer Dashboard" → `customer/account/login`.
