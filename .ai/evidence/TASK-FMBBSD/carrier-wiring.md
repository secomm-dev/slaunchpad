# Evidence — TASK-FMBBSD slice 2: Magento Carrier RATE wiring (GHN-C)

> Date: 2026-09-14 (r2 = TL-review fixes) · Scope: `app/code/Secomm/Ghn/**` only (ShippingCore v5 / VietNamAddress READ-ONLY)

## 0. TL review r2 (2026-09-14) — approved-with-fixes, tất cả đã xử lý

| Verdict TL | Xử lý |
|---|---|
| APPROVED: carrier / per-op handoff / Stage-2 / outcome / no-fallback / heavy fail-closed | — |
| MUST VERIFY `processAdditionalValidation` | Đọc TOÀN BỘ vendor method (`AbstractCarrierOnline.php:314-372`): ngoài weight trap còn **zip-code gate** + decimal-weight expansion + showmethod rendering → viết lại **parent-parity trừ trap** (max_package_weight chỉ enforce khi cấu hình; zip gate GIỮ NGUYÊN; rendering giữ). 3 test khóa behavior |
| MUST FIX missing weight-unit → kg | **FAIL-CLOSED**: `resolveWeightUnit()` throws `LocalizedException` → carrier catch → warning log UNAVAILABLE/`INVALID_CONFIGURATION` → no method. Không còn assume-kgs |
| MUST FIX dimension "assume cm" | **OMIT hoàn toàn** dims ở RATE (không unit contract upstream). Chỉ gửi lại khi có parcel contract unit-aware |
| MUST REVIEW hardcoded `['STANDARD']` | **`GhnServiceLevel` REMOVED** (declaration-only, 0 consumer — không để STANDARD thành intrinsic contract); service-level = config-driven/upstream mapping tại composition (E-SL) |
| Governance VND-only | Accepted cho release VN-scope (documented); lâu dài = conversion boundary |
| Governance `enabled`→`active` | Manual re-enable chấp nhận được (0.3.x chưa từng commit = 0 production adoption); nếu có adopt → data-patch nhẹ |
| Backlog type-5 | Ghi rõ: GHN type-5 RATE support (item payload) = backlog, không phải limitation vĩnh viễn |

## 1. Deliverables

| File | Type |
|---|---|
| `app/code/Secomm/Ghn/Model/Carrier/Ghn.php` | new — Magento carrier (`AbstractCarrierOnline`, `$_code='secomm_ghn'`) + parent-parity validation + `INVALID_CONFIGURATION` path |
| `app/code/Secomm/Ghn/Model/Rate/GhnRateRequestMapper.php` | new — RateRequest → `GhnRateQuery` (weight-unit normalize + fail-closed, no dims, no COD) |
| `app/code/Secomm/Ghn/Model/Capability/GhnRateCapabilityAdapter.php` | new — module-private legacy-shape bridge pin RATE |
| `app/code/Secomm/Ghn/Model/Capability/GhnAddressCapability.php` | modified — per-op `CarrierOperationAddressCapabilityInterface` |
| `app/code/Secomm/Ghn/Model/Rate/GhnRateCalculator.php` | modified — `handoffContextForOperation` + heavy guard `GHN_HEAVY_PARCEL_UNSUPPORTED` |
| `app/code/Secomm/Ghn/Model/Logger/GhnLogger.php` | modified — += `warning()`/`error()` |
| `app/code/Secomm/Ghn/Model/Config.php` | modified — xóa `XML_PATH_ENABLED`/`isEnabled()` |
| `etc/config.xml` / `etc/adminhtml/system.xml` | modified — `active` thay `enabled` + display fields chuẩn |
| `etc/module.xml` | modified — sequence += Magento_Backend/Config/Directory/Shipping |
| `i18n/en_US.csv` / `i18n/vi_VN.csv` | modified — method title + errmsg + log strings (BR-001) |
| Tests | 3 modified + 3 new suites (`GhnTest`, `GhnRateRequestMapperTest`, `GhnRateCapabilityAdapterTest`) |

## 2. Unit tests (scoped)

Command: `php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml --filter 'Secomm\\Ghn'`

```text
OK, but there were issues!
Tests: 174, Assertions: 210438, PHPUnit Deprecations: 6
```

0 Failures / 0 Errors / 0 Skipped. (6 deprecations = baseline pre-existing.) Key coverage r2:
validation parity ×3 (unconfigured-max không chặn / configured-max enforce / zip-gate giữ),
INVALID_CONFIGURATION ×1, mapper fail-closed ×2, dims-omitted ×1.

## 3. Compile

```text
$ php bin/magento setup:di:compile
Generated code and dependency injection configuration successfully.  (exit 0)  [r2 re-run sau khi đổi mapper ctor]
```

## 4. Full Secomm suite (regression)

```text
Tests: 1477, Assertions: 214910, Errors: 8, Failures: 2, PHPUnit Deprecations: 6
```

Toàn bộ 8 errors = `Secomm_Tracking` (baseline pre-existing), 2 failures = `Secomm_FulfillmentCore`
(stream khác). **0 lỗi ở Secomm_Ghn / ShippingCore / VietNamAddress.**

## 5. Grep gates (independence §9/§10)

```text
runtime PHP (exclude Test/ + docblock prose): Mageplaza|TableRate|FallbackRateProvider|
  Launchpad_|VietMap|Google|GeoIP|GiaoHangNhanh|GhnAddressMapper|Ghtk  → 0 hits
use-imports of forbidden modules                    → 0
service_id trong runtime payload/identity           → 0 (chỉ service_type_id)
ObjectManager::getInstance                          → 0
```

## 6. Validator (spec-first gate)

```text
$ bash .ai/bin/project-ai-validate --check-specs
result (project): 21 FAIL, 0 WARN   ← toàn bộ pre-existing (records/specs/plans stream khác),
                                      0 finding cho TASK-FMBBSD
```

Plan artifact đã bổ sung: `.ai/plans/TASK-FMBBSD-implementation-plan.md` (slice 1 chạy thiếu plan —
gap REPORT TL).

## 7. Pending / backlog (ngoài scope slice)

- QC L3 checkout trên store thật (token/shop_id sandbox + dataset v1.0.0 import) — Tier-2, chờ TL.
  Lưu ý QC: zip-gate parent-parity — cart thiếu postcode ở country zip-required sẽ ẩn GHN (chuẩn
  Magento); verify flow postcode của Launchpad checkout.
- **Backlog: GHN type-5 RATE support** — item payload cho heavy parcel; limitation của slice
  (fail-closed `GHN_HEAVY_PARCEL_UNSUPPORTED`), không phải limitation vĩnh viễn.
- ShippingCore gap: public per-op scalar context builder (hiện shim `GhnRateCapabilityAdapter`).
- Available Services + leadtime: EXTERNAL/deferred (docs vs sandbox conflict chưa giải).
- Service-level mapping: config-driven/upstream mapping tại composition task (E-SL) — không thuộc
  carrier (TL r2).
- Lâu dài: VND conversion boundary nếu muốn reuse cho store base ≠ VND (TL governance).
