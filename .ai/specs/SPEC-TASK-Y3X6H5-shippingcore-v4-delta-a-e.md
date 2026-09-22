# Task Spec: ShippingCore — architecture v4 delta A–E implementation (reopen-and-freeze)

Specification ID: SPEC-TASK-Y3X6H5

> Filename: `SPEC-TASK-Y3X6H5-shippingcore-v4-delta-a-e.md`. Reopen ShippingCore CHỈ cho 5 delta
> đã được amend trong address-shipping.md **Revision v3/v4** (per-operation capability,
> CanonicalResolutionSnapshot, failure_class, shift-left AMBIGUOUS seam, COD identification) —
> không audit lại toàn bộ module, không mở generic framework mới. **Freeze lại sau task.**

## Metadata

| Field | Value |
|-------|-------|
| Specification ID | SPEC-TASK-Y3X6H5 |
| Feature ID | FEAT-YA2C0W (parent — chuỗi ShippingCore) |
| Specification Level | FULL |
| Author | Claude (AI-assisted draft) — từ reopen directive; architecture basis = address-shipping.md **Revision v4** (§4.1/§5/§5.1/§6/§23/§28) |
| Status | **VALID** — contract shapes theo architecture v4 nguyên văn (§28 "Contract P1 đã chốt") |
| Date | 2026-09-11 |
| Related Ticket(s) | TASK-Y3X6H5 · TASK-STC3NB (COD — Delta E đã implement) · TASK-7AJ3K8 (handoffContext/candidates nền) · TASK-M3ME32 |
| Workflow Mode | A (shipping shared-contract) |

## 1. Objective

Implement các contract gap v3/v4 còn thiếu để ShippingCore có thể **freeze lại**:

```text
Delta A — address capability PER-OPERATION (RATE | CREATE) + representations (UNIT_ID | TEXT_NAME)
Delta B — CanonicalResolutionSnapshot contract (VO; persistence để task sau — §5.1 để DB cho implementation)
Delta C — failure_class semantics (NONE|AMBIGUOUS|UNMAPPED|TECHNICAL) — nằm trong Delta B contract
Delta D — external resolver seam AMBIGUOUS-only explicit (contract docblock; KHÔNG build provider)
Delta E — COD payment identification — ĐÃ IMPLEMENT (TASK-STC3NB): verify + report, không re-code
```

## 2. Audit result (delta matrix — code thật 2026-09-11)

| Delta | Before | Verdict |
|---|---|---|
| A | `CarrierAddressCapabilityInterface` per-carrier (`getRequiredScheme()`, `supportsTextualFallback()`; implementers `GhnAddressCapability`/`GhtkAddressCapability` ngoài ShippingCore; consumers `GhnRateCalculator`, `GhtkAddressAdapter`) | **PARTIAL** |
| B/C | Không có snapshot contract/persistence; resolution semantics đã preserve AMBIGUOUS/UNMAPPED (7AJ3K8 candidates passthrough) | **MISSING (contract)** |
| D | Seam `ExternalAddressResolverPool` + `ExternalAddressResolverInterface::resolve(context): ?string` — 0 invocation, selector semantics ngầm, CHƯA explicit AMBIGUOUS-only | **PARTIAL** (docblock-only delta) |
| E | `CodPaymentMethodResolverInterface` + config resolver + system.xml + DI (TASK-STC3NB, 8 tests) | **MATCH** |

Consumer impact (audit): `GhnRateCalculator`, `GhtkAddressAdapter` gọi old `handoff()/handoffContext()`
→ **BC-safe strategy**: old interface + old handoff methods GIỮ NGUYÊN (chỉ @deprecated docblock);
per-operation là CONTRACT MỚI song song — carriers adapt ở task kế tiếp (directive §11).

## 3. Scope — Delta A (per-operation, BC-safe)

### 3.1 `Api\Address\ShippingAddressOperation` + `Api\Address\AddressRepresentation`

Final constant classes (mirror `ShippingFailureReason`/`ShippingServiceLevel` precedent, không enum):

```text
ShippingAddressOperation: RATE, CREATE + all()/exists()/assertKnown (unknown → LocalizedException)
AddressRepresentation:   UNIT_ID, TEXT_NAME + all()/exists()
```

### 3.2 `Api\Address\CarrierOperationAddressCapabilityInterface` (MỚI — per-operation)

```php
getRequiredScheme(string $operation): string;              // AddressRepresentation scheme canonical từ VietNamAddress catalog
getSupportedRepresentations(string $operation): array;     // AddressRepresentation::* (UNIT_ID | TEXT_NAME)
supportsTextualFallback(string $operation): bool;
```

Provider-specific values (GHN district_id/ward_code/is_new_to_address, GHTK payload) vẫn
carrier-owned — ShippingCore chỉ own scheme + representation CATEGORY (§5/§6).
Old `CarrierAddressCapabilityInterface`: **@deprecated** (signature GIỮ NGUYÊN — carriers
compile/run tiếp; follow-up task chuyển sang per-op interface).

### 3.3 Handoff per-operation (BC-safe additions)

* `DestinationContextBuilderInterface` + impl: thêm
  `buildForOperation(Address, CarrierOperationAddressCapabilityInterface, string $operation)` —
  delegate nội bộ chung `doBuild(destination, targetScheme)` với targetScheme =
  `capability->getRequiredScheme($operation)`.
* `CarrierAddressHandoffServiceInterface` + impl: thêm
  `handoffForOperation(Address, CarrierOperationAddressCapabilityInterface, string $operation)` và
  `handoffContextForOperation(Context, CarrierOperationAddressCapabilityInterface, string $operation)` —
  resolve với scheme per-op, `textualFallbackEligible = supportsTextualFallback(operation)`,
  `supportedRepresentations` expose lên handoff. `assertKnown(operation)` (unknown →
  `LocalizedException`); prebuilt-context mismatch `context.targetScheme ≠ requiredScheme(op)`
  → `LogicException` (mirror E-SL2 mismatch pattern).
* `CarrierAddressHandoffInterface` + VO: thêm `getSupportedRepresentations(): array`
  (optional constructor param, default `[]` — BC với call sites hiện hữu).

### 3.4 Delta B — `Api\Address\CanonicalResolutionSnapshotInterface` + VO

```text
getStatus(): RESOLVED | UNRESOLVED
getFailureClass(): NONE | AMBIGUOUS | UNMAPPED | TECHNICAL   (RESOLVED ⇒ NONE; UNRESOLVED ⇒ 3 giá trị kia)
getSource(): LOCAL_MAPPING | EXTERNAL_RESOLVER
getCanonical2025Scheme()/getCanonical2025UnitCode()           (RESOLVED ⇒ non-empty)
getPre2025ProvinceUnitCode()/getPre2025DistrictUnitCode()/getPre2025WardUnitCode(): ?string
getProvenanceResolver(): ?string  (EXTERNAL_RESOLVER ⇒ non-null)
getProvenanceTimestamp(): ?string
getProvenanceMappingVersion(): ?string
```

VO immutable + fail-fast. **`resolved_pre2025` chỉ chứa Secomm canonical PRE-2025 unit_code —
provider IDs (GHN district_id/ward_code…) bị cấm tuyệt đối** (structural: VO không có field nào
cho provider values; comment cấm ghi thẳng). Persistence/DB = task sau (§5.1).

### 3.5 Delta D — docblock-only

`ExternalAddressResolverInterface`: explicit AMBIGUOUS-only (candidate set > 1), chỉ chọn
candidate đã biết hoặc null; KHÔNG resolve UNMAPPED, KHÔNG mint unit, KHÔNG ghi authoritative
edge. Không đổi signature, không build provider.

### 3.6 Delta E — VERIFY ONLY (đã implement TASK-STC3NB)

`Api\Cod\CodPaymentMethodResolverInterface` + `ConfiguredCodPaymentMethodResolver` + config +
system.xml + DI + 8 tests — verify regression, không re-code.

## 4. Out of scope

Carrier module changes (Ghn/Ghtk adapt = task kế tiếp) · snapshot persistence/DB · resolver
provider (VietMap/Google) · `GEOPOINT`/`GeocodeHandoff`/`CarrierType` · CANCEL/TRACK capability ·
COD framework · bất kỳ foundation contract khác (rate outcome/service-level/fallback — regression only).

## 5. Acceptance Criteria

* **AC-1**: RATE và CREATE capability cho phép scheme khác nhau + representations khác nhau
  (test qua handoffForOperation với stub capability; không GHN provider IDs trong test).
* **AC-2**: Snapshot VO: canonical identities only (không field provider values); AMBIGUOUS/
  UNMAPPED/TECHNICAL preserved (không collapse); RESOLVED ⇒ failure NONE + canonical non-empty;
  EXTERNAL source ⇒ provenance resolver; impossible combos → `LogicException`.
* **AC-3**: Per-op handoff: unknown operation → `LocalizedException`; prebuilt-context mismatch
  → `LogicException`; old handoff/handoffContext behavior KHÔNG đổi (regression).
* **AC-4**: Old capability interface chỉ thêm @deprecated docblock — 0 signature change
  (carriers compile + run tiếp).
* **AC-5**: Delta D docblock AMBIGUOUS-only; 0 resolver invocation; Delta E regression pass.
* **AC-6**: compile + validator 0 new finding + phpunit pass; README/CHANGELOG + working memory
  sync; freeze conclusion ghi rõ consumer follow-up.

## 6. Test plan (unit, AAA)

`OperationAddressCapabilityTest` (stub per-op capability + handoffForOperation): RATE→PRE_2025 +
[UNIT_ID] vs CREATE→2025 + [TEXT_NAME] (scheme/representation khác nhau per op) · textual
fallback per-op · unknown operation → exception · context mismatch → exception · representations
expose lên handoff · old handoff regression không đổi. `CanonicalResolutionSnapshotTest`:
RESOLVED happy · UNRESOLVED + 3 failure classes · EXTERNAL provenance resolver bắt buộc ·
impossible combos · constants stability. Regression: full ShippingCore suite.
