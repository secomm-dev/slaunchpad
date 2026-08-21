# Secomm_Promotion

Base/foundation module for the **Secomm Promotion group** (Secomm Launchpad Core) —
FEAT-008 / DEC-FEAT008-001.

## Purpose

Anchor module grouping Secomm promotion mechanics. Each promotion capability ships as
an independent feature module (e.g. `Secomm_PromotionMaxDiscount`) that depends on this
base. Magento `SalesRule` remains the promotion execution engine — Secomm modules only
extend it, never replace it.

## Design policy (DEC-FEAT008-001 §5)

- **Deliberately minimal**: this module contains only module registration — no PHP
  classes, no business logic, no configuration.
- **No premature framework**: shared contracts/utilities are pulled up here only when
  a *second* promotion module exists and real duplication is proven (YAGNI).
- Promotion business logic NEVER lives here — it belongs to the feature module.

## How it works

Feature modules declare `Secomm_Promotion` in their `module.xml` sequence so the whole
group can be tracked, ordered and reused as one unit across Launchpad projects.

## Current feature modules

| Module | Capability | Spec |
|---|---|---|
| `Secomm_PromotionMaxDiscount` | Per-rule Maximum Discount Cap for `by_percent` Cart Price Rules | [SPEC-FEAT-008](../../../../.ai/specs/SPEC-FEAT-008-promotion-max-discount.md) |
