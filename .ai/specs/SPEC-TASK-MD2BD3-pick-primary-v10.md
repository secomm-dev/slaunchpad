# Task Spec: ShippingCore/VietNamAddress — v10 PICK_PRIMARY (directional curated primary + deterministic selector)

Specification ID: SPEC-TASK-MD2BD3

> Filename: `SPEC-TASK-MD2BD3-pick-primary-v10.md`. Implement architecture **Revision v10 §35.4**
> (DEC-FEATYA2C0W-006 amendment 2026-09-18): `AddressResolutionPolicy::PICK_PRIMARY` promoted vào
> P1 — chỉ applicable cho carrier RATE yêu cầu legacy scheme. Consumer thật: GHN-D (legacy RATE).

## Metadata

| Field | Value |
|-------|-------|
| Specification ID | SPEC-TASK-MD2BD3 |
| Feature ID | FEAT-YA2C0W (parent — chuỗi ShippingCore) |
| Specification Level | FULL |
| Author | Claude (AI-assisted draft) — từ implementation directive v10 + audit mapping schema/data thật |
| Status | **VALID** — selection semantics theo directive §1/§2/§10 nguyên văn |
| Date | 2026-09-16 |
| Related Ticket(s) | TASK-MD2BD3 · TASK-Y3X6H5 (snapshot) · TASK-M3ME32 (E-SL2) · TASK-NQT782 (bridge consumer) |
| Workflow Mode | A (canonical mapping data + shipping shared-contract) |

## 1. Objective

Implement directional curated primary designation + deterministic selector trong
`Secomm_VietNamAddress`, và consume qua `AddressResolutionPolicy::PICK_PRIMARY` trong
`Secomm_ShippingCore` handoff per-operation. Không đụng GHN/GHTK/bridge.

## 2. Audit (code thật 2026-09-16)

* `secomm_vietnam_address_mapping`: 6 cột (mapping_id, source_scheme, source_code, target_scheme,
  target_code, relation_type) — **không có primary/rank metadata** (gap thật).
* `MappingCandidateFinder::find()` trả union outgoing+incoming rows có `direction` field (đã có)
  nhưng KHÔNG expose `is_primary` (cột chưa tồn tại).
* `VnAdminAddressResolver`: candidates `array_unique` + `sort()` alphabetical — chỉ deterministic
  display, KHÔNG phải priority.
* `VnMappingReader`: strict header 5 cột — cần hỗ trợ thêm header v1.1 (6 cột + `is_primary`)
  backward-safe.
* `VnMappingImporter::import()`: `insertOnDuplicate` update field chỉ `relation_type`.
* `VnMappingValidator`: validate schemes/relation/identity — CHƯA validate primary.

## 3. Scope

### 3.1 VietNamAddress — directional curated primary

* `etc/db_schema.xml`: cột `is_primary` (boolean, default false, not null) trên
  `secomm_vietnam_address_mapping` + whitelist. Meaningful chỉ theo hướng lưu trong row
  (source_scheme → target_scheme) — reverse traversal KHÔNG kế thừa (directive §1.1).
* `VnMappingReader`: chấp nhận header legacy (5 cột) và v1.1 (6 cột `is_primary`); legacy →
  is_primary = '0'.
* `VnMappingValidator`: `is_primary` chỉ nhận '0'/'1'/'' (giá trị khác → error); validate
  **duplicate curated primary per (source_scheme, source_code, target_scheme) → error**
  (integrity defect — directive §6/§7).
* `VnMappingImporter`: batch map + insert/update `is_primary` (normalize: '1'/'true' → 1, else 0 —
  KHÔNG infer từ alphabetical/db-order).

### 3.2 `Api\VnPrimaryCandidateSelectorInterface` + `Model\VnPrimaryCandidateSelector`

```php
public function selectPrimary(
    string $sourceScheme,
    string $sourceCode,
    string $targetScheme,
    array $candidateCodes
): VnPrimaryCandidateSelectionInterface;
```

* Query directional (source_scheme + source_code + target_scheme + `is_primary = 1`) trên
  candidate set.
* `candidateCount < 2` → `NOT_APPLICABLE` (selector normally unnecessary).
* Đúng 1 primary trong candidates → `SELECTED` (selectedCandidateCode).
* 0 primary → `NO_DESIGNATED_PRIMARY`. >1 → `MULTIPLE_PRIMARY` (integrity defect — fail, không
  pick, không tie-break alphabetically).
* Candidate order KHÔNG ảnh hưởng selection (sort-independent — query theo designation).
* Ownership: `Secomm_VietNamAddress` (mapping/data interpretation — directive §9).

### 3.3 `Api\Data\VnPrimaryCandidateSelectionInterface` + `Model\Data\VnPrimaryCandidateSelection`

```text
getStatus(): SELECTED | NO_DESIGNATED_PRIMARY | MULTIPLE_PRIMARY | NOT_APPLICABLE
getSelectedCode(): ?string
getCandidateCount(): int
```

### 3.4 ShippingCore — `Api\Address\AddressResolutionPolicy` (constants)

```text
STRICT | FALLBACK | PICK_PRIMARY + all()/exists()/assertKnown (unknown → LocalizedException)
```

### 3.5 ShippingCore handoff per-operation — policy integration (BC-safe)

`CarrierAddressHandoffServiceInterface::handoffForOperation(destination, capability, operation)`
thêm optional param cuối: `string $addressResolutionPolicy = AddressResolutionPolicy::FALLBACK`.

Flow AMBIGUOUS + PICK_PRIMARY:

```text
selector.selectPrimary(sourceScheme, sourceUnitCode, targetScheme, candidateCodes)
├── SELECTED → re-resolve qua resolution manager (same-scheme EXACT trên selected candidate)
│              → handoff RESOLVED (selected candidate là resolved identity; ambiguity origin
│                giữ trong provenance — không rewrite history)
├── NO_DESIGNATED_PRIMARY / MULTIPLE_PRIMARY → handoff unresolved như AMBIGUOUS thường
└── fallback eligibility vẫn theo capability (§13 directive: CARRIER_ONLY → no fallback;
    CARRIER_WITH_FALLBACK → fallback eligible)
```

`handoffContextForOperation` cũng nhận optional policy param (BC-safe). STRICT/FALLBACK behavior
giữ nguyên 1:1. `FALLBACK_ONLY` (RateSourceMode) là carrier-flow concern — handoff không evaluate.

### 3.6 Snapshot provenance (ShippingCore)

`CanonicalResolutionSnapshotInterface` + VO thêm **optional** provenance:

```text
getSelectionPolicy(): ?string     // 'PICK_PRIMARY' khi selector được áp dụng
getSelectionReason(): ?string     // 'CURATED_PRIMARY' | 'NO_DESIGNATED_PRIMARY' | …
getCandidateCount(): ?int
```

Constructor optional params cuối (BC). Selected candidate KHÔNG lưu trùng — đã là
`resolved_pre2025` ward code. Persistence vẫn DEFER (contract-level ready).

## 4. Out of scope

GHN/GHTK/bridge changes · external resolver providers · zones/CarrierEligibility contracts ·
snapshot persistence/DB tables mới · rank metadata (defer tới khi curation yêu cầu nhiều mức) ·
PICK_PRIMARY admin UI (carrier task) · mass dataset curation (chỉ schema + selector + validation;
curated data supply sau).

## 5. Acceptance Criteria

* **AC-1**: `is_primary` boolean column + whitelist; importer đọc cả 2 header, legacy → default 0;
  duplicate primary per directional key → validation error.
* **AC-2**: Selector: candidateCount < 2 → NOT_APPLICABLE; đúng 1 directional curated primary →
  SELECTED; 0 → NO_DESIGNATED_PRIMARY; >1 → MULTIPLE_PRIMARY; **candidate order shuffle không đổi
  kết quả**; reverse-direction primary KHÔNG được pick.
* **AC-3**: `AddressResolutionPolicy` STRICT/FALLBACK/PICK_PRIMARY; handoffForOperation + policy:
  PICK_PRIMARY + SELECTED → resolved handoff (selected candidate); NO_DESIGNATED/MULTIPLE →
  unresolved AMBIGUOUS; STRICT/FALLBACK behavior 1:1 (regression).
* **AC-4**: Snapshot provenance +3 optional getters; không provider IDs; không duplicate selected
  identity.
* **AC-5**: compile + phpunit scoped pass (0 new fail) + validator 0 new finding; 0 carrier/
  bridge/vendor edit.
* **AC-6**: README/CHANGELOG (VietNamAddress + ShippingCore) + CURRENT_STATE sync.

## 6. Test plan

VietNamAddress: `VnPrimaryCandidateSelectorTest` (mock ResourceConnection/Select chain: 1 primary
→ SELECTED · 0 → NO_DESIGNATED · 2 → MULTIPLE · <2 → NOT_APPLICABLE · order shuffle invariant) ·
`VnMappingValidatorTest` additions (is_primary normalize + duplicate-primary error) ·
`VnMappingReaderTest` additions (legacy 5-cột + v1.1 6-cột header). ShippingCore:
`CarrierAddressHandoffServiceTest` additions (PICK_PRIMARY SELECTED → resolved handoff với
selected code; NO_DESIGNATED → unresolved; MULTIPLE → unresolved) + `CanonicalResolutionSnapshotTest`
additions (provenance optional fields) + `ConfiguredCodPaymentMethodResolverTest`… (không đổi).

## 7. Risks & rollback

| Risk | Mitigation |
|------|-----------|
| Import CSV cũ break | Reader chấp nhận cả 2 header; is_primary default 0 |
| Duplicate primary trong dataset hiện có | Import validation fail-loud + selector fail-closed (không pick mù) |
| Selector query chậm | WHERE theo (source_scheme, source_code, target_scheme) + is_primary — index hiện có (SOURCE/TARGET) phủ |

Rollback: revert — is_primary column additive (drop an toàn), 0 data migration bắt buộc.
