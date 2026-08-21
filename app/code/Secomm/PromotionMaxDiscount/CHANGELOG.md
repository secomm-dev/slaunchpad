# Changelog — Secomm_PromotionMaxDiscount

## 1.4.0 — 2026-08-21 (TASK-4HYX6Y — integration tests, Option 2 engine-level)

### Added — engine-level integration suite (24 tests / 121 assertions, repeat ×3 stable, zero residue)
- `Test/Integration/bootstrap.php` — full-Magento PHPUnit bootstrap (TL-approved engine-level
  option; the isolated TestFramework was never configured in this project).
- `Test/Integration/AbstractCapTestCase.php` — fixture builders encoding the accumulated
  lessons: MSI salability (source item + cataloginventory reindex + repository reload),
  quote persisted before breakdown assertions, rule SKU restrictions via JSON
  `setActionsSerialized`, guaranteed teardown (quotes → rules → orders → products) with a
  final residue sweep; precision-aware amount assertions.
- Groups per the ticket matrix: A single item (no-op / cap / NULL), B multi-item
  (proportional contribution 20k/30k, ineligible untouched, qty), C multi-rule (two caps
  independent, capped + uncapped, stop_rules, coupon dormant → active), D recompute
  (idempotent ×3, qty/item/coupon changes, customer-group switch, two-precision chains
  VND base / USD display), E lifecycle — all 7 ticket scenarios incl. invoices/credit
  memos after post-placement rule changes (pure allocation, numbers never move).

### Known coverage gaps (disclosed, escalated)
- Configurable/bundle children-calculated path: test present but skipped — programmatic
  composite fixtures stay non-salable under MSI despite reindexList/reindexAll; needs a
  dedicated fixture investigation. Unit tests cover the parent-item collector logic.
- Tax incl/excl × catalog price matrix and real FX-rate display currencies: deferred to
  the QC environment (shared dev DB is not repeat-safe for global config surgery).

## 1.3.0 — 2026-08-21 (TASK-67GGPR — admin UI field)

### Added — Actions tab field "Maximum Discount Amount"
- `Plugin/Adminhtml/Rule/Metadata/ViewProviderPlugin` (adminhtml scope) — injects the
  field into the ValueProvider metadata, spliced right after `discount_amount` in
  `actions.children` (core fields carry no explicit sortOrder, so array order is render
  order). Core `sales_rule_form.xml` untouched; skips injection if core ever ships the
  field natively. `input` + `validate-number` / `validate-zero-or-greater`, hidden by
  default.
- `view/adminhtml/web/js/form/element/max-discount-field.js` — visible and enabled only
  while Apply = "Percent of product price discount" (imports `simple_action` from the
  same fieldset, mirrors core `apply_to_shipping`). Disabled fields are not submitted,
  so an action switch never sends a stale value; the stored cap survives (runtime
  collector guard stays independent of the UI).
- `Plugin/Adminhtml/Rule/LoadPostNormalizerPlugin` — cleared visible input submits `''`,
  normalised to NULL before `loadPost` (nullable DECIMAL column; NULL/0 = unlimited).
  Absent keys untouched.
- `i18n/vi_VN.csv` + `i18n/en_US.csv` — label + note (BR-001).

### Tests
- +10 unit tests (splice position, field config, core-keys-identical, native-field
  guard, normalizer cases) — module suite 50 tests / 883 assertions GREEN.
- Runtime wiring check on the real engine (adminhtml area, post di:compile): 10/10 —
  meta carry-through, `loadPost('')` → NULL, absent key keeps stored cap.
- Pre-review round 2 (merge-path repro with the real UiComponentFactory): the field
  config now carries `componentType: field` — without it `mergeMetadataItem()` throws
  and the whole sales rule form breaks (repro verified); meta-created components render
  at the END of the Actions fieldset (factory appends new children — documented AC-1
  deviation, TL decision).

## 1.2.1 — 2026-08-21 (TASK-5H8WKE — AI pre-review round 2 fixes)

### Fixed
- Address breakdown rebuild now aggregates from ALL shipping-assignment items
  (mirroring the native `$itemsAggregate` set), so uncapped rules on items
  untouched by a cap keep their entries in `address.extension_attributes.discounts[]`
  (REST `V1/carts/totals` / GraphQL `cart.discounts`). Previously rebuilt from
  capped-affected items only — a rule applying to a different item vanished or was
  understated whenever a capped rule fired.
- Currency precision is resolved from the quote's own currency codes
  (`base_currency_code` / `quote_currency_code`, store fallback for unsaved quotes),
  passed explicitly to `Locale\Format::getPriceFormat()` — the no-code call resolves
  through the CURRENT request scope, so admin/CLI/API contexts could allocate on the
  wrong grid (a third "store" argument was silently ignored: the method takes two).
  Base and display chains now each use their own currency's precision.

### Changed
- `RuleCapResolver` selects `rule_id, simple_action, maximum_discount_amount` only —
  no more serialized conditions/actions blobs on the per-pass query.
- Converter plugins `aroundToDataModel`/`aroundToModel` → `afterToDataModel`/
  `afterToModel` (behavior-identical; `after` preferred per coding standards).

### Tests
- +2 repro tests (mixed capped/uncapped address breakdown; precision resolved from
  quote currencies) — module suite 40 tests / 858 assertions GREEN.
- Runtime smoke re-run on the real engine post-fix: 24/24 checks PASS (mixed-rule
  address breakdown, VND integer grid, converter round-trip, idempotency ×3);
  `setup:di:compile` re-run after the plugin signature change.

## 1.2.0 — 2026-08-20 (TASK-5H8WKE / FEAT-JKZM68 / DEC-FEATJKZM68-001 §1/§2/§4)

### Added — cap engine (the core slice)
- `etc/sales.xml` — quote total collector `max_discount_cap` at sort_order 310, right
  after the native SalesRule discount collector (300) and before shipping/tax, so
  every later consumer (Mollie fee, Mageplaza ExtraFee/OSC, tax, grand total) sees
  capped amounts. No `fetch()` — the cap lives inside the native "discount" total.
- `Model/Quote/Address/Total/MaxDiscountCap` — per-rule cap collector: reads the
  native per-rule per-item breakdown, scales contributions by cap/Σ when the base
  chain exceeds the cap, redistributes with largest-remainder per chain (base and
  display independently), mutates entries in place, adjusts aggregates exactly like
  the native final block, and rebuilds the address per-rule breakdown.
- `Model/Cap/RuleCapResolver` — stateless per-pass resolution (one query per
  collect, guards `by_percent` + cap > 0 at calculation time).
- `Model/Redistribution/LargestRemainderAllocator` — pure functions, currency
  precision injected (VND 0 / USD 2), tie-break by ascending item key.
- `Test/Unit/*` — 38 tests / 849 assertions: LRM invariants (24 generated cases),
  resolver guards, collector behaviour incl. no-op paths and determinism.

### Fixed during verification
- Resolver instance memo leaked stale caps across collect passes (shared DI
  singleton) — removed; the collector now owns the one-query-per-pass contract.
- Precision source: `PriceCurrency::round()` hard-codes 2 decimals; the currency
  precision now comes from `Locale\FormatInterface::getPriceFormat()` (VND → 0).

## 1.1.0 — 2026-08-20 (SL-021 / FEAT-008 / DEC-FEAT008-001 §3)

### Added — data model (no pricing behavior yet)
- `salesrule.maximum_discount_amount DECIMAL(12,4) UNSIGNED NULL` via declarative
  schema (`etc/db_schema.xml` + `etc/db_schema_whitelist.json`) — additive merge
  into the core table, declarative-only (no install/upgrade scripts).
- `maximum_discount_amount` extension attribute on
  `Magento\SalesRule\Api\Data\RuleInterface` (`etc/extension_attributes.xml`).
- Converter plugins (`etc/di.xml`): write path copies the extension attribute onto
  the persistable model (`ToModel::toModel` — interface reflection would otherwise
  drop it); read path exposes the column on every repository read
  (`ToDataModel::toDataModel` — covers getById / getList / save response).
  Saves that omit the attribute keep the stored value (no silent REST reset).

### Verified (SL-021 evidence)
- AC-1 column additive + idempotent upgrade · AC-2 model path (loadPost 50000 /
  NULL round-trip) · AC-3 service-contract round-trip incl. no-reset guard ·
  AC-4 rollback semantics documented in README (disable + next `setup:upgrade`
  DROPS the column — corrected from the original ticket assumption) ·
  AC-5 zero `vendor/` changes.

## 1.0.0 — 2026-08-19 (SL-020 / FEAT-008 / DEC-FEAT008-001)

### Added — foundation scaffold
- Module registration (`registration.php`, `etc/module.xml`) with dependency sequence
  `Secomm_Promotion` + `Magento_SalesRule` + `Magento_Quote` + `Magento_Sales`
  (DEC-FEAT008-001 §5 module boundary).
- `i18n/vi_VN.csv` + `i18n/en_US.csv` placeholders (BR-001 bilingual policy).
- Directory tree for upcoming slices: `Model/`, `Plugin/`, `Test/`.

No runtime behavior in this release — scaffold only. See README "Status" for the
SL-021..SL-025 delivery plan.
