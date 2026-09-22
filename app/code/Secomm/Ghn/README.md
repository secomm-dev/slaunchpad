# Secomm_Ghn

Giao Hàng Nhanh (GHN) carrier adapter cho Secomm Launchpad — **carrier adapter chuẩn đầu tiên** trên
`Secomm_ShippingCore`, theo [SPEC-FEAT-FQWEQ3](../../../.ai/specs/SPEC-FEAT-FQWEQ3-secomm-ghn-carrier-adapter.md).

Thay thế `Secomm_GiaoHangNhanh` + `Secomm_GhnAddressMapper` sau khi đạt parity gate (spec §41).
Hai hệ module **chạy song song tách biệt** đến khi cutover (GHN-F): carrier code `ghn`, webhook
route, MQ topic và config namespace `secomm_ghn/*` đều mới hoàn toàn — không đụng legacy.

## Kiến trúc

```text
Secomm_Ghn  →  Secomm_ShippingCore  →  Secomm_VietNamAddress
```

- KHÔNG runtime-depend legacy GHN modules; KHÔNG carrier nào được depend `Secomm_Ghn`.
- Rating: `VN_ADMIN_2025` → ShippingCore handoff (`VN_ADMIN_PRE_2025`, exact old ward) → mapping
  → legacy `district_id + ward_code` → Calculate Fee.
- Create Order: `VN_ADMIN_2025` → mapping → `GHN_ADMIN_2025` names + `is_new_to_address=true`
  (DEC-FEATFQWEQ3-001) — rating và create **không reuse resolution path**.
- Fail closed: không magic rate fallback, không fuzzy match name runtime, empty-body = exception.

## Cấu trúc (GHN-A + GHN-B)

| Thành phần | Vai trò |
|---|---|
| `Model\Config` | đọc config `secomm_ghn/general/*`; token decrypt qua `EncryptorInterface` |
| `Model\Client\GhnApiClient` | **duy nhất** điểm HTTP ra GHN: base URL theo environment, headers Token/ShopId, timeout, envelope `{code,message,data}`, error translation, safe logging |
| `Model\Client\GhnErrorTranslator` | mã lỗi GHN → typed `Provider*Exception` (SPEC §11) |
| `Model\Client\GhnEndpoints` | path constants (chỉ path đã verify docs 2026-09-10 — không invent) |
| `Api\Exception\*` | taxonomy: Authentication / InvalidAddress / ServiceUnavailable / InvalidRequest / RateUnavailable / Timeout / Remote |
| `Model\Capability\GhnAddressCapability` | `CarrierAddressCapabilityInterface`: required scheme `VN_ADMIN_PRE_2025`, textual fallback = false |
| `Model\Address\GhnSchemes` | constants scheme `GHN_ADMIN_2025` / `GHN_ADMIN_PRE_2025` |
| `Model\Logger\GhnLogger` + Handler | `var/log/secomm_ghn.log`; context 1 dòng/call; token luôn scrub |
| `Model\Address\Sync\*` | master-data fetchers (legacy `master-data/{province,district,ward}` cho PRE_2025; v3 `province/all` + `ward/all-by-province-id` cho 2025) + `MasterDataSynchronizer` (upsert theo `(scheme, provider_key)`, mất row → DISABLED) |
| `Model\Address\Mapping\*` | `MappingMatcher` (candidate tooling — KHÔNG BAO GIỜ tự ghi APPROVED) · `MappingSuggester` (workfile REVIEW_REQUIRED/AMBIGUOUS/UNRESOLVED) · `MappingAuditor` (mapped/unmapped/ambiguous/invalid/stale/duplicate/dangling/disabled + coverage % + production_ready) · `GhnMappingResolver` (runtime fail-closed + cache hits) · `NameNormalizer` · `AliasRepository` (CSV candidate input) |
| `Model\Address\Dataset\*` + `Model\Address\Export|Import\*` | **Dataset lifecycle (DEC-FEATFQWEQ3-002)**: exporter deterministic CSV + manifest; importers fail-loud APPROVED-only UPSERT; bundled `data/` bootstrap |
| `Model\ResourceModel\{AddressUnit,AddressMapping}` | parameterized SQL; `insertOnDuplicate` batch; không đụng bảng canonical/runtime khác |
| `Model\Cache\MappingCache` | cache type `secomm_ghn_mapping` — chỉ cache resolution THÀNH CÔNG |
| `Console\Command\{SyncAddressCommand,AuditAddressCommand}` | `secomm:ghn:address:sync --scheme --dry-run` · `secomm:ghn:address:audit --scheme --format=table|json` |

### Bảng dữ liệu (SPEC §5/§6)

| Bảng | Mục đích | Khóa |
|---|---|---|
| `secomm_ghn_address_unit` | GHN master data dual-scheme (1 row = 1 unit trong 1 scheme) | `UNIQUE(scheme_code, provider_key)`; self-FK `parent_id` RESTRICT |
| `secomm_ghn_address_mapping` | bridge canonical↔GHN (chỉ row APPROVED) | `UNIQUE(secomm_scheme_code, secomm_unit_code)`; FK unit CASCADE |

## Cấu hình

GHN là shipping method → cấu hình nằm ở **`Stores → Configuration → Sales → Delivery Methods → GHN Shipping (Secomm_Ghn)`** (section chuẩn `carriers`, group `secomm_ghn`):

| Path | Mặc định | Ghi chú |
|---|---|---|
| `carriers/secomm_ghn/enabled` | `0` | |
| `carriers/secomm_ghn/environment` | `sandbox` | sandbox = `dev-online-gateway.ghn.vn/shiip/public-api` |
| `carriers/secomm_ghn/api_token` | — | `obscure` + backend Encrypted |
| `carriers/secomm_ghn/shop_id` | — | |
| `carriers/secomm_ghn/payment_type` | `2` | preserve legacy behavior |
| `carriers/secomm_ghn/required_note` | `CHOXEMHANGKHONGTHU` | preserve |
| `carriers/secomm_ghn/debug` | `0` | payload scrub mới được ghi |
| `carriers/secomm_ghn/connection_timeout` | `10` | legacy không có timeout |
| `carriers/secomm_ghn/request_timeout` | `30` | legacy không có timeout |

## Address dataset lifecycle (DEC-FEATFQWEQ3-002)

Mapping production KHÔNG bao giờ được sinh tự động. Quy trình:

```text
GHN API → secomm:ghn:address:export (--scheme --dir --version)
      → deterministic CSV (master/) + manifest.json (counts + sha256)
OFFLINE review (AI/manual, đối chiếu canonical Secomm CSVs)
      → reviewed mapping CSV (APPROVED / REVIEW_REQUIRED / UNRESOLVED / AMBIGUOUS)
secomm:ghn:address:import (--dir; mặc định = bundled data/ bootstrap)
      → chỉ row APPROVED được activate (UPSERT, fail-loud, không truncate)
secomm:ghn:address:audit (--scheme --format) → production_ready gate
secomm:ghn:address:suggest → workfile đề xuất (candidate tooling, không tự approve)
```

- Portable identity: Secomm `scheme_code + unit_code` ↔ GHN `scheme_code + provider_key` —
  KHÔNG entity_id/region_id/city_id trong file; DB ids chỉ resolve bên trong importer.
- First install không cần GHN API: module ship `data/` (placeholder v0.0.0-empty cho tới khi
  dataset reviewed đầu tiên được export + commit lại).
- Provider sync/import master KHÔNG đụng bảng mapping; unit mất khỏi snapshot mới → DISABLED.
- Runtime resolver không đổi: stable code → APPROVED → stable GHN identity, fail closed.

## Quirk đã xử lý

- **HTTP 200 + body rỗng** = shop không tồn tại trên GHN → `ProviderRemoteException` (không bao giờ
  false-success — SPIKE-9Z231Q R11).
- Token/PII không bao giờ xuất hiện trong log (`GhnLogger::sanitizeContext`, unit-tested).

## Roadmap

| Phase | Task | Trạng thái |
|---|---|---|
| GHN-A skeleton | TASK-RJFTPZ | done (TL approve 2026-09-10) |
| GHN-B master data + mapping | TASK-MZ2TCB | in_progress |
| GHN-C rate (Magento carrier wiring) | TASK-FMBBSD | dev-complete 2026-09-14 — chờ TL review + QC L3 (available-services/leadtime giữ EXTERNAL/deferred) |
| GHN-D create (observer) | TASK-9Q5ZAK | dev-complete 2026-09-15 — CREATE only qua shipment observer + idempotent retry; cancel/return/MQ = GHN-E/F backlog |
| GHN-E1 webhook tracking (normalize) | TASK-GKHXY1 | dev-complete 2026-09-15 — webhook + mapper + reconciliation cron; getTracking/label = E3; cancel/return API = E2 |
| GHN-F cutover legacy | TASK-8019VC | proposed |

## Test

```bash
php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml --filter 'Secomm\\Ghn'
```
