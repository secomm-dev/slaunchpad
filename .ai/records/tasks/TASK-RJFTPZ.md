---
id: TASK-RJFTPZ
type: task
title: 'Phase GHN-A — Skeleton module Secomm_Ghn: registration, config, GhnApiClient, exception taxonomy, capability'
project_code: SLP
parent: {type: feature, id: FEAT-FQWEQ3}
mode: A
specification_level: FULL
spec_status: VALID            # SPEC-FEAT-FQWEQ3 — canonical Full Spec của FEAT (slice reference; không duplicate spec)
specification_ref: ../../specs/SPEC-FEAT-FQWEQ3-secomm-ghn-carrier-adapter.md
risk: high                    # module mới 0 đụng code hiện hữu nhưng là nền Tier-2 shipping; client + secrets
status: done                  # TL approve 2026-09-10 (user acting TL/SA): composer deps chấp nhận as-is (module.xml sequence enforce deps; ShippingCore chưa có composer package); line-length post() refactor defers sang GHN-C. QC pending-env (DB down): setup:di:compile đầy đủ + admin UI check + sandbox smoke call
priority: high
decision_assessment: none-material   # shape theo SPEC §9..§11 + DEC-FEATFQWEQ3-001 đã accepted — không DEC mới
decisions: [DEC-FEATFQWEQ3-001, DEC-FEATYA2C0W-004]
components:
  - CMP-GHN
source_areas:
  - app/code/Secomm/Ghn/
changes_project_state: true
created: 2026-09-10
updated: 2026-09-10
owner: [dev]
related_tickets: [TASK-MZ2TCB]
---

# [SLP][FEAT-FQWEQ3][TASK-RJFTPZ] Phase GHN-A — Skeleton module Secomm_Ghn: registration, config, GhnApiClient, exception taxonomy, capability

## Embedded Mini-Spec

*(behavioral contract của slice — đầy đủ tại specs/SPEC-FEAT-FQWEQ3-secomm-ghn-carrier-adapter.md, FULL — đặc biệt §9 Config, §10 API Client, §11 Provider Exceptions, §52 Supplements)*

### Goal

Module `Secomm_Ghn` tồn tại độc lập (không runtime-depend legacy GHN modules) với nền vận hành
API: config lean + encrypted token, `GhnApiClient` central (base URL theo environment, Token+ShopId
headers, timeout, envelope parsing, error translation, safe logging), exception taxonomy `Provider*`,
address capability declare `VN_ADMIN_PRE_2025`. KHÔNG rate/master-data/webhook/MQ ở phase này.

### Expected Behavior

1. `bin/magento module:enable Secomm_Ghn` thành công; module.xml sequence `Secomm_VietNamAddress`,
   `Secomm_ShippingCore` (+ Magento_Store/Sales/Quote); composer khai đủ deps.
2. Config `secomm_ghn/general/*`: enabled(0), environment(sandbox|production), api_token
   (obscure + `Magento\Config\Model\Config\Backend\Encrypted`), shop_id, payment_type(2),
   required_note(CHOXEMHANGKHONGTHU), debug(0), connection_timeout(10), request_timeout(30);
   `Model\Config::getApiToken()` decrypt qua `EncryptorInterface`; `getBaseUrl()` theo environment
   (dev-online-gateway / online-gateway …/shiip/public-api).
3. `GhnApiClient::post(operation, path, payload): array` — headers Content-Type/Token/ShopId;
   parse envelope `{code,message,data}`: code 200 → trả `data`; code != 200 → `GhnErrorTranslator`
   throw typed `Provider*Exception`; **body rỗng HTTP 200 → `ProviderRemoteException`** (quirk
   shop-not-found); malformed JSON → `ProviderRemoteException`; timeout → `ProviderTimeoutException`.
4. Exception taxonomy `Api/Exception/`: base `GhnApiException` (LocalizedException) + 7 exception
   SPEC §11 (Authentication/InvalidAddress/ServiceUnavailable/InvalidRequest/RateUnavailable/
   Timeout/Remote).
5. Logging: 1 dòng context mỗi call (operation, shop_id, http_status, provider_code, duration_ms)
   vào `var/log/secomm_ghn.log`; payload chỉ khi debug=1 sau khi scrub token — token/PII không bao
   giờ xuất hiện trong log (SPEC §48).
6. `GhnAddressCapability implements CarrierAddressCapabilityInterface`:
   `getRequiredScheme() = VnSchemes::VN_ADMIN_PRE_2025` (rating-driven; docblock ghi rõ dual-scheme
   D1 — create path resolve 2025 qua mapping, không qua handoff); `supportsTextualFallback() = false`.
7. `Model\Address\GhnSchemes` constants `GHN_ADMIN_2025`/`GHN_ADMIN_PRE_2025` + STATUS_ACTIVE/
   DISABLED + `assertKnown()` (để GHN-B dùng).

### Constraints / Rules

- KHÔNG đụng file nào ngoài `app/code/Secomm/Ghn/**` + entry trong `app/etc/config.php` (enable).
- KHÔNG tái sử dụng IntegrationBase legacy; KHÔNG Curl inline; HTTP qua core client DI-inject.
- KHÔNG log token/PII; KHÔNG false-success trên body rỗng; KHÔNG retry auto ở phase này (fail fast).
- Lean theo SPEC §35 — không tạo interface/class chỉ để mirror directory.
- PHP 8.2+ strict_types, no ObjectManager; strings mới vào `vi_VN.csv` + `en_US.csv`; README + CHANGELOG.
- KHÔNG sửa ShippingCore/VietNamAddress.

### Out of Scope

Rate/services/leadtime (GHN-C) · master data sync + mapping tables + CLI (GHN-B) ·
shipment/MQ/webhook/label (GHN-D/E) · carriers Magento (`Model/Carrier`) · admin actions ·
capability matrix mở rộng (SPEC §31 — phần còn lại ở GHN-C/D) · ShippingCore slices.

### Acceptance Criteria

- AC-A1: module enable + `setup:upgrade` sạch (không schema); `module:status` thấy `Secomm_Ghn`.
- AC-A2: admin section `secomm_ghn` hiển thị đủ 9 field; token lưu encrypted (không plain trong
  `core_config_data`).
- AC-A3: `GhnApiClient` unit test cover: success envelope; error code → đúng typed exception; empty
  body → `ProviderRemoteException`; malformed JSON; timeout → `ProviderTimeoutException`.
- AC-A4: token-scrub test — assert token không xuất hiện trong output logger ở mọi mode.
- AC-A5: capability test — required scheme đúng `VN_ADMIN_PRE_2025`, textual fallback false.
- AC-A6: `php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml --filter 'Secomm\\Ghn'` green;
  validators `--check-specs --check-records --check-identity` không tăng FAIL mới; evidence tại
  `.ai/evidence/TASK-RJFTPZ/`.

## Plan

`../plans/TASK-RJFTPZ-implementation-plan.md`
