# generate-migration-gap-analysis

> Function (English meta). Initialization phase. Turn source-to-target mapping into explicit migration gaps.

## Purpose
Identify the gaps between source Magento capabilities and the Shopify target path, including business impact, technical impact, risk, and open questions.

## When to use
- After target platform mapping
- Before complexity scoring and discovery question generation
- When the team needs a gap matrix for BA, TL, estimation, and solution design

## Required inputs
- Business capability inventory
- Target platform mapping
- Known source limitations and Shopify constraints

## Required source system context
- Source behaviors that must be preserved, replaced, or redesigned

## Required target platform context
- Shopify native limits and extension boundaries
- App/function/custom app possibilities

## Required research profile
- Shopify migration gap profile
- Checkout and B2B capability profile when the source uses them

## Required project files to read
- `shared-core/initialization/TARGET_PLATFORM_MAPPING_STANDARD.md`
- `shared-core/initialization/templates/CURRENT_SYSTEM_ASSESSMENT.template.md`

## Required agents
- `sa`
- `ba`

## Required skills
- `research-implementation`
- `audit-architecture`

## Required rules
- `research-first.md`
- `security-first.md`
- `evidence-required.md`

## Expected output
- Migration gap analysis with source behavior, target limitation, recommended approach, impact, complexity, and open questions

## Evidence required
- Gap matrix linked to source evidence and target capability notes

## Validation
- Every gap states why it exists
- Every gap proposes a realistic migration approach
- No gap silently assumes 1:1 parity

## Memory updates
- `RESEARCH_NOTES.md`
- `project-context/06_KNOWN_CONSTRAINTS_AND_RISKS.md`
- `DECISIONS.md` when a gap resolution becomes accepted

## Failure handling
- If the target limitation is unknown, mark the gap `Unknown` and route it to discovery
- If a gap is low confidence, keep it visible instead of normalizing it away

## Related standards
- `shared-core/initialization/TARGET_PLATFORM_MAPPING_STANDARD.md`
- `shared-core/initialization/ESTIMATION_DRIVER_STANDARD.md`

## When to improve/update
- When a recurring source behavior becomes a known Shopify gap pattern
- When new Shopify features reduce or remove an existing gap
