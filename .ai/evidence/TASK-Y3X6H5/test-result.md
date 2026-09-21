# Evidence — TASK-Y3X6H5 (ShippingCore v4 delta A–E — reopen-and-freeze)

Ngày: 2026-09-11 · Mode A · pre-review evidence cho TL review.

## Delta matrix (Before → Change → After)

| Delta | Before | Change | After |
|---|---|---|---|
| **A. per-operation capability** | Interface per-carrier (`getRequiredScheme()`, `supportsTextualFallback()`; không op, không representations) | Thêm `ShippingAddressOperation` (RATE/CREATE) + `AddressRepresentation` (UNIT_ID/TEXT_NAME) + `CarrierOperationAddressCapabilityInterface` (per-op) + `buildForOperation`/`handoffForOperation`/`handoffContextForOperation` + handoff `getSupportedRepresentations()`; old interface @deprecated docblock-only (signature unchanged) | **PARTIAL → IMPLEMENTED** (per-op path hoàn chỉnh; carrier migration = task kế tiếp) |
| **B. CanonicalResolutionSnapshot** | 0 contract/persistence | `CanonicalResolutionSnapshotInterface` + immutable VO (canonical 2025 identity + PRE-2025 province/district/ward unit codes + status + failure_class + source + provenance; fail-fast invariants). Persistence DEFER (§5.1 để DB cho implementation) | **MISSING → CONTRACT IMPLEMENTED** (persistence deferred) |
| **C. failure_class** | Resolution semantics preserve AMBIGUOUS/UNMAPPED (7AJ3K8) nhưng chưa có failure_class nào | Constants NONE/AMBIGUOUS/UNMAPPED/TECHNICAL trong snapshot contract; UNRESOLVED cấm NONE; RESOLVED bắt buộc NONE | **MISSING → IMPLEMENTED** (trong Delta B) |
| **D. shift-left AMBIGUOUS seam** | Pool + resolver contract selector-ngầm, chưa explicit AMBIGUOUS-only | Docblock explicit: AMBIGUOUS-only selector / không UNMAPPED / không mint / không authoritative edge / không gọi trong RATE fan-out. 0 signature change, 0 provider built, 0 invocation | **PARTIAL → MATCH** (seam đúng + boundary explicit) |
| **E. COD identification** | `CodPaymentMethodResolverInterface` + `ConfiguredCodPaymentMethodResolver` + config + system.xml + DI + 8 tests (TASK-STC3NB) | Verify-only — regression pass, không re-code | **MATCH** |

## Test result (unit)

```text
$ php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml --filter "OperationAddressCapability"
OK — 6 tests, 12 assertions, 0 failure/error

$ php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml --filter "CanonicalResolutionSnapshot"
OK — 8 tests, ~23 assertions, 0 failure/error

$ php vendor/bin/phpunit -c dev/tests/unit/phpunit-secomm.xml            # full suite
Tests: 1249, Assertions: 214173, Errors: 7
  → CẢ 7 errors = Secomm\Tracking baseline pre-existing (đã ghi từ các evidence trước; không đổi).

$ php bin/magento setup:di:compile
Generated code and dependency injection configuration successfully.
```

Test mới:

- `OperationAddressCapabilityTest` (6 — real DestinationContextBuilder + RuntimeAddressContextBuilder):
  RATE vs CREATE target schemes khác nhau (PRE_2025 vs 2025 capture qua manager mock) ·
  representations theo op ([UNIT_ID] vs [TEXT_NAME]) · textual fallback eligibility per-op trên
  AMBIGUOUS unresolved (RATE true / CREATE false, resolvedAddress null) · unknown op
  (`CANCEL`) → `LocalizedException` · context/capability scheme mismatch → `LogicException` ·
  legacy `handoff()` regression 1:1 (representations rỗng trên legacy path).
- `CanonicalResolutionSnapshotTest` (8): RESOLVED shape (canonical + PRE-2025 codes + provenance) ·
  UNRESOLVED ×3 failure classes preserved (không collapse) · EXTERNAL source bắt buộc provenance
  resolver · impossible combos (RESOLVED có failure class / thiếu canonical identity;
  UNRESOLVED với NONE; unknown status/source).

## Verification greps

```text
$ grep GEOPOINT|GeocodeHandoff|CarrierType (code lines, Api/Model) → 0 hit
$ grep getFailureReason trong Model/ServiceLevel → 0 hit (status authoritative giữ nguyên)
$ git status Ghn/Ghtk → CHỈ pre-existing edits của stream khác (0 edit bởi task này)
```

## Command output (build + validator)

```text
$ php bin/magento setup:di:compile
Generated code and dependency injection configuration successfully.

$ .ai/bin/project-ai-validate --check-specs --check-records --check-identity
result (project): 29 FAIL, 0 WARN — 0 finding TASK-Y3X6H5 (grep = 0). 15 baseline + BUG records
+ GHTK/GHN-B2 records của các stream song song.
```

## Files changed

- MỚI: `Api/Address/{ShippingAddressOperation, AddressRepresentation, CarrierOperationAddressCapabilityInterface, CanonicalResolutionSnapshotInterface}.php`,
  `Model/Address/{OperationCapabilityAdapter, CanonicalResolutionSnapshot}.php`,
  `Test/Unit/Model/Address/{OperationAddressCapabilityTest, CanonicalResolutionSnapshotTest}.php`
- UPDATE: `Api/Address/{CarrierAddressCapabilityInterface (@deprecated docblock-only),
  CarrierAddressHandoffServiceInterface (+2 methods), CarrierAddressHandoffInterface (+1 method),
  DestinationContextBuilderInterface (+1 method @deprecated build)}`,
  `Model/Address/{CarrierAddressHandoffService (+2 impl), CarrierAddressHandoff (+param+getter),
  DestinationContextBuilder (+buildForOperation)}`,
  `README.md` + `CHANGELOG.md` (0.14.0)
- Governance: SPEC + plan + TASK record + FEAT ticket_ref + CURRENT_STATE

0 `Secomm_Ghn` / `Secomm_Ghtk` / Mageplaza / vendor code thay đổi bởi task này.
