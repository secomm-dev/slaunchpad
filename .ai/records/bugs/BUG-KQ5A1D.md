---
id: BUG-KQ5A1D
type: bug
title: "[Social login] Config sociallogin/google/app_id bi thua khoang trang sau deploy staging"
project_code: SLP
parent:
external_refs:
  ticket: SLP-222
legacy_ids: []
mode: C
specification_level: MINI
spec_status: VALID
specification_ref: Embedded Mini-Spec
risk: low
status: in_progress
created: 2026-09-15
updated: 2026-09-15
ticket_ref:
affects_version: Magento 2.4.8-p5 + Hyva 3.x + Mageplaza_SocialLoginPro
decisions: []
decision_assessment: pending-tl
components:
  - app/code/Launchpad/MageplazaSocialLogin
source_areas:
  - auth-oauth-config
changes_project_state: false
changes_architecture: false
changes_integration: false
changes_known_limitations: false
verified_against_commit:
last_verified: 2026-09-15
supersedes: []
---

# [SLP][BUG-KQ5A1D] [Social login] Config sociallogin/google/app_id bi thua khoang trang sau deploy staging

<!-- External ticket: SLP-222. Bao cau hinh sociallogin/google/app_id tren staging chua khoang
     trang thua (leading/trailing whitespace) sau khi deploy staging, dan den Social Login Google
     loi. Ticket khong kem screenshot/log — symptom chinh xac tren staging can QC xac nhan. -->

## Summary

Trieu chung bao cao: config `sociallogin/google/app_id` tren staging bi thua khoang trang, Google
login loi. Phan tich code (`/task` 2026-09-15) cho thay **2 duong doc config khac nhau**:

| Path | File | Xu ly whitespace |
|------|------|------------------|
| OAuth login flow chinh (HybridAuth) | `Mageplaza_SocialLogin/Helper/Social.php:136` `getAppId()` | **da `trim()`** — chiu duoc padding |
| Google **One Tap** | `Mageplaza_SocialLoginPro/Block/OneTap.php:90` `getClientId()` | **doc RAW, khong trim** → inject verbatim vao `data-client_id` (`one_tap.phtml:25`) |

Nghia la: padding trong app_id **chi thuc su gay loi o path One Tap** (Google GIS client tu choi
client id khong hop le). Neu symptom giu la o Google login thuong thi whitespace chua chac la root
cause — can QC xac dinh symptom that tren staging.

**Nguon whitespace**: `sociallogin` config KHONG nam trong `config.php` (local lan staging deu
khong co trong file) → gia tri song trong DB `core_config_data` cua staging, den tu Admin paste
tay (field `system.xml` `type="password"` + `required-entry` — padding paste tu Google Cloud
console **khong nhin thay duoc**, khong co trim luc save) hoac buoc `config:set` trong runbook
deploy (deploy script chua commit — CI/CD TBD). Local DB 7 rows `sociallogin/google/*` deu clean
(dummy credentials) — khong reproduce duoc trang thai staging o local.

### Root cause

Hanh vi khong ro rang cua vendor: path One Tap doc config raw khong trim trong khi path OAuth
chinh co trim. Keo theo gia tri config co padding khong duoc chuan hoa tai mot diem doc duy nhat.

## Mini Spec

### Goal

Client id Google truyen vao storefront (One Tap `data-client_id`) luon sach khoang trang
dau/cuoi bat ke gia tri trong `core_config_data` co bi padding hay khong — phong vet tai doc,
khong phai sua du lieu staging (viec do thuoc DevOps/Admin, xem Out of Scope).

### Expected Behavior

1. `Mageplaza\SocialLoginPro\Block\OneTap::getClientId()` sau fix tra ve gia tri config
   `sociallogin/google/app_id` **da cat khoang trang dau/cuoi** (space/tab/newline — hanh vi
   `trim()` PHP).
2. Gia tri da sach (khong padding) tra ve **nguyen ven byte-for-byte** (trim idempotent).
3. Gia tri config khong ton tai (null) giu nguyen hanh vi cu (null passthrough — template in rong,
   dung nhu vendor hien tai).
4. Khong thay doi: OAuth login flow chinh (da trim tu vendor), giá tri luu trong DB, hanh vi
   cac provider khac (Facebook/Zalo/... deu di qua `Helper\Social::getAppId()` da trim).

### Constraints / Rules

- **KHONG sua vendor Mageplaza in place** (06_KNOWN_CONSTRAINTS: third-party source-committed —
  extend via plugin/preference only).
- Plugin `after` (khong `around`), chi trim string; khong cham provider data khac.
- Module dat theo convention hien co: `Launchpad_MageplazaSocialLogin` (nha chua cho fix
  SocialLogin/SocialLoginPro — pattern `Launchpad_MageplazaExtraFeeFix`/`MageplazaTranslate`).
- PHP 8.2+ compatible; khong ObjectManager truc tiep; khong hardcode credential.
- Auth surface (OAuth credentials) — **Tier-2 formality** (§12 Authentication): TL review bat buoc
  truoc khi release; precedent TASK-7P5RJP / BUG-QKX5BW.

### Out of Scope

- **Sua gia tri DB staging** (re-enter sach trong Admin staging + `cache:flush config`) — hanh
  dong DevOps/PM sau khi deploy code; ban than no khong the lam tu local.
- Prevention luc save (observer `admin_system_config_changed_section_sociallogin` trim toan bo
  field sociallogin gom `app_secret`) — de xuat TL duyet rieng (mo rong scope cham credential path
  nhieu hon).
- Symptom khac cua SocialLogin (button UI, popup...) — da giai quyet o SLP-160/SLP-185.
- `app_secret` staging co padding hay khong — QC check flag khi xac minh staging (khong doc gia tri
  qua document nay).

### Acceptance Criteria

- **AC-001**: Config `sociallogin/google/app_id` co padding (`"  id  "` v.d.) →
  `getClientId()` tra ve `"id"` (trim 2 dau) — framework-level verify.
- **AC-002**: Config da sach → `getClientId()` tra ve dung gia tri, khong bien doi khac.
- **AC-003**: Render-level: trang storefront (guest, One Tap enabled) render
  `data-client_id="id"` sach khong padding, 2 store vi/en.
- **AC-004**: Khong sua file nao thuoc `app/code/Mageplaza/**` — diff chi gom module
  `Launchpad_MageplazaSocialLogin` moi + `config.php` (setup:upgrade regen).
- **AC-005**: Regression: `data-client_id` van ton tai dung vi tri trong `#g_id_onload`; One Tap
  disabled → block khong render nhu cu.

## Approach

Mode C — plugin read-path (phuong an B duyet trong ticket analysis 2026-09-15):

1. Module moi `app/code/Launchpad/MageplazaSocialLogin`:
   - `registration.php`, `etc/module.xml` (require `Mageplaza_SocialLoginPro`),
     `README.md` + `CHANGELOG.md` ([WARN] rule Secomm-owned module).
   - `Plugin/SocialLoginPro/OneTapPlugin.php` — `afterGetClientId()`:
     `return is_string($result) ? trim($result) : $result;` (null/other passthrough).
   - `etc/di.xml` — wire plugin vao `Mageplaza\SocialLoginPro\Block\OneTap::getClientId`.
2. `setup:upgrade` + `setup:di:compile` as secomm (trap env: shell Claude = root; metadata
   plugin-list can regen — bai hoc TASK-QX93G3) + `cache:flush` as secomm.
3. Verify (framework + render), ENV restore as-found:
   - Snapshot toan bo rows `sociallogin/google/%` (config_id, scope, scope_id, path, value)
     TRUOC khi touched.
   - Framework: CLI script boot Magento + OM tao block `OneTap` → `getClientId()`:
     padding case (set tam gia tri padded dummy) + clean case (restore gia tri goc) + absent case
     (row goc co ton tai giu nguyen; khong DELETE row nao).
   - Render: curl storefront guest voi `one_tap` tam bat (neu chua bat) → grep
     `data-client_id` sach, 2 store; restore dung trang thai config goc (row da co → gia tri goc;
   - `cache:flush config` sau moi thay doi config.
4. Evidence `.ai/evidence/BUG-KQ5A1D/` (RESULTS.md + verify script + snapshot/restore log),
   estimation row, update CURRENT_STATE/NEXT_TASK.
5. Pre-review checklist → **TL review (Mode C, Tier-2 formality)** → QC staging sau deploy
   (buoc staging thuoc human).

## Fix (2026-09-15)

Module moi `app/code/Launchpad/MageplazaSocialLogin` (vendor `Mageplaza_*` nguyen ven):

- `Plugin/SocialLoginPro/OneTapPlugin.php` — `afterGetClientId()`:
  `return is_string($result) ? trim($result) : $result;` (null passthrough)
- `etc/di.xml` — plugin `launchpad_socialloginpro_onetap_trim_client_id` tren
  `Mageplaza\SocialLoginPro\Block\OneTap`
- `etc/module.xml` (sequence `Mageplaza_SocialLogin` + `Mageplaza_SocialLoginPro`),
  `registration.php`, `README.md`, `CHANGELOG.md`

`setup:upgrade` + `setup:di:compile` as secomm PASS; plugin-list co o 3 scope
(primary/global/webapi_rest); interceptor `OneTap/Interceptor.php` sinh dung.
`config.php:424` them module line (regen kem noise reorder — tach hunk khi commit).

## Verification (2026-09-15 — evidence `.ai/evidence/BUG-KQ5A1D/RESULTS.md`)

- **AC-001..002 PASS** (framework, qua `OneTap\Interceptor`): as-found clean `123123213`
  (dummy) nguyen ven; 4 bien the padded (2 dau / leading / trailing / 2 dau+internal)
  deu trim dung — internal space `999 9999` giu nguyen (chi trim 2 dau, dung hanh vi
  `trim()`). Null case khong test destructive (khong DELETE row as-found) — code path
  `is_string` guard passthrough.
- **AC-003 PASS** (render, curl guest homepage + FPC flush): DB `␣␣9999999999␣␣` →
  HTML `data-client_id="9999999999"` sach; `data-login_uri`/`data-context` nguyen ven.
  Ghi chu: render default store vi config scope `default/0` dung chung cho moi store view
  (vi/en) — QC staging nen check 2 store khi co credentials that.
- **AC-005 PASS**: `one_tap=0` (as-found) → `g_id_onload` khong render (curl 0 match).
- **AC-004 PASS**: `git status` — change set chi gom `app/code/Launchpad/MageplazaSocialLogin/**`
  (moi) + `config.php` (1 module line + noise regen) + record/evidence/estimation;
  0 file `app/code/Mageplaza/**`. Cac file working-tree khac (theme i18n, styles.css…)
  thuoc session song song.
- **ENV as-found**: DB diff vs snapshot `.ai/evidence/BUG-KQ5A1D/env-snapshot-2026-09-15.txt`
  = 0 dong lech; gia tri `app_id` goc stash trong /tmp roi shred; khong DELETE row nao.
  (Tren quang trinh verify da tam set: `app_id` padded dummy + `one_tap=1` — da restore.)

## Notes

- Staging (sau khi deploy code): Admin re-enter `sociallogin/google/app_id` sach + dong
  thoi check flag padding `app_secret` (khong doc gia tri) + `cache:flush config`. Viec sua
  gia tri DB staging thuoc DevOps/PM, khong thuoc change set.
- Option prevention luc save (observer trim toan bo field section sociallogin) — de xuat
  TL duyet rieng neu muon phong vet tu luc nhap lieu.
- Diem can xac nhan voi QC ticket: symptom that tren staging la One Tap hay Google login
  thuong — login flow chinh da `trim()` tu vendor (`Helper/Social.php:136`) nen whitespace
  khong the la root cause cua path do.
