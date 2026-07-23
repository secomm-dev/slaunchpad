# assess-migration-complexity

> Function (English meta). Initialization phase. Score the migration difficulty in business terms.

## Purpose
Assess migration complexity by domain so the team can plan discovery depth, estimation range, sequencing, and risk mitigation.

## When to use
- After gap analysis
- Before discovery question finalization
- Before estimating a migration or preparing client conversation points

## Required inputs
- Migration gap analysis
- Business capability inventory
- Source and target constraints

## Required source system context
- Scope of customizations, data dependencies, and integration touchpoints

## Required target platform context
- Shopify implementation path per capability
- Shopify plan and extensibility constraints

## Required research profile
- Migration complexity profile
- Shopify checkout, B2B, app, and function capability profiles as needed

## Required project files to read
- `shared-core/initialization/ESTIMATION_DRIVER_STANDARD.md`
- `shared-core/initialization/templates/CURRENT_SYSTEM_ASSESSMENT.template.md`

## Required agents
- `sa`
- `tl`

## Required skills
- `research-implementation`
- `audit-performance`

## Required rules
- `planning-first.md`
- `research-first.md`
- `backward-compatibility.md`

## Expected output
- Complexity ratings by area: Low, Medium, High, or Critical
- Complexity notes for catalog, customer, checkout, payment, shipping, SEO, CMS, integration, ERP, B2B, custom apps, data migration, and cutover

## Evidence required
- Complexity matrix with linked reasons and evidence

## Validation
- Every rating is explainable from gaps and dependencies
- Critical complexity is reserved for real blockers or major multi-system impact

## Memory updates
- `RESEARCH_NOTES.md`
- `CONTINUOUS_LEARNING.md` when the same complexity pattern repeats

## Failure handling
- If complexity is ambiguous, keep the higher-risk score until discovery resolves it
- If data is missing, mark the affected area `Unknown` and note the blocker

## Related standards
- `shared-core/initialization/ESTIMATION_DRIVER_STANDARD.md`
- `shared-core/initialization/CAPABILITY_INVENTORY_STANDARD.md`

## When to improve/update
- When a repeated migration pattern consistently changes complexity scoring
- When Shopify platform changes alter the effort model
