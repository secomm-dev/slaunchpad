# Implementation Plan — TASK-BYT2WK (GHN production wiring + runtime proof)

| Specification | SPEC-FEAT-QA23PZ (../../records/specs/SPEC-FEAT-QA23PZ-shippingcore-canonical-zone-admin.md) — Full |
|---|---|
| **Work item** | TASK-BYT2WK — slice C của FEAT-QA23PZ |
| **Mode** | A — shipping runtime + bridge change (Tier-2, chờ TL approve) |
| **Depends** | TASK-1EK2MW (reader + reason constant); admin fields độc lập TASK-ZA10BT |
| **Risk** | medium — chạm production rate path GHN + Launchpad bridge |

## Steps

1. **GhnConfig accessors** — `getDestinationScope(?storeId)` / `getAllowedZoneCodes(?storeId)`
   delegate shared reader (`Api\Config\CarrierDestinationScopeConfigInterface`), mirror pattern
   `getRateSourceMode` (enum fail-closed).
2. **system.xml GHN** — group `secomm_ghn` += `destination_scope` (select inline options
   ALL/SELECTED_ZONES, sortOrder sau `address_resolution_policy`) + `allowed_zone_codes`
   (multiselect, `Model\Config\Source\AllowedZoneCodes` đọc repository enabled zones;
   `<depends><field id="destination_scope">SELECTED_ZONES</field></depends>` +
   backend_model validate ≥1 khi SELECTED_ZONES; comment "zone bị disable/xoá → không match").
   `config.xml` default `destination_scope = ALL`, `allowed_zone_codes` rỗng.
3. **GHN wiring** — `Ghn::__construct` += `CarrierRateExecutionServiceInterface`,
   `CarrierRateExecutionRequestInterface` (factory-style nếu cần — kiểm tra: request là final
   với 11 ctor args ⇒ tạo qua DI-injected instance hoặc factory nhỏ `GhnExecutionRequestFactory`),
   `RealtimeRateContributorFactory`, `RuntimeAddressContextBuilderInterface`,
   `VnOperationalAddressResolverInterface`, `ShippingContextFactory`, shared reader.
   `Ghn::collect()` per SPEC §6.1. Docblock `calculate()` += "standalone path — carrier entry
   đi qua CarrierRateExecutionService".
4. **Canonical scalars** — trong collect: `resolveFromRuntime(regionId, cityId)` → identity
   `getRegionCode()`/`getUnitCode()` (unresolved → `''`/`null`); KHÔNG resolve lại trong
   contributor.
5. **FallbackCoordinator guard** — `isMemberEligible()`: `if ($outcome->getFailureReason() ===
   ShippingFailureReason::DESTINATION_NOT_IN_SCOPE) return false;` TRƯỚC mode branches +
   docblock §20.
6. **Tests GHN** — `Test/Unit/Model/Carrier/GhnZoneExecutionTest` (hand-composed real chain:
   real `CarrierRateExecutionService` + evaluator + `CanonicalZoneRegistry` fixture zones +
   real `RuntimeAddressContextBuilder` với mocked resolvers + contributor thật với mock API
   client đếm call; carrier parent deps mock):
   - ALL + 0 zones → contributor/API ≥1 call, outcome success → method price.
   - SELECTED_ZONES + matched zone → API call.
   - SELECTED_ZONES + miss → 0 contributor + 0 API + collector
     `unavailable(DESTINATION_NOT_IN_SCOPE)` + `hide()`.
   - SELECTED_ZONES + 0 configured → ineligible + warning diagnostic.
   - FALLBACK_ONLY + eligible → record skip outcome (fallback do coordinator mở — case guard).
   - FALLBACK_ONLY + miss → outcome `DESTINATION_NOT_IN_SCOPE`.
7. **Tests bridge** — `Launchpad_MageplazaTableRate/Test/Unit/Model/FallbackCoordinatorTest`
   += case: FALLBACK_ONLY member + `DESTINATION_NOT_IN_SCOPE` → không append fallback;
   CARRIER_WITH_FALLBACK + zone-miss → không append (policy fail-closed đã có).
8. **Docs** — `Secomm/ShippingCore/docs/USER_GUIDE.md` (§35 SPEC list) +
   `Secomm/Ghn/README.md` (scope config) + architecture `address-shipping.md` §35 addendum
   (persistent registry + GHN production adoption + config paths — operational completion v10).

## Validation

Suites: `--filter "ShippingCore|Ghn|Ghtk|MageplazaTableRate|VietNamAddress"` (regression §38);
`setup:di:compile`; `bin/project-ai-validate --check-specs`; grep gates §39 (0 provider-ID /
localized-text zone matching trong carriers; 0 TableRate→ShippingCore zone dep).
Runtime smoke quote thật (dev store) khi origin/config sẵn — BLOCKED_BY_ENVIRONMENT được ghi
nhận như TASK-8MQHJX nếu sandbox thiếu.

## Risks

- Chạm production rate path: mitigated bởi tail `quoteWithHandoff` giữ nguyên + test matrix
  ALL-path parity + full-suite regression.
- `AbstractCarrierOnline` ctor deps lớn trong test — mock thủ công như ShippingCore flow test
  (không ObjectManager).
- Store scoping: `getAddressResolutionPolicy(null)` hiện tại trong calculator — wiring mới
  truyền store đúng qua accessor (cải thiện, ghi nhận trong evidence).
