# Implementation Plan: TASK-FMBBSD — GHN-C slice 2: Magento Carrier RATE wiring trên ShippingCore v5

## Metadata

| Field | Value |
|-------|-------|
| Task | TASK-FMBBSD (parent FEAT-FQWEQ3) — GHN-C |
| Mode | A (checkout shipping path → Tier-2) |
| Specification | [specs/SPEC-FEAT-FQWEQ3-secomm-ghn-carrier-adapter.md](../specs/SPEC-FEAT-FQWEQ3-secomm-ghn-carrier-adapter.md) — FULL, VALID (§12 Rate, §13 No Magic Fallback, §19 method identity, §43 AC-RATE) |
| Decision | [DEC-FEATFQWEQ3-001](../records/decisions/DEC-FEATFQWEQ3-001.md) (dual-scheme per-op: RATE=PRE_2025 IDs, CREATE=2025 names) · DEC-FEATYA2C0W-004 |
| Reuses | `GhnRateCalculator` (slice 1, outcome-based) · `GhnRateQuery`/`GhnParcel` · `GhnMappingResolver` (Stage-2 APPROVED-only) · `GhnApiClient` + taxonomy `Provider*Exception` · `GhnLogger`/`MappingCache` |
| Scope | Production: `app/code/Secomm/Ghn/**` · Read-only: `Secomm_ShippingCore` (v5 RE-FROZEN), `Secomm_VietNamAddress` · **0 Magento_Shipping vendor edit, 0 schema change** |
| Out of scope | CREATE/Cancel/Return/Label/Tracking/Webhook (GHN-D/E) · available-services + leadtime (DEFERRED) · fallback orchestration (E-SL composition) · snapshot persistence · currency conversion · multi-origin routing |
| Note | Slice 1 chạy thiếu plan artifact (gap REPORT TL). Plan này che slice 2 (carrier wiring); slice 1 đã dev-complete + evidence riêng tại `.ai/evidence/TASK-FMBBSD/` |

## Approach

Wire Magento carrier thật cho `GhnRateCalculator` + migrate `GhnAddressCapability` sang
per-operation capability (ShippingCore v5, TASK-Y3X6H5). Carrier `Model/Carrier/Ghn` (extends
`AbstractCarrierOnline`, `$_code = 'secomm_ghn'`) là thin adapter: active gate → VN/currency guard
→ `GhnRateRequestMapper` (RateRequest → `GhnRateQuery`, weight gram theo `general/locale/weight_unit`,
dims > 0 giả định cm, không COD inference) → `GhnRateCalculator::calculate()` → `CarrierRateOutcome`
→ `RateResult\Method` | no method. Per-op path: `handoffContextForOperation(context, capability, RATE)`
với context build qua bridge module-private `GhnRateCapabilityAdapter` (ShippingCore chưa có public
per-op scalar builder — `OperationCapabilityAdapter` là @internal; gap REPORT TL). Heavy-parcel
(≥20kg → service_type 5) → UNAVAILABLE `GHN_HEAVY_PARCEL_UNSUPPORTED` TRƯỚC khi gọi API (sandbox:
type-5 fee cần items — cấm gửi invalid request, cấm downgrade). Override `processAdditionalValidation()`
(vendor trap: thiếu `max_package_weight` config → mọi item weight>0 set errorMsg → carrier bị ẩn
silent) + `isTrackingAvailable`/`isShippingLabelsAvailable` = false (parent default true, sai cho
rate-only). Method identity ổn định: carrier = method = `secomm_ghn` (không service_id/type/district).
Currency: VND-only slice (base ≠ VND → hide + warning). Service level: declaration-only
`['STANDARD']` qua `CarrierServiceLevelInterface`, không wire registry (composition = Launchpad).

## Files affected

| File | Change type | Lý do |
|------|-------------|-------|
| `Model/Capability/GhnAddressCapability.php` | modify | migrate → `CarrierOperationAddressCapabilityInterface` (RATE=PRE_2025+[UNIT_ID], CREATE=2025+[TEXT_NAME], fallback false) |
| `Model/Capability/GhnRateCapabilityAdapter.php` | new | module-private legacy-shape bridge pin RATE (cho `RuntimeAddressContextBuilder::build`) |
| `Model/Rate/GhnRateCalculator.php` | modify | `handoffContextForOperation` + heavy-parcel guard + representations docblock |
| `Model/Carrier/Ghn.php` | new | Magento carrier (collectRates → outcome → RateResult; processAdditionalValidation/isTracking/labels override; `_doShipmentRequest` throw) |
| `Model/Rate/GhnRateRequestMapper.php` | new | RateRequest → GhnRateQuery (weight unit kgs/lbs→gram, dims, no COD) |
| `Model/ServiceLevel/GhnServiceLevel.php` | new | `CarrierServiceLevelInterface` declaration `['STANDARD']` |
| `Model/Logger/GhnLogger.php` | modify | += `warning()`/`error()` (sanitize giữ nguyên) |
| `Model/Config.php` | modify | xóa `XML_PATH_ENABLED`/`isEnabled()` → carrier dùng `active` chuẩn |
| `etc/config.xml` + `etc/adminhtml/system.xml` | modify | += `model`/`active`/`title`/`name`/`sort_order`/`showmethod`/`sallowspecific`/`specificcountry`/`specificerrmsg`; xóa `enabled` |
| `etc/module.xml` | modify | sequence += Magento_Backend/Config/Directory/Shipping |
| `i18n/en_US.csv` + `i18n/vi_VN.csv` | modify | method title + specificerrmsg (BR-001) |
| `Test/Unit/**` | new/modify | capability matrix, calculator per-op + heavy guard, mapper, carrier (7 suite — xem plan session) |

## Verification

Scoped phpunit (`--filter 'Secomm\\Ghn'`) 0F/0E/0S · `setup:di:compile` · `--check-specs` · grep gates
(Mageplaza/TableRate/VietMap/Fallback/GiaoHangNhanh/Ghtk = 0 code hit; `service_id` = 0 runtime).
Manual smoke checkout chờ TL/QC (Tier-2).

## AC (slice)

1. Địa chỉ có mapping APPROVED → method `secomm_ghn` giá = GHN `total`; AMBIGUOUS/UNMAPPED/mapping-missing → ẩn, không exception checkout.
2. Timeout/5xx/malformed → ẩn + log TECHNICAL; auth/config → UNAVAILABLE; không fake price.
3. ≥20kg → ẩn + 0 GHN HTTP call.
4. Unit green + compile + validator; QC L3 chờ TL.
