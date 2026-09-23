# Implementation Plan: TASK-STC3NB — ShippingCore COD payment identification (architecture v4 §4.1)

## Metadata

| Field | Value |
|-------|-------|
| Task | TASK-STC3NB (parent FEAT-YA2C0W) |
| Mode | A (shipping shared-contract) |
| Specification | [specs/SPEC-TASK-STC3NB-shippingcore-cod-payment-identification.md](../specs/SPEC-TASK-STC3NB-shippingcore-cod-payment-identification.md) — FULL, VALID (architecture basis: address-shipping.md **Revision v4 §4.1**, đã TL/SA amend) |
| Decision | [DEC-FEATYA2C0W-004](../records/decisions/DEC-FEATYA2C0W-004.md); không DEC mới — COD identification đã được approve trong architecture v4; các gap PARTIAL khác (per-operation capability, snapshot) DEFER + REPORT |
| Audit basis | Audit 2026-09-11: MATCH (rate/fallback/service-level/handoff/resolver-seam/deps) · PARTIAL defer (capability per-operation — implementers ngoài ShippingCore; snapshot — DB Tier-2) · MISSING (COD) |
| Risk | Low — 1 interface + 1 resolver + admin config field; chưa có consumer code (GHN/GHTK consume sau) |

## Approach

`Api\Cod\CodPaymentMethodResolverInterface` (isCod scalar-in/bool-out) + `Model\Cod\ConfiguredCodPaymentMethodResolver`
(config `secomm_shippingcore/cod/payment_methods`, comma-separated, trim + exact strict match,
empty/malformed → false) + system.xml/config.xml + DI preference. Identification thuần —
không COD framework (directive "Không implement" list).

## Steps

| # | Step | Files | Ghi chú |
|---|------|-------|---------|
| 1 | Governance | SPEC + plan + record + FEAT ticket_ref | spec-first TRƯỚC code |
| 2 | Contract | `Api/Cod/CodPaymentMethodResolverInterface.php` | scalar-in/bool-out |
| 3 | Resolver | `Model/Cod/ConfiguredCodPaymentMethodResolver.php` | normalization + exact match + safe false |
| 4 | Config | `etc/adminhtml/system.xml`, `etc/config.xml`, `etc/di.xml` | section `secomm_shippingcore`; ACL Magento_Backend::stores |
| 5 | Tests | `Test/Unit/Model/Cod/ConfiguredCodPaymentMethodResolverTest.php` | §6 spec (8 cases) |
| 6 | Docs | `README.md` + `CHANGELOG.md` | COD identification ownership |
| 7 | Validation | phpunit (COD + regression ShippingCore) · compile · validator · grep forbidden deps | AC-4/AC-5 |
| 8 | Memory | CURRENT_STATE + evidence `.ai/evidence/TASK-STC3NB/` | kèm compliance matrix |

## Test plan

configured single → true · multi → từng code · unconfigured → false · empty → false ·
null → false · prefix (`cashondel`) → false · case-different (`CASHONDELIVERY`) → false ·
config whitespace (`" a , b "`) → deterministic · query whitespace trimmed.

## Risks & rollback

| Risk | Mitigation |
|------|-----------|
| Config scope sai (store-specific COD) | Contract không có storeId — default scope documented; store-scoping là upgrade không đổi contract |
| Case-sensitivity gây khó hiểu | Documented exact/case-sensitive; config author control |
| Scope creep sang COD framework | Spec §4 cứng; grep review |

Rollback: revert — không DB, không carrier code.
