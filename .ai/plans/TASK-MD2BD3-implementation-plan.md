# Implementation Plan: TASK-MD2BD3 — v10 PICK_PRIMARY (directional curated primary + deterministic selector)

## Metadata

| Field | Value |
|-------|-------|
| Task | TASK-MD2BD3 (parent FEAT-YA2C0W) |
| Mode | A (canonical mapping data + shipping shared-contract) |
| Specification | [specs/SPEC-TASK-MD2BD3-pick-primary-v10.md](../specs/SPEC-TASK-MD2BD3-pick-primary-v10.md) — FULL, VALID (architecture **Revision v10** §35.4 + DEC-FEATYA2C0W-006 amendment) |
| Decision | [DEC-FEATYA2C0W-006](../records/decisions/DEC-FEATYA2C0W-006.md) amendment (PICK_PRIMARY promoted; legacy RATE strategy superseded) |
| Contract basis | TASK-Y3X6H5 (snapshot) · TASK-M3ME32 (E-SL2) · TASK-NQT782 (bridge consumer) |
| Risk | Medium — schema additive (an toàn); selector query mới; handoff policy branch mới |

## Approach

VietNamAddress: `is_primary` boolean column (directional — chỉ meaningful theo hướng row) +
reader dual-header + validator duplicate-primary error + importer normalize. Selector
`VnPrimaryCandidateSelector` query directional curated primary (count<2 → NOT_APPLICABLE; 0 →
NO_DESIGNATED_PRIMARY; >1 → MULTIPLE_PRIMARY fail-closed). ShippingCore:
`AddressResolutionPolicy` (STRICT/FALLBACK/PICK_PRIMARY) + `handoffForOperation` policy param +
selector integration (AMBIGUOUS + PICK_PRIMARY + SELECTED → re-resolve same-scheme EXACT trên
selected candidate qua manager). Snapshot +3 optional provenance getters. Không carrier/bridge edit.

## Steps

| # | Step | Files | Ghi chú |
|---|------|-------|---------|
| 1 | Governance | SPEC + plan + record + FEAT ticket_ref | |
| 2 | VN schema | `etc/db_schema.xml` + `db_schema_whitelist.json` | is_primary boolean default false |
| 3 | VN reader/importer | `VnMappingReader` (dual header) + `VnMappingImporter` (is_primary) + `VnMappingValidator` (duplicate-primary error) | |
| 4 | VN selector | `Api/VnPrimaryCandidateSelectorInterface.php` + `Api/Data/VnPrimaryCandidateSelectionInterface.php` + `Model/Data/VnPrimaryCandidateSelection.php` + `Model/VnPrimaryCandidateSelector.php` + di preference | |
| 5 | SC policy | `Api/Address/AddressResolutionPolicy.php` + handoff per-op policy param + selector integration | BC-safe optional param |
| 6 | SC snapshot | `CanonicalResolutionSnapshotInterface` + VO: +selection provenance optional | |
| 7 | DI | `ShippingCore/etc/di.xml` (constructor arg tự resolve — chỉ cần nếu explicit) + `VietNamAddress/etc/di.xml` preference | |
| 8 | Tests | VN: selector/validator/reader tests; SC: handoff PICK_PRIMARY cases + snapshot provenance | |
| 9 | Docs | `README.md` + `CHANGELOG.md` cả 2 module | |
| 10 | Validation | phpunit scoped + full · compile · `setup:upgrade` (apply is_primary) · validator | |

## Test plan

Selector (mock connection/Select chain): 1 primary → SELECTED · 0 → NO_DESIGNATED · 2 →
MULTIPLE_PRIMARY · <2 candidates → NOT_APPLICABLE · order shuffle invariant · reverse-direction
primary không pick. Validator: is_primary '2'/'x' → error · duplicate directional primary →
error · missing → default 0. Reader: legacy header + v1.1 header. Handoff: policy param flows
(§24 directive cases). Snapshot: provenance optional fields.

## Risks & rollback

| Risk | Mitigation |
|------|-----------|
| Dataset hiện có chứa duplicate primary | Import validation fail-loud trước khi vào runtime; selector fail-closed MULTIPLE_PRIMARY |
| Reader dual-header parse sai cột | Header-key mapping theo header detected; tests cả 2 format |
| PICK_PRIMARY dùng cho key không curated | Selector trả NO_DESIGNATED_PRIMARY — không pick mù |

Rollback: revert + `setup:upgrade` — column additive drop an toàn, không data migration bắt buộc.
