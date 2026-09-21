# AI Pre-review: TASK-RJFTPZ — Phase GHN-A skeleton module Secomm_Ghn

Date: 2026-09-10 · Reviewer: Claude (AI self-review per AGENTS §8.3) · Spec: SPEC-FEAT-FQWEQ3

### Summary

Module mới `Secomm_Ghn` (26 files): registration/composer/etc, config `secomm_ghn/general/*`
(token encrypted backend, timeout — fix nợ legacy), `GhnApiClient` duy nhất sở hữu HTTP (envelope
`{code,message,data}`, empty-body quirk → exception, timeout → `ProviderTimeoutException`),
`GhnErrorTranslator` (code/message → 7 typed `Provider*` exception), `GhnAddressCapability`
(required scheme `VN_ADMIN_PRE_2025`, textual fallback false), `GhnSchemes` dual-scheme constants,
`GhnLogger` + Handler với token scrub unit-tested, i18n vi/en, README + CHANGELOG, 59 unit tests.

### Findings

#### Critical (must fix)

- (không có)

#### Warnings (should fix)

- **composer.json deps thiếu runtime deps (deviation khỏi plan)** —
  `app/code/Secomm/Ghn/composer.json` chỉ require `php` + `magento/framework`; plan ghi
  "require secomm/module-shipping-core + secomm/module-viet-nam-address". Nguyên nhân: đối chiếu
  thực tế `Secomm_ShippingCore` **không có composer.json** (không tồn tại package name
  `secomm/module-shipping-core`) — khai báo sẽ tạo constraint không resolve được. Deps runtime đã
  enforce qua `etc/module.xml` sequence. Options cho TL: (a) chấp nhận như hiện tại; (b) thêm
  composer.json cho ShippingCore (task riêng) rồi bổ sung require.
- **Hàm vượt guideline §7.2 [WARN]** — `GhnApiClient::post()` ~100 dòng (guideline ≤30) và
  `logCall()` 5 params (guideline ≤3). Là flow guard-clause tuyến tính chủ đích (transport → HTTP →
  body → envelope → log → throw/return); refactor tách method khi GHN-C thêm response handling
  (ghi vào plan GHN-C).

#### Notes (consider)

- **Base URL là class constants** (`Config::BASE_URLS`), không phải config field — provider fact,
  khớp §9 lean + DEC-FEATYA2C0W-004 D3 (environment switch = full profile switch). Legacy config
  được URL field; nếu sau này cần custom gateway URL phải thêm config (không có evidence cần).
- **Envelope `code !== 200` coi là lỗi** — không có evidence GHN dùng code khác cho success-with-
  warning; verify thêm tại lần smoke sandbox đầu (QC step).
- **6 PHPUnit deprecations** (mock generator, non-blocking) — tests 59/59 OK, 141 assertions.
- **Pending environment** (DB down — docker daemon off): `setup:di:compile` đầy đủ, admin UI check
  section config, sandbox smoke call `master-data/provinces` — đã liệt kê trong evidence §3 như
  QC/TL steps khi DB khả dụng.

### Scope Check

- [x] Changes match implementation plan — file tree khớp plan TASK-RJFTPZ (trừ composer deps đã
  nêu ở Warning 1)
- [x] No out-of-scope modifications — `git status`: chỉ `app/code/Secomm/Ghn/**` (new) +
  `app/etc/config.php` (file đã modified sẵn trong working tree; dòng mới duy nhất =
  `'Secomm_Ghn' => 1` line 453 qua `module:enable`) + `.ai/**` artifacts

### High-risk area check (AGENTS §12)

- **Shipping logic → Tier-2**: module mới thuộc shipping domain — **đúng theo quy trình, handoff
  TL review là gate tiếp theo** (task này KHÔNG đụng code shipping hiện hữu: 0 file ngoài thư mục
  module mới; legacy GiaoHangNhanh/GhnAddressMapper/ShippingCore/VietNamAddress nguyên vẹn).
- DB schema: không có (GHN-B mới có → TL review schema riêng).
- Secrets: token encrypted backend + scrub log — kiểm tra bằng test (AC-A4).

### Regression Risks

- 0 rủi ro regression lên behavior hiện hữu: module mới chưa hook vào rate/carrier/webhook/MQ;
  full scoped suite 1127 tests — Secomm_Ghn 0 error/failure; Tracking (7) pre-existing baseline;
  Ghtk (3) thuộc stream song song TASK-7AJ3K8.
- Rủi ro duy nhất: `module:enable` thêm entry vào `app/etc/config.php` — file này đã modified
  sẵn (uncommitted) bởi stream khác; khi commit cần chú ý gộp dòng.

### Suggested Tests (đã viết trong scope; bổ sung cho QC khi DB up)

- Smoke thật: cấu hình sandbox token/shop_id → gọi `master-data/provinces` qua client; assert
  response parse + log sạch token + duration_ms ghi.
- Envelope `code != 200` thật từ GHN sandbox (token sai) → `ProviderAuthenticationException`.
- (GHN-C) mapping dùng client với token sai → outcome UNAVAILABLE reason AUTH-family, không fake rate.

### Recommendation

**PASS WITH WARNINGS** — sẵn sàng cho TL review. Hai warnings là quyết định có chủ đích cần TL
acknowledge (composer deps; guideline line-length), không có critical.

---

**TL APPROVE 2026-09-10** (user acting as SA/TL, in-chat "approve"): cả 2 warnings acknowledged —
(1) composer.json giữ as-is (deps enforce qua module.xml sequence; ShippingCore chưa có composer
package name), (2) refactor line-length `post()` defer sang GHN-C plan. TASK-RJFTPZ → `done`.
QC pending-environment items (setup:di:compile đầy đủ / admin UI check / sandbox smoke call)
chuyển thành QC checklist chạy khi DB khả dụng — không block GHN-B.
