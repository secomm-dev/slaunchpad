# Magento Module Analysis

## Purpose

Analyze the impact of a Magento module (custom or third-party) — identify plugin chains, preferences, observers, and schema modifications. Maps what the module touches and flags high-risk interactions.

## When to Use

- Before modifying a module for the first time
- When debugging a module interaction issue
- Before upgrading Magento (check module compatibility)
- When adding a new third-party module (assess potential conflicts)
- When a module's behavior is unclear or undocumented

## Prerequisites

- `project-context/09_MAGENTO_MODULE_MAP.md` — module list and initial risk assessment
- Source code access to the target module (in `app/code/` or `vendor/`)
- Composer knowledge (for dependency analysis)
- Magento module structure knowledge (module.xml, etc/module.xml, di.xml, etc.)

## Input

The module name to analyze (vendor_module format, e.g., `Acme_CustomCheckout`).

## Steps

1. Read `project-context/09_MAGENTO_MODULE_MAP.md` — check existing documentation for this module
2. Locate the module files:
   - Path: `app/code/{Vendor}/{Module}/` (custom) or `vendor/{vendor}/{module}/` (third-party)
3. Analyze the module's interaction points:
   - **Plugin chains** (`di.xml`): List all before/around/after plugins — what class, method, and sort order
   - **Preferences** (`di.xml`): List all class preferences — what is being overridden
   - **Observers** (`events.xml`): List all observed events — what event, observer class, and when it fires
   - **Layout XML** (`view/*/layout/`): List layout modifications — what handles, blocks, containers
   - **Template overrides**: Check for template files in `view/*/templates/` that override core templates
   - **Database schema** (`etc/db_schema.xml`): List tables, columns, indexes added or modified
   - **Dependencies** (`etc/module.xml`): List `depends` on other modules
   - **Composer dependencies** (`composer.json`): List required packages, conflicts
   - **API endpoints** (`etc/webapi.xml`): List REST endpoints and their ACL
   - **Cron jobs** (`etc/crontab.xml`): List scheduled cron jobs and their schedule
4. Map impact: which Magento core areas does this module touch?
   - Checkout, payment, shipping, catalog, customer, sales, etc.
5. Flag high-risk interactions:
   - Plugin on core checkout/payment/shipping methods
   - Preference on core classes
   - Observer on critical events (`sales_order_save_after`, `checkout_submit_all_after`)
   - Schema changes to core tables
   - Module conflicts (same preference/plugin target as another module)
6. Check module documentation + structure health (Magento rules §1.1, §1.6, §9.6):
   - **README.md** present? (purpose / features / how-it-works) — required for Secomm-owned modules.
   - **CHANGELOG.md** present + current? (versioned, updated per change) — required.
   - **Module separation**: is this a generic / country-specific / market-setup module? Any country-specific coupling inside a generic module (hardcoded `countryId === 'XX'`, a local third-party reference)? Flag.
   - **Dual-theme**: if reusable and must render on both Luma + Hyva — does it ship both template sets + `hyva_` layout handle? (PHP `getTemplate()` theme detection is an anti-pattern.)

## Output Format

> **Output language:** Produce all prose in the project's `output_language` (see `.ai/AGENTS.md`; default English). Code, identifiers, file paths, and technical terms (Magento, plugin, GraphQL, checkout, etc.) ALWAYS stay English.

```markdown
## Module Analysis: {vendor_module}

### Module Info
- **Path**: {path}
- **Type**: {Custom / Third-party}
- **Status**: {Enabled / Disabled}
- **Risk level**: {H/M/L — from module map}

### Interaction Points
#### Plugins
| Class | Method | Sort Order | Plugin Type | Module |
|-------|--------|------------|-------------|--------|
| {class} | {method} | {order} | before/around/after | {plugin_class} |

#### Preferences
| Original Class | Override Class | Purpose |
|---------------|---------------|---------|
| {class} | {override} | {purpose} |

#### Observers
| Event | Observer Class | When |
|-------|---------------|------|
| {event} | {class} | {global/admin/frontend} |

#### Database Schema
| Action | Table | Details |
|--------|-------|---------|
| {add/modify} | {table} | {columns/indexes} |

#### Dependencies
| Type | Dependency | Version |
|------|------------|---------|
| require | {module} | {version} |

#### Module Documentation & Structure
| Aspect | Status |
|--------|--------|
| README.md | {present / missing} |
| CHANGELOG.md | {present + current / missing / stale} |
| Module type | {generic / country-specific / market-setup / third-party} |
| Dual-theme | {N/A · Luma+Hyva via `hyva_` handle · Luma-only · Hyva-only · PHP detection (anti-pattern)} |
| Separation flags | {country-specific coupling in generic module? …} |

### Impact Summary
**Core areas affected**: {list}

### High-Risk Flags
- [ ] {flag} — {reason}
```

## Quality Checklist

- [ ] All 10 interaction points checked (plugins, preferences, observers, layout, templates, schema, dependencies, composer, API, cron)
- [ ] Plugin sort order noted — conflicts with other modules possible?
- [ ] Preferences justified — could a plugin achieve the same goal?
- [ ] Observer event timing understood — does it run on critical paths?
- [ ] README.md + CHANGELOG.md present + current (Secomm-owned modules)
- [ ] Module separation clean (no country-specific coupling in a generic module); dual-theme via `hyva_` handle (not PHP detection)

## Escalation Rules

- Plugin on core checkout/payment/shipping class → flag for Tier 2 review
- Preference overrides core class → flag for SA/TL review
- Schema changes to core tables → flag for Tier 2
- Module conflicts discovered → flag for TL review before proceeding

## Example

**Input**: `Acme_CustomCheckout`

**Output**:
```markdown
## Module Analysis: Acme_CustomCheckout

**Path**: app/code/Acme/CustomCheckout | **Type**: Custom
**Risk level**: High

### Plugins
| Class | Method | Order | Type | Plugin Class |
|-------|--------|-------|------|-------------|
| Magento\Checkout\Controller\Index\Index | execute | 10 | around | Acme\CustomCheckout\Plugin\ReplaceCheckout |
| Magento\Quote\Model\QuoteManagement | submit | 20 | around | Acme\CustomCheckout\Plugin\ApprovalCheck |

### Observers
| Event | Observer | When |
|-------|----------|------|
| sales_order_save_after | Acme\CustomCheckout\Observer\SyncToERP | global |

### Impact Summary
**Core areas affected**: Checkout, Quote, Order, Sales

### High-Risk Flags
- [x] Plugin on QuoteManagement::submit — affects ALL order submissions
- [x] Observer on sales_order_save_after — runs on every order save
```