# Implementation Plan: TASK-RJFTPZ — Phase GHN-A skeleton module Secomm_Ghn

## Metadata

| Field | Value |
|-------|-------|
| Task | TASK-RJFTPZ (parent FEAT-FQWEQ3) |
| Mode | A (shipping = generic risk category → Tier-2) |
| Specification | [specs/SPEC-FEAT-FQWEQ3-secomm-ghn-carrier-adapter.md](../specs/SPEC-FEAT-FQWEQ3-secomm-ghn-carrier-adapter.md) — FULL, VALID (owner draft + supplements §52; TL review spec text chạy cùng code pre-review) |
| Decision | [DEC-FEATFQWEQ3-001](../records/decisions/DEC-FEATFQWEQ3-001.md) (create dual-scheme — ảnh hưởng capability docblock) · DEC-FEATYA2C0W-004 (dependency chain) |
| Architecture basis | [SPIKE-9Z231Q](../research/SPIKE-9Z231Q-secomm-ghn-canonical-architecture.md) §13 (GHN-A proceed), §10 (client: auth headers, timeout, retry=0, PSR-3, error envelope + empty-body quirk) |
| Contract basis | `Secomm_ShippingCore\Api\Address\CarrierAddressCapabilityInterface` (TASK-AQT7V3) · `Secomm_VietNamAddress\Model\Scheme\VnSchemes` |
| Risk | High — nền module Tier-2 shipping + secrets, nhưng 0 đụng code hiện hữu (thư mục mới) |

## Approach

Module skeleton thuần theo SPEC §9/§10/§11 + §35 (lean): config `secomm_ghn/general/*` (token
encrypted backend — fix nợ legacy không timeout), `GhnApiClient` duy nhất sở hữu HTTP (envelope
`{code,message,data}` + **empty-body quirk → exception**, KHÔNG false-success), exception taxonomy
`Provider*` trong module (ShippingCore dùng outcome — translate ở boundary ở GHN-C), capability
`getRequiredScheme=VN_ADMIN_PRE_2025` (rating-driven; create path 2025 qua mapping — docblock ghi
DEC-FEATFQWEQ3-001), `GhnSchemes` constants, dedicated logger với token scrub. KHÔNG rate, KHÔNG
master data, KHÔNG webhook — phạm vi đúng GHN-A (SPIKE §13 "Không — proceed").

## Files affected

| File | Change type | Lý do |
|------|-------------|-------|
| `app/code/Secomm/Ghn/registration.php` | new | đăng ký module |
| `app/code/Secomm/Ghn/composer.json` | new | khai deps đủ (shipping-core + viet-nam-address) |
| `app/code/Secomm/Ghn/etc/module.xml` | new | sequence VietNamAddress + ShippingCore |
| `app/code/Secomm/Ghn/etc/config.xml` | new | defaults 9 field `secomm_ghn/general/*` (AC-A2) |
| `app/code/Secomm/Ghn/etc/adminhtml/system.xml` | new | admin section (token obscure + Encrypted backend) |
| `app/code/Secomm/Ghn/etc/acl.xml` | new | `Secomm_Ghn::config` |
| `app/code/Secomm/Ghn/etc/di.xml` | new | preference client + logger virtualType |
| `app/code/Secomm/Ghn/Api/Client/GhnApiClientInterface.php` | new | `post(operation, path, payload): array` |
| `app/code/Secomm/Ghn/Api/Exception/*.php` (8 files) | new | taxonomy SPEC §11 |
| `app/code/Secomm/Ghn/Model/Config.php` | new | paths + decrypt + base URL + timeouts |
| `app/code/Secomm/Ghn/Model/Address/GhnSchemes.php` | new | scheme constants (GHN-B dùng) |
| `app/code/Secomm/Ghn/Model/Capability/GhnAddressCapability.php` | new | capability contract impl |
| `app/code/Secomm/Ghn/Model/Client/GhnApiClient.php` | new | central client (AC-A3) |
| `app/code/Secomm/Ghn/Model/Client/GhnErrorTranslator.php` | new | code/message → typed exception |
| `app/code/Secomm/Ghn/Model/Client/GhnEndpoints.php` | new | path constants (verify docs khi dùng) |
| `app/code/Secomm/Ghn/Model/Logger/GhnLogger.php` + `Handler.php` | new | `var/log/secomm_ghn.log` + scrub |
| `app/code/Secomm/Ghn/i18n/{vi_VN,en_US}.csv` | new | strings CLI/admin |
| `app/code/Secomm/Ghn/{README.md,CHANGELOG.md}` | new | chuẩn Secomm module |
| `app/code/Secomm/Ghn/Test/Unit/**` (5 files) | new | AC-A3..A5 |
| `app/etc/config.php` | modify | entry `Secomm_Ghn => 1` qua `module:enable` (local) |

## Steps

1. Scaffolding registration + etc (module/config/system/acl/di) — risk: low — deps: none
   - verify: `bin/magento module:status | grep Secomm_Ghn`; admin thấy section; cache flush.
2. `GhnSchemes` + `GhnAddressCapability` — risk: low — deps: 1
   - verify: phpunit capability test (scheme đúng, fallback false).
3. Exception taxonomy + `GhnErrorTranslator` — risk: low — deps: 1
   - verify: phpunit map code→exception.
4. `GhnApiClient` + logger + scrub — risk: high — deps: 3
   - verify: phpunit envelope/empty-body/timeout; smoke 1 call sandbox master-data (manual, evidence).
5. i18n + README + CHANGELOG + `module:enable` + validators — risk: low — deps: 4
   - verify: validator không tăng FAIL mới; compile `php -l` toàn module.

## Regression risks

| Risk | Severity | Mitigation |
|------|----------|------------|
| Secrets (token) leak qua log/debug | high | scrub test bắt buộc (AC-A4); payload chỉ log khi debug=1 sau scrub |
| False-success shop-not-found (HTTP 200 rỗng) | high | empty-body → `ProviderRemoteException` + test (AC-A3) |
| Đụng nhầm file đang uncommitted (legacy GHN/ShippingCore) | medium | phạm vi cứng `app/code/Secomm/Ghn/**` + config.php entry |

## Test approach

- Unit: `php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml --filter 'Secomm\\Ghn'`
  (Config, Client envelope/error/timeout, Translator, Capability, TokenScrub).
- Integration/QC: smoke call sandbox master-data qua CLI tạm (evidence log sạch token).
- Không chạm checkout/payment/order ở phase này → L3 để dành GHN-C/D.

## Out of scope

Rate/master data/webhook/MQ/carriers/admin actions (các phase sau) · mọi sửa đổi
ShippingCore/VietNamAddress/legacy modules.

## Open questions / Escalation

- Retry policy client: mặc định KHÔNG retry (fail fast) — nếu TL muốn retry idempotent-only,
  bổ sung ở GHN-C plan (SPEC §10 không bắt buộc).
- Connection vs request timeout mapping trên `Magento\Framework\HTTP\Client\Curl`
  (`setTimeout`/`setConfig`) — verify hoạt động thật lúc smoke.
