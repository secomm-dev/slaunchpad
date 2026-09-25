---
id: TASK-YHCJ79
type: task
title: '[Login/Checkout] Icon show/hide password cho cac form popup Mageplaza + trang checkout OSC'
project_code: SLP
parent: null
external_refs:
  tickets: TBD
legacy_ids: []
mode: B
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: medium
status: in_review
created: 2026-09-25
updated: 2026-09-25
ticket_ref:
affects_version: Magento 2.4.8-p5 + Hyva 3.x + Mageplaza_SocialLogin/SocialLoginPro + Mageplaza_Osc
decisions: []
decision_assessment: display/UX-only tren popup login + OSC page (§12 checkout) — Tier-2 formality, khong cham flow/order/payment/xac thuc; follow precedent TASK-8TXS2P (display-only OSC → Mode B + e2e QC bat buoc)
components:
  - app/code/Launchpad/MageplazaSocialLogin
  - app/code/Launchpad/MageplazaTranslate
source_areas:
  - social-login-popup
  - mageplaza-osc-checkout
  - ll-0011-luma-scope
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-09-25
supersedes: []
---

# [SLP][TASK-YHCJ79] [Login/Checkout] Icon show/hide password cho cac form popup Mageplaza + trang checkout OSC

<!-- Yeu cau user 2026-09-25: "can them icon show password o cac form cua popup mageplaza va o
     trang checkout". Chua co ma ticket SLP. -->

Plan: [TASK-YHCJ79-implementation-plan.md](../../plans/TASK-YHCJ79-implementation-plan.md)

## Ngu canh (inventory 2026-09-25)

| Ngu canh | Input password | Render |
|---|---|---|
| Popup Hyva — Login | `#social_login_pass` | `Mageplaza_SocialLogin::hyva/popup/form/authentication.phtml` (Alpine; module override SLP-207) |
| Popup Hyva — Create | `#password-social`, `#password-confirmation-social` | `hyva/popup/form/create.phtml` |
| Popup Hyva — Request info (social thieu email) | `#request-password-social`, `#request-password-confirmation` | `hyva/popup/form/email.phtml` |
| Popup Hyva — checkout auth (`#authentication-popup`) | `#form-login-password` | `hyva/form/authentication-popup.phtml` (trong `<template x-if="open">`) |
| OSC — email step (email da co tai khoan) | `#customer-password` | OSC KO `container/form/element/email.html` |
| OSC — billing "Create an account" | `#osc-password`, `#osc-password-confirmation` | OSC KO `container/address/billing/create.html` |
| OSC — popup Sign In | `#login-password` | OSC KO `container/authentication.html` |
| OSC — popup Social Login (luma, LL-0011) | `#social_login_pass`, `#password-social`, `#password-confirmation-social` | `Mageplaza_SocialLogin::popup/form/*.phtml` (luma) |

Pattern san co: Hyva login/register/reset dung heroicons solid `eye`/`eye-off` + aria "Show Password"/
"Hide Password" (theme `Magento_Customer/templates/form/resetforgottenpassword.phtml`). Vendor Mageplaza
Hyva da khai bao `showPassword: false` trong Alpine data nhung khong dung.

## Mini Spec

### Goal

Moi o nhap password trong cac popup Mageplaza (storefront Hyva) va tren trang checkout OSC co nut
icon mat de khach xem/an mat khau vua nhap.

### Expected Behavior

1. Moi input password trong scope co 1 nut icon mat nam **ben phai, ben trong** o input, can giua theo
   chieu doc voi input; text nhap khong chay de len icon.
2. Click (hoac Enter/Space khi focus nut) → input hien plain text, icon doi sang `eye-off`,
   `aria-pressed="true"`, `aria-label` = "Hide Password" / "An mat khau". Click lai → an, icon `eye`,
   `aria-pressed="false"`, label "Show Password" / "Hien mat khau".
3. Moi o doc lap (password va confirm password toggle rieng).
4. O password render sau khi trang load (KO checkout, Alpine `x-if`) cung co icon khi xuat hien.
5. Gia tri, validation, submit, autocomplete, luong login/dang ky/checkout **khong doi**.
6. Label nut dich vi/en ca tren trang Hyva lan trang checkout (luma scope).

### Constraints / Rules

- KHONG sua `app/code/Mageplaza/**`; KHONG override them template vendor (enhancer JS doc lap).
- KHONG doi KO binding / validation / flow checkout (OSC la vung Tier-2 §12) — chi them nut + doi `type`.
- JS thuan (khong Alpine/KO/jQuery) de chay ca Hyva lan luma; inline script goi
  `$hyvaCsp->registerInlineScript()` theo quy uoc Hyva.
- Mau: dung token co san `var(--color-ink-muted, #4a5565)` / `var(--color-ink, #101828)` — cung bieu thuc
  `social-login-checkout.css` dang dung (Hyva: `--color-ink-muted` = `--ds-text-gray-tertiary`; luma khong
  co token → fallback nhu file checkout hien co). Khong them mau moi.
- A11y: `<button type="button">`, focus-visible, `aria-pressed`, `aria-label`, `aria-controls`.
- Chuoi moi co ca vi_VN + en_US (BR-001); luma scope qua `Launchpad_MageplazaTranslate`.

### Out of Scope

- Trang Hyva full-page login/register/reset/edit account (da co icon mat tu Hyva/theme).
- Admin; password strength meter; hanh vi password manager cua trinh duyet.
- Thay doi logic popup/checkout, redirect sau login (SLP-207 / BUG-C97F09).

### Acceptance Criteria

- **AC-001**: Popup Hyva (home): login (1), create (2) co icon; click doi `type` text↔password + icon +
  aria; vi "Hien mat khau"/"An mat khau", en "Show Password"/"Hide Password".
- **AC-002**: Popup request-info (2) + `#authentication-popup` (1) co icon khi form hien thi (neu mo duoc o
  local); input render muon van duoc gan.
- **AC-003**: OSC checkout: 7 input (email step, create account x2, popup Sign In, popup social x3) co icon khi
  hien thi; toggle hoat dong.
- **AC-004**: Khong regression: popup login van submit (endpoint + redirect nhu truoc), validation Hyva/luma
  van hien loi, 0 console error moi tren home/PDP/checkout.
- **AC-005**: Visual: icon can giua doc trong input, khong de text, 1280 + 375.
- **AC-006**: Diff chi `app/code/Launchpad/MageplazaSocialLogin/**` + `app/code/Launchpad/MageplazaTranslate/i18n/*`
  + record/plan/evidence; 0 file `app/code/Mageplaza/**`.

## Approach

Xem plan. Tom tat: 1 template `password-toggle.phtml` (style + JS enhancer, icon heroicons, label `__()`)
trong `Launchpad_MageplazaSocialLogin`, gan vao `before.body.end` qua `hyva_default.xml` (root
`#social-login-popup`, `#authentication-popup`) va `onestepcheckout_index_index.xml` (root `body`);
MutationObserver cho input render muon; ResizeObserver can nut theo input; i18n luma o
`Launchpad_MageplazaTranslate`.

## Fix (2026-09-25)

- `app/code/Launchpad/MageplazaSocialLogin/view/frontend/templates/password-toggle.phtml` (moi):
  `<style>` + JS thuan. Toggle = `span[role=button][tabindex=0]` (Enter/Space) chen ngay sau input;
  host `.lp-password-toggle-host` (relative + `isolation: isolate`), input `padding-right: 44px`;
  vi tri theo layout offsets cua input (ResizeObserver); input render muon qua MutationObserver.
  Icon heroicons solid `eye`/`eye-off`, label `__('Show Password'/'Hide Password')`,
  `$hyvaCsp->registerInlineScript()` chi khi `isset($hyvaCsp)`.
- `view/frontend/layout/hyva_default.xml`: block `launchpad.sociallogin.password.toggle` (roots
  `#social-login-popup`, `#authentication-popup`) — file dung chung voi BUG-C97F09 (SLP-207); phan
  `referenceBlock` cua SLP-207 nam o working tree, commit rieng khi SLP-207 duoc duyet.
- `view/frontend/layout/onestepcheckout_index_index.xml`: block
  `launchpad.sociallogin.password.toggle.checkout` (root `body`).
- `view/frontend/web/css/social-login-checkout.css`: `padding-right: 44px` cho password input cua
  modal social luma.
- `app/code/Launchpad/MageplazaTranslate/i18n/{vi_VN,en_US}.csv`: +"Show Password", "Hide Password".
- README (muc "Show/hide password toggle") + CHANGELOG 2 module.

Khong setup:upgrade / di:compile (khong class PHP moi) — chi `cache:flush`.

## Verification (2026-09-25 — `.ai/evidence/TASK-YHCJ79/RESULTS.md`)

- **AC-001 PASS**: popup Hyva login (1) + create (2): toggle type/icon/aria, vi "Hien/An mat khau";
  en store local ke thua locale vi_VN → hien vi (en_US.csv co identity rows).
- **AC-002 PASS**: request-info (2, voi `information_require=password` tam bat trong `run.sh`) +
  `#authentication-popup` (`x-if`, render muon) co toggle.
- **AC-003 PASS**: OSC — create account (2) + modal social luma (3) toggle hoat dong; email step
  `#customer-password` + OSC Sign In `#login-password` duoc gan toggle nhung **khong bao gio hien**
  o cau hinh nay (OSC `isLoginVisible` luon false; link OSC mo modal social thay the).
- **AC-004 PASS**: regression SLP-207 popup login 8/8; validation Hyva + luma van hien loi, toggle
  giu vi tri; 0 console error moi; 0 exception moi.
- **AC-005 PASS**: 1280 + 375 vi/en 38/38 moi run; screenshots.
- **AC-006 PASS**: diff chi `Launchpad_MageplazaSocialLogin` + `Launchpad_MageplazaTranslate/i18n` +
  record/plan/evidence; 0 file `app/code/Mageplaza/**`.
- ENV as-found: DB diff vs snapshot = 0.

## Flags cho TL

- Chung file `hyva_default.xml` / README / CHANGELOG voi BUG-C97F09 (SLP-207, cho review): commit
  task nay chi stage phan TASK-YHCJ79 (hash-object), phan SLP-207 giu o working tree.
- Tren OSC, enhancer quet toan trang (`body`) — moi input password tuong lai tren trang checkout
  cung tu co toggle (chu y khi them field password moi).
- Chua co ma ticket SLP — can PM gan.
