# identify-discovery-focus

> Function (English meta). Initialization phase. Turn assessment gaps into the BA discovery agenda.

## Purpose
Convert migration gaps, unknowns, and high-complexity areas into prioritized discovery focus areas for BA and client workshops.

## When to use
- After capability mapping, gap analysis, and complexity assessment
- Before generating or finalizing discovery questions

## Required inputs
- Business capability inventory
- Migration gap analysis
- Complexity assessment
- Known assumptions and blockers

## Required source system context
- The source behaviors that are not yet fully understood

## Required target platform context
- The Shopify constraints that need validation with the client

## Required research profile
- Discovery profile for migration projects
- Capability-specific research profile where the source has special behavior

## Required project files to read
- `shared-core/initialization/DISCOVERY_PREPARATION_GUIDE.md`
- `shared-core/initialization/CAPABILITY_INVENTORY_STANDARD.md`
- `shared-core/initialization/ESTIMATION_DRIVER_STANDARD.md`

## Required agents
- `ba`
- `sa`

## Required skills
- `spec`
- `research-implementation`

## Required rules
- `no-duplicate-knowledge.md`
- `planning-first.md`
- `research-first.md`

## Expected output
- Prioritized discovery focus areas
- Question clusters for source of truth, data, integrations, checkout, SEO, and cutover

## Evidence required
- Discovery focus list with the assessment items that triggered each question cluster

## Validation
- Every focus area traces back to a gap, unknown, or high-risk area
- Discovery questions are not generic; they are linked to assessment findings

## Memory updates
- `RESEARCH_NOTES.md`
- `CONTINUOUS_LEARNING.md`

## Failure handling
- If the assessment is incomplete, route the missing areas to discovery instead of guessing
- If no focus areas exist, discovery may be lightweight but must still validate the migration assumptions

## Related standards
- `shared-core/initialization/CAPABILITY_INVENTORY_STANDARD.md`
- `shared-core/initialization/ESTIMATION_DRIVER_STANDARD.md`

## When to improve/update
- When BA repeatedly asks the same follow-up questions for the same migration pattern
- When a new assessment pattern needs a discovery checklist
