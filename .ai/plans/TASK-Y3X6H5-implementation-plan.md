# Implementation Plan: TASK-Y3X6H5 — ShippingCore v4 delta A–E (reopen-and-freeze)

## Metadata

| Field | Value |
|-------|-------|
| Task | TASK-Y3X6H5 (parent FEAT-YA2C0W) |
| Mode | A (shipping shared-contract) |
| Specification | [specs/SPEC-TASK-Y3X6H5-shippingcore-v4-delta-a-e.md](../specs/SPEC-TASK-Y3X6H5-shippingcore-v4-delta-a-e.md) — FULL, VALID (architecture basis: address-shipping.md **Revision v4** §4.1/§5/§5.1/§6/§23/§28 — "Contract P1 đã chốt") |
| Decision | [DEC-FEATYA2C0W-004](../records/decisions/DEC-FEATYA2C0W-004.md); không DEC mới — 5 delta đã được amend trong architecture v3/v4; BC-safe strategy (old contracts @deprecated, per-op contract mới song song) giữ carriers compile/run |
| Audit basis | Delta matrix 2026-09-11: A=PARTIAL, B/C=MISSING(contract), D=PARTIAL(docblock), E=MATCH (TASK-STC3NB) |
| Risk | Medium — thêm contract mới song song + BC-safe additions trên handoff/builder; old call sites (GhnRateCalculator, GhtkAddressAdapter) không đổi → 0 runtime break |

## Approach

BC-safe reopen: (A) `ShippingAddressOperation` + `AddressRepresentation` constants +
`CarrierOperationAddressCapabilityInterface` per-op + `buildForOperation`/`handoffForOperation`/
`handoffContextForOperation` additions + handoff VO `getSupportedRepresentations()`; old
capability interface chỉ @deprecated docblock. (B/C) `CanonicalResolutionSnapshotInterface` + VO
contract (persistence defer). (D) resolver docblock AMBIGUOUS-only. (E) verify-only (STC3NB).
Old handoff methods delegate/refactor giữ behavior 1:1 (regression test bảo đảm).

## Steps

| # | Step | Files | Ghi chú |
|---|------|-------|---------|
| 1 | Governance | SPEC + plan + record + FEAT ticket_ref | spec-first TRƯỚC code |
| 2 | Constants | `Api/Address/{ShippingAddressOperation, AddressRepresentation}.php` | all/exists/assertKnown |
| 3 | Per-op capability | `Api/Address/CarrierOperationAddressCapabilityInterface.php` + @deprecated docblock old interface | old signature unchanged |
| 4 | Handoff per-op | builder interface/impl `buildForOperation`; handoff interface/impl `handoffForOperation`/`handoffContextForOperation`; handoff VO `getSupportedRepresentations` | BC: params optional/default; helper chung |
| 5 | Snapshot | `Api/Address/CanonicalResolutionSnapshotInterface.php` + `Model/Address/CanonicalResolutionSnapshot.php` | contract-only, persistence defer |
| 6 | Delta D | `Api/Address/ExternalAddressResolverInterface.php` docblock | AMBIGUOUS-only explicit |
| 7 | Tests | `Test/Unit/Model/Address/{OperationAddressCapabilityTest, CanonicalResolutionSnapshotTest}.php` + handoff regression assertions | §6 spec |
| 8 | Docs | `README.md` + `CHANGELOG.md` (0.14.0) | per-op flow + snapshot + freeze |
| 9 | Validation | phpunit (new + regression full ShippingCore) · compile · validator · greps (GEOPOINT/CarrierType/UNMAPPED-resolver/provider IDs) | AC-1..AC-6 |
| 10 | Memory | CURRENT_STATE + evidence `.ai/evidence/TASK-Y3X6H5/` | freeze conclusion |

## Test plan

Per-op: RATE(PRE_2025,[UNIT_ID]) vs CREATE(2025,[TEXT_NAME]) scheme/representation khác nhau ·
textual fallback per-op · unknown operation → exception · context/targetScheme mismatch →
exception · representations lên handoff · old `handoff()/handoffContext()` regression 1:1.
Snapshot: RESOLVED(NONE)+canonical · UNRESOLVED×3 failure classes · EXTERNAL provenance ·
impossible combos reject · constants stable.

## Risks & rollback

| Risk | Mitigation |
|------|-----------|
| Old handoff behavior đổi | Regression test existing handoff cases 1:1; refactor chỉ delegate |
| Carriers compile break | Old interface signature unchanged; new interface song song |
| Snapshot VO thành dead code | Contract P1 đã chốt §28; consumer = shift-left persistence task sau |

Rollback: revert — không DB, không carrier code.
