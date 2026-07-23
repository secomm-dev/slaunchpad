# map-source-to-target-platform

> Function (English meta). Initialization phase. Map source capabilities to the target platform honestly.

## Purpose
Map each assessed business capability to the most realistic Shopify delivery path without assuming one-to-one parity.

## When to use
- After business capability assessment
- Before gap analysis
- When the team needs to test whether Shopify can support the current Magento behavior

## Required inputs
- Business capability inventory
- Source evidence
- Shopify plan and target constraints

## Required source system context
- Current Magento behavior and customization level
- Data ownership and integration touchpoints

## Required target platform context
- Shopify native
- Shopify configuration
- Shopify app ecosystem
- Shopify Functions
- Checkout Extensibility
- Custom app and script options

## Required research profile
- Shopify platform capability profile
- Shopify checkout and Functions profile
- Shopify app and B2B profile when relevant

## Required project files to read
- `shared-core/initialization/TARGET_PLATFORM_MAPPING_STANDARD.md`
- `shared-core/initialization/templates/CURRENT_SYSTEM_ASSESSMENT.template.md`

## Required agents
- `sa`
- `tl`

## Required skills
- `research-implementation`
- `create-solution-design`

## Required rules
- `backward-compatibility.md`
- `research-first.md`
- `evidence-required.md`

## Expected output
- Target platform mapping per capability
- Supported, configurable, app-based, extensible, custom, manual, or redesign-required classification

## Evidence required
- Mapping matrix with source evidence and target rationale

## Validation
- No false parity claims
- Every capability has a target decision or explicit unknown
- Any Shopify limitation is visible, not hidden

## Memory updates
- `RESEARCH_NOTES.md`
- `DECISIONS.md` for repeated mapping choices

## Failure handling
- If Shopify support is unclear, mark the capability `Unknown` and route it to discovery
- If multiple approaches exist, keep all feasible options and note tradeoffs

## Related standards
- `shared-core/initialization/TARGET_PLATFORM_MAPPING_STANDARD.md`
- `shared-core/initialization/ESTIMATION_DRIVER_STANDARD.md`

## When to improve/update
- When a Magento behavior repeatedly maps to a known Shopify pattern
- When Shopify plan or feature changes affect migration mapping
