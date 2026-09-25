# TASK-YHCJ79 — Verification results (2026-09-25)

Icon show/hide password cho popup Mageplaza (Hyva) + trang checkout OSC (luma).
Moi truong: local `http://slaunchpad.localhost`, Chrome 150 + Playwright (`/tmp/pw-cal`),
viewport 1280 + 375, store vi (`default`) + en (`launchpad_en`, locale local ke thua `vi_VN` →
label hien tieng Viet; script chon label ky vong theo `<html lang>`).

## Chay lai

`bash .ai/evidence/TASK-YHCJ79/run.sh vi|en` — tam set `sociallogin/general/information_require =
password` (de form request-info render o password), chay `verify.js`, **luon restore NULL** (trap EXIT).

## Ket qua cuoi

| Run | File | Ket qua |
|---|---|---|
| vi | `results-vi.txt` | **38/38 PASS** |
| en | `results-en.txt` | **38/38 PASS** |
| Regression SLP-207 popup login (config core = Yes) | `regression-slp207-login-yes.txt` | **8/8 PASS** (toggle khong anh huong submit/redirect) |

Moi field duoc check: nut `span[role=button]` co trong DOM, **nam tren cung** (`elementFromPoint`),
**trong mep phai input**, can giua doc (`centerΔ=0`, `hΔ=0`), `padding-right` input ≥ 44px, nen trong
suot + border 0, `tabIndex=0`; click → `text` + icon `eye-off` + `aria-pressed=true` + label "An mat
khau"; click lai → `password`; ban phim Enter → hien, Space → an.

| Ngu canh | Field | 1280 | 375 |
|---|---|---|---|
| Hyva popup login | `#social_login_pass` (+ sau validation loi) | PASS | PASS |
| Hyva popup create | `#password-social`, `#password-confirmation-social` (+ doc lap) | PASS | PASS |
| Hyva popup request-info | `#request-password-social`, `#request-password-confirmation` | PASS | PASS |
| Hyva checkout-auth popup (`x-if`, render muon) | `#form-login-password` | PASS | PASS |
| OSC create account | `#osc-password`, `#osc-password-confirmation` (+ sau validation loi) | PASS | PASS |
| OSC social modal (luma) | `#social_login_pass`, `#password-social`, `#password-confirmation-social` | PASS | PASS |
| OSC email step | `#customer-password` | gan toggle (khong bao gio hien — OSC `isLoginVisible` luon false) | idem |
| OSC Sign In popup | `#login-password` | gan toggle (link OSC mo social modal thay the) | idem |
| Console errors (Hyva home, OSC) | — | 0 | 0 |

Screenshots: `{vi,en}-{1280,375}-{hyva-login,hyva-create,hyva-checkout-auth,osc-create-account,osc-social-login,osc-social-create}.png`.
(O `*-social-create` 375 co vien focus-visible quanh icon — do buoc test ban phim de focus lai.)

## Findings trong qua trinh (da fix)

1. `$hyvaCsp` khong ton tai o theme luma → block loi, bi bo khoi trang OSC → guard `isset($hyvaCsp)`
   (`$viewModels` thi global).
2. Hyva validation boc input vao `div.field-reserved` moi sau submit dau → CSS input dung selector
   descendant; vi tri tinh theo input, khong theo host.
3. Popup Hyva mo bang scale transition → do bang layout offsets (khong `getBoundingClientRect`).
4. Input OSC `z-index: 2` (flex item) de len nut → host `isolation: isolate` + nut `z-index: 10`.
5. `#social-login-popup .social-login .input-text { padding: 10px 14px }` (1,2,0) → them rule
   `padding-right: 44px` trong `social-login-checkout.css`.
6. Ca 2 trang style `button` theo tag (`#popup_test button` nen xam; OSC design color
   `.checkout-container button:not(...) !important` nen navy) → dung `span[role=button]` + Enter/Space.

## Da kiem, khong phai do change nay

- OSC create account: sau validation o "Mat khau" tut 7px so voi "Xac nhan mat khau" — co san (do voi
  toggle bi vo hieu: 497 vs 490; co toggle: cung lech 7px).
- PDP console `Error fetching data` (ExtraFee Hyva) — co san, khong tinh.

## ENV as-found

- Snapshot `env-snapshot-2026-09-25.txt` (sociallogin/general/* + customer/startup/*); sau moi run diff = 0.
- `information_require` chi doi trong `run.sh` (restore NULL qua trap); 0 row
  `checkout/options/enable_guest_checkout_login` (tam them o 1 run trung gian → da xoa, script da bo buoc do).
- Moi run OSC them 1 san pham vao gio guest (quote guest local).
- Khong tao/sua customer. 1 run trung gian doc email 1 account dummy `@example.com` qua env (khong log) —
  buoc do da bo khoi script.

## Ghi chu line ending

`MageplazaTranslate/i18n/{vi_VN,en_US}.csv`, `MageplazaTranslate/CHANGELOG.md` va
`MageplazaSocialLogin/view/frontend/layout/onestepcheckout_index_index.xml` o working tree da la CRLF
truoc khi task nay sua (HEAD la LF) → da dua ve LF de diff chi con dong thay doi that.
