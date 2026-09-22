# SLP-239 — Diagnostic: Google login không thành công trên staging

**Ngày:** 2026-09-18 · **Loại:** local reproduction + Google endpoint probing (read-only, không sửa code)

## Mục tiêu

Xác định nhánh lỗi của flow Mageplaza SocialLogin (Google): callback fail silent / session không persist / token exchange fail.

## Repro local (curl + cookie jar, không cần Google consent thật)

```bash
# Bước 1 — popup mở login endpoint
curl -s --resolve slaunchpad.localhost:80:127.0.0.1 -c /tmp/slp-ck.txt \
  "http://slaunchpad.localhost/sociallogin/social/login/type/google?<ts>"
# → HTTP 302 → https://accounts.google.com/o/oauth2/v2/auth?...&state=HA-<32 ký tự>
#   redirect_uri = http://slaunchpad.localhost/sociallogin/social/callback/?hauth.done=Google

# Bước 2 — callback mô phỏng Google redirect (giữ nguyên cookie jar)
curl -s --resolve slaunchpad.localhost:80:127.0.0.1 -b /tmp/slp-ck.txt \
  "http://slaunchpad.localhost/sociallogin/social/callback?hauth.done=Google&code=FAKE&state=<state>"
# → Popup error page: "Ooophs, chúng tôi đã nhận được lỗi: Unable to exchange code for
#   API access token. HTTP error 400. Raw Provider API response: invalid_request —
#   You can't sign in to this app because it doesn't comply with Google's OAuth 2.0 policy..."
```

## Kết luận kỹ thuật

1. **Flow code Mageplaza SỬNG đúng đến bước token exchange**: sinh auth URL + `state` đúng; session state persist qua 2 request popup (cookie jar); callback parse `hauth.done` → `hauth_done` đúng (PHP key normalization); state verification PASS.
2. **Điểm chết = exchange code tại Google** — lỗi render thẳng vào popup qua `AbstractSocial::setBodyResponse()` (`Controller/Social/AbstractSocial.php:474`), popup KHÔNG tự đóng.
3. **Error taxonomy (probe trực tiếp `oauth2.googleapis.com/token`):**
   - client_id không tồn tại / bị block → `invalid_request` + *"doesn't comply with Google's OAuth 2.0 policy"*
   - client hợp lệ + secret sai → `invalid_client` + *"The provided client secret is invalid."*
4. Trên staging user HOÀN TẤT consent → `client_id` hợp lệ + redirect URI đăng ký đúng → lỗi còn lại nằm ở **client_secret sai** hoặc **app bị Google policy block** (OAuth consent screen status/verified state trong Google Cloud Console).

## Đã loại trừ

- Template override theme (`Secomm/launchpad/Mageplaza_SocialLogin/...`) — `php -l` OK; so sánh 1:1 với vendor hyva template (cả hai đều có `data-mage-init` + inline `onclick` — Hyvä không có RequireJS nên widget inert, chỉ inline onclick chạy → 1 popup duy nhất).
- Session state loss ở tầng code — repro chứng minh state persist OK (ít nhất với local file sessions).
- `getProviderConnected()` thiếu 'google' (`Model/Social.php:482`) — không rẽ vào nhánh này vì callback luôn mang `hauth.done=Google`.
- ParseError template `social_buttons.phtml:63` trong `var/log/exception.log` (Sep 11) — đã được sửa (lint OK hiện tại).

## Secondary finding (vendor bug)

`Controller/Social/Email.php:150` — `$params['type']` có thể undefined → Warning/CRITICAL trong `system.log`/`exception.log` local (Aug 26). Ảnh hưởng flow "required more info" (email submit). Chỉ relevant nếu staging bật `require_more_info` / `check_mode`.

## Còn thiếu để chốt root cause

1. **URL staging** (hoặc access) — probe public auth endpoint sẽ lộ `client_id` + `redirect_uri` thực tế.
2. Nội dung popup trên staging sau khi chọn tài khoản (error message chính xác → phân biệt `invalid_client` vs policy block).

## Bước tiếp theo

- Có URL staging → curl `sociallogin/social/login/type/google` → lấy client_id/redirect_uri → đối chiếu Google Console (secret, consent screen status, redirect URI list).
- Sau khi chốt root cause: nếu là config-side (credential/redirect) → fix ngay trên staging admin; nếu code-side → mint BUG- record + Mini-Spec + plan, escalate **Tier 2** (auth/customer data — AGENTS.md §12) trước khi sửa. Không sửa vendor in-place; dùng plugin/preference qua `Launchpad_`.

---

# Phần 2 — Probe staging demo (2026-09-18, sau khi nhận URL)

Target: `https://slaunchpad-demo.secomm.vn/` (Cloudflare: 104.26.7.87; SSH không vào được, mọi probe qua public surface).

## Kết quả probe

| Probe | Kết quả | Suy ra |
|---|---|---|
| TLS cert | `CN=secomm.vn`, Google Trust Services, valid 13 Aug – 11 Nov 2026 | Cert ĐÃ renew (memory cũ nói expired — cần update) |
| `GET /sociallogin/social/login/type/google?<ts>` | 302 → `accounts.google.com/o/oauth2/v2/auth` với `client_id=1046818576324-…7345.apps.googleusercontent.com`, `redirect_uri=https://slaunchpad-demo.secomm.vn/sociallogin/social/callback/?hauth.done=Google`, state OK | Module chạy đúng, sinh đúng tham số |
| `GET accounts.google.com/...` với client_id + redirect_uri của demo | **HTTP 302 (chấp nhận)** | `client_id` hợp lệ + redirect URI đã đăng ký đúng → giải thích được vì sao user hoàn tất consent |
| Callback demo (code giả + đúng state từ cookie jar) | `invalid_grant` (KHÔNG phải `invalid_client`/policy block) | **client_secret trên demo HỢP LỆ** — Google xác thực app pass, chỉ từ chối code giả. Với code thật exchange sẽ thành công |
| Đối chiếu taxonomy: fake secret → | `invalid_client` ("The provided client secret is invalid.") | Demo không rơi case này |
| GraphQL `storeConfig` | `store_code=default` ("Vietnamese"), `website_id=1` | 1 website → giả thuyết dup-email cross-website yếu |
| Homepage DOM hooks | `mp-popup-social-content` ×8, `.social-login.authentication` ×26, `.social-login.fake-email` ×12, `#social-form-fake-email` ×3, `social-login-channel` ×1, `socialAuthenticationPopup` ×1 | Broadcast listener + fake-email form ĐỦ trên demo → branch `requiredMoreInfo` (Hyvä) không dead-end vì thiếu DOM |

## Kết luận sau probe demo

Google credentials demo **hợp lệ hoàn toàn** (client_id + secret + redirect URI). Flow server-side chạy đúng đến hết token exchange. Lỗi nằm **SAU exchange**, và trong code chỉ có 2 nhánh kết thúc bằng "opener reload nhưng không login":

1. **Exception khi tạo/link customer** — `createCustomerSocial` throw (vd: `send_password=Yes` + SMTP demo fail khi gọi `newAccount()` → catch **xóa customer vừa tạo** rồi rethrow) → `emailRedirect` chỉ add error message → `_appendJs(null, null)` broadcast `windowClose` + reload → **đúng 100% triệu chứng**; error message nằm trong session messages, dễ bị bỏ qua. (`Model/Social.php:259-263`, `AbstractSocial.php:216-219,441-443`)
2. **Session cookie mất sau reload** sau khi login thành công (cookie/SameSite/Cloudflare HTML cache) — ít khả năng hơn vì state check trong callback đã chứng minh session hoạt động trong flow.

Phụ thuộc config demo chưa thấy được: `sociallogin/general/send_password`, `sociallogin/general/check_mode`, `sociallogin/general/require_more_info`, trạng thái Mageplaza SMTP trên demo.

## Cần demo-side để chốt (người có access demo server/admin)

```bash
# trên demo server
grep -iE "social|InputMismatch|UnableToSend|SMTP|Error" var/log/exception.log | tail -20
grep -i "social" var/log/system.log | tail -20
```

- Admin > Stores > Config > Mageplaza Extensions > Social Login: General → **Send Password to Customer?**, **Check Mode?**, **Require More Info?**; SMTP module enabled + transport test.
- Khi retry login Google: chú ý (a) popup có đóng ngay không, (b) sau reload có message lỗi đỏ nào không, (c) vào trực tiếp `/customer/account/` xem có đăng nhập không (phân biệt case 2).

---

# Phần 3 — Browser probe (Playwright headed) trên demo: REPRODUCED + local hóa root cause

Config demo đã nhận từ user: `Send Password = Yes` (store view), `Require More Info = off`, `Require Enter Password (check_mode) = No`, SMTP demo hoạt động. → loại trừ SMTP, check_mode, requiredMoreInfo.

## Setup probe

Playwright Chromium headed (WSLg) + cookie jar SẠCH (`COOKIES-BASELINE []`), script `/tmp/pw-cal/slp239-probe.mjs`, logs `/tmp/pw-cal/slp239-log.txt` (+ reg/login contrast logs). Flow: mở demo → bấm Google (fallback `clickLoginSocial()` — selector modal không khớp, behavior y hệt vendor) → user đăng nhập Google thật trong popup → capture toàn bộ.

## Kết quả run 1 (04:57–04:58 UTC, repro thành công)

```
COOKIES-BASELINE []                                    ← jar sạch: stale-cookie theory DEAD
POPUP-OPENED accounts.google.com/v3/signin/identifier… (client_id demo, state OK)
POPUP-RESP 200 …/sociallogin/social/callback/?hauth.done=Google&state=HA-…
CALLBACK-SET-COOKIE ["X-Magento-Vary=046fd73f…; Max-Age=3600; secure; HttpOnly; SameSite=Lax"]
                                                       ← CHỈ vary. KHÔNG có Set-Cookie PHPSESSID!
OPENER-DOC 200 homepage fpc=HIT   (04:58:11.451 — opener reload qua BroadcastChannel = chạy đúng)
OPENER-DOC 302 /customer/account/ fpc=MISS → 200 login page
LOGGED-IN=false
```

## Probe đối chứng (cùng cơ chế session của Magento, khác controller)

| Flow | Response | Set-Cookie | Đăng nhập giữ được? |
|---|---|---|---|
| `POST /customer/account/createpost/` | 302 | **`PHPSESSID=es34o3…`** + form_key + deletes | ✅ dashboard |
| `POST /customer/account/loginPost/` | 302 | **`PHPSESSID=6n647…`** + tương tự | ✅ dashboard |
| `GET /sociallogin/social/callback/` | 200 | **CHỈ `X-Magento-Vary`** | ❌ guest |

→ Session infra demo OK (registration + login thường survives). Lỗi **riêng ở response của social callback**: cookie session regenerate không bao giờ đến browser.

## Cơ chế (khớp core code Magento 2.4.8)

`AbstractSocial::refresh()` → `setCustomerAsLoggedIn()` (queue header X-Magento-Vary) → `regenerateId()`:
- `vendor/magento/framework/Session/SessionManager.php::regenerateId()` mở đầu bằng `if (headers_sent()) return $this;` — nếu headers đã flush: KHÔNG regen, KHÔNG gửi PHPSESSID → khớp (1) callback chỉ có vary.
- Nếu regen chạy: PHP `session_regenerate_id()` queue Set-Cookie mới + session cũ bị đánh dấu `destroyed` (race protection) → request sau mang id cũ sẽ nhận **session rỗng** → cũng khớp guest.

Cả hai nhánh đều tập trung về: **output/headers bị flush trong lúc controller callback chạy** (echo sớm — nghi PHP notice/warning với `display_errors` bật trên demo) hoặc header bị mất trên đường truyền (CF). Chưa phân biệt được do callback raw body chưa capture được (run 2 user chưa kịp đăng nhập Google, timeout).

## Kết luận đã chứng minh

1. Deterministic repro trên browser sạch (không stale cookie, không Playwright artifact — main-tab test của user trên Chrome thật cũng vậy).
2. Google credentials + flow code + session infra demo: SỬNG.
3. Điểm chết: response của `/sociallogin/social/callback` không mang cookie session regenerate; session cũ bị vô hiệu → guest.
4. Yếu tố phụ: opener reload nhận FPC HIT (cached logged-out homepage) → user nhìn thấy "reload nhưng không login" dù trạng thái session thật ra đã guest từ trước.

## Việc còn lại — demo server (chốt sub-mechanism cuối)

```bash
# 1. Tìm dấu hiệu headers đã flush sớm trong lúc callback
grep -iE "Cannot modify header|headers already sent|Unable to send|cookie" \
  var/log/{system,exception,debug}.log | tail -30
# 2. PHP config
php -i | grep -E "output_buffering|display_errors|session.use_strict_mode"
# 3. Soi raw Set-Cookie của callback trong access log (nếu log upstream headers)
```
