# RESULTS — BUG-KQ5A1D (SLP-222) — Trim Google app_id cho One Tap

Ngày verify: 2026-09-15 · Local env `slaunchpad.localhost` · Magento 2.4.8-p5, mode developer

## Fix

Module mới `Launchpad_MageplazaSocialLogin` (KHÔNG sửa vendor `Mageplaza_*`):

- `Plugin/SocialLoginPro/OneTapPlugin.php` — `afterGetClientId()`:
  `return is_string($result) ? trim($result) : $result;`
- `etc/di.xml` — plugin `launchpad_socialloginpro_onetap_trim_client_id` trên
  `Mageplaza\SocialLoginPro\Block\OneTap`
- `etc/module.xml` (sequence SocialLogin + SocialLoginPro), `registration.php`,
  `README.md`, `CHANGELOG.md`

## Verify framework (CLI qua interceptor — `/tmp/read_onetap.php` pattern)

Boot `Bootstrap::create(BP, $_SERVER)` → OM `create(OneTap::class)` → `getClientId()`.
Class trả về = `Mageplaza\SocialLoginPro\Block\OneTap\Interceptor` (plugin áp dụng).

| # | DB value (`config_id=70`) | expect | `getClientId()` | KQ |
|---|---------------------------|--------|-----------------|----|
| 1 | as-found `123123213` (clean, len9, dummy) | nguyên vẹn | len9 `[123123213]` padded=false | **PASS** (AC-002) |
| 2 | `␣␣9999999999␣␣` (14) | `9999999999` (10) | len10 `[9999999999]` | **PASS** (AC-001) |
| 3 | `␣9999999999` leading | `9999999999` | len10 | **PASS** |
| 4 | `9999999999␣` trailing | `9999999999` | len10 | **PASS** |
| 5 | `␣999␣9999␣` (2 đầu + internal space) | `999 9999` (chỉ trim 2 đầu) | len8 `[999 9999]` | **PASS** |

Null case (config row absent): không test destructive (không DELETE row as-found) —
code path `is_string($result) ? trim($result) : $result` passthrough null, giữ đúng
hành vi vendor (template in rỗng).

## Verify render-level (curl guest homepage, FPC flushed)

Điều kiện: `sociallogin/google/is_enabled=1`, tạm bật `sociallogin/google/one_tap=1`,
`config_id=70` = `␣␣9999999999␣␣` (padded), `cache:clean config` + `cache:flush full_page`.

- HTTP 200, `#g_id_onload` render tại line 8720:

      <div id="g_id_onload"
           data-client_id="9999999999"        ← DB padded 14 ký tự → render SẠCH 10 ký tự (AC-003)
           data-login_uri="http://slaunchpad.localhost/sociallogin/onetap/login/form_key/…/"
           data-context="signin">

- `data-login_uri` + `data-context` nguyên vẹn (AC-005 regression).

**Lưu ý phạm vi AC-003**: chỉ render default store view — config row thuộc scope
`default/0`, mọi store view (vi/en) dùng chung giá trị nên 1 lần render là đại diện;
staging QC nên check cả 2 store khi có credentials Google thật.

## Negative case + ENV restore as-found

- Restore `one_tap=0` → curl lại: `g_id_onload` **0** match (block không render — AC-005 PASS).
- Restore `app_id` = giá trị gốc (stashed `/tmp/appid_orig`, đã shred sau restore) →
  diff DB vs snapshot `.ai/evidence/BUG-KQ5A1D/env-snapshot-2026-09-15.txt` =
  **0 dòng lệch** → ENV as-found. Không row nào bị DELETE/tạo mới.

## DI wiring

- `config.php:424` `'Launchpad_MageplazaSocialLogin' => 1` (setup:upgrade regen —
  kèm noise reorder dòng khác, flag TL khi commit).
- `setup:di:compile` PASS; plugin-list `generated/metadata/primary|global|webapi_rest|plugin-list.php`
  có plugin ở cả 3 scope; interceptor `generated/code/Mageplaza/SocialLoginPro/Block/OneTap/Interceptor.php`.

## Scope diff

Chỉ: `app/code/Launchpad/MageplazaSocialLogin/**` (mới) + `app/etc/config.php`
(+1 module line, regen noise) + `.ai/records/bugs/BUG-KQ5A1D.md` + evidence này +
row `estimation-tracking.csv`. **0 file `app/code/Mageplaza/**`** (AC-004 PASS).
Các working-tree file khác (theme i18n CSV, styles.css, LESSONS_LEARNED…) thuộc
session song song — không thuộc change set này.

## Chưa verify ở local (thuộc staging QC sau deploy)

- Symptom thật của ticket (One Tap lỗi hay Google login thường lỗi) — cần log/screenshot
  staging; path OAuth login chính đã `trim()` từ vendor nên không thể lỗi vì whitespace.
- Sửa giá trị DB staging (Admin re-enter sạch + `cache:flush config`) — hành động DevOps/PM.
- Google GIS thật với client id production (credentials local là dummy).
