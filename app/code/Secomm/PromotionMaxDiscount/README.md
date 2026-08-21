# Secomm_PromotionMaxDiscount

**Per-rule Maximum Discount Cap** for `by_percent` Cart Price Rules —
"Giảm X%, tối đa Y" (e.g. *20% off, up to 50,000 VND*).
FEAT-008 · [SPEC-FEAT-008](../../../../.ai/specs/SPEC-FEAT-008-promotion-max-discount.md) ·
DEC-FEAT008-001.

## Purpose

Magento native `by_percent` rules cannot limit the **total monetary discount** a rule
generates. This module adds a `Maximum Discount Amount` per rule: when the sum of the
rule's native discount contributions across eligible quote items exceeds the cap, the
result is scaled down and redistributed proportionally to each item's native
contribution (largest-remainder rounding), before shipping/tax/grand-total collectors run.

## How it works (DEC-FEAT008-001)

```
Magento SalesRule discount collector (sort 300)      — UNCHANGED, engine of record
        ↓ per-rule per-item breakdown (item.extension_attributes.discounts[])
MaxDiscountCap collector (sort 310)                  — THIS module
        ↓ Σ per rule > cap → factor = cap/Σ → largest-remainder redistribute
shipping (350) → tax (450) → grand_total (550)       — see capped amounts
        ↓ native fieldset copy (to_order_item_discount)
order item → invoice / credit memo                   — native pure allocation
```

Key properties: per-rule caps (independent) · eligible items only (native eligibility,
never re-implemented) · `NULL`/`0` = unlimited (native behavior) · `by_percent` only
(runtime guard on `simple_action`) · shipping benefits never capped · idempotent
(repeated `collectTotals()` identical) · no Magento core modification.

## Status

- **1.2.0 (TASK-5H8WKE): cap engine** — collector at sort 310 caps `by_percent`
  rules whose Σ native discount exceeds `maximum_discount_amount`, redistributes
  by contribution (largest-remainder, per-chain precision from the locale price
  format — VND 0 decimals). Runtime-verified on VND: 20% on 2M+3M with cap 50k
  → 20k/30k, Σ == cap on both chains, idempotent across repeat collectTotals.
- **1.1.0 (TASK-33J3RP): data model** — `salesrule.maximum_discount_amount` column
  (declarative schema, additive) + `RuleInterface` extension attribute with
  converter plugins so the field round-trips through the service contract
  (`RuleRepository` save/getById/getList, i.e. REST `/V1/salesRules/*`):
  saves that omit the attribute keep the stored value (no silent reset).
- **1.0.0 (TASK-3R6X8E): scaffold** — module registration + dependency sequence.

Admin UI lands in TASK-67GGPR, integration tests in TASK-4HYX6Y, QC in
TASK-HPK1WZ.

## Schema & rollback (verified on 2.4.8-p5)

Column: `maximum_discount_amount DECIMAL(12,4) UNSIGNED NULL` on `salesrule`,
declared in `etc/db_schema.xml` + `etc/db_schema_whitelist.json` (declarative only —
no InstallSchema/UpgradeSchema/data patches, no `vendor/` changes). Semantic:
`NULL`/`0` = unlimited (native behavior); `> 0` = cap in **base currency**
(`by_percent` rules only — runtime guard lands with SL-022).

Rollback semantics (empirical, verified 2026-08-20):

| Action | Column | Data |
|---|---|---|
| `module:disable` alone (no upgrade) | kept | kept |
| `module:disable` + next `setup:upgrade` | **dropped automatically** | **lost** |
| `module:enable` + `setup:upgrade` | recreated | NULL (fresh) |

So disabling the module in an environment where deploys run `setup:upgrade`
removes the column with its data — export cap values first (`SELECT rule_id,
maximum_discount_amount FROM salesrule WHERE maximum_discount_amount > 0`).
Manual drop when the module is enabled is NOT needed and NOT recommended —
declarative schema owns the column. The column sits on a core table: track
`project-context/12_UPGRADE_NOTES.md` when upgrading Magento.

REST note: a save that omits the attribute keeps the stored value; the attribute
is typed `float`, so an explicit `null` over REST is indistinguishable from
"absent" — clear a cap via the admin form (stores NULL) or by saving `0`.

## Configuration

(rule-level field lands with SL-023:
`Marketing → Promotions → Cart Price Rules → Actions → Maximum Discount Amount`)
