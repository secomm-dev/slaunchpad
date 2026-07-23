# generate-current-system-assessment

> Function (English meta). Initialization phase. Compile the migration assessment into the final source assessment artifact.

## Purpose
Assemble the technical audit, business capability inventory, target platform mapping, gap analysis, complexity assessment, discovery focus, and estimation drivers into `CURRENT_SYSTEM_ASSESSMENT.md`.

## When to use
- After the migration assessment sub-steps are complete
- Before discovery questions are finalized
- Before the draft blueprint is allowed to start

## Required inputs
- Technical audit
- Business capability assessment
- Target platform mapping
- Migration gap analysis
- Complexity assessment
- Discovery focus areas
- Estimation drivers

## Required source system context
- Verified Magento evidence and source-system behavior

## Required target platform context
- Shopify capability boundaries and target implementation paths

## Required research profile
- Migration assessment profile
- Shopify platform profile
- Vendor verification profile for any third-party dependencies

## Required project files to read
- `shared-core/initialization/templates/CURRENT_SYSTEM_ASSESSMENT.template.md`
- `shared-core/initialization/CAPABILITY_INVENTORY_STANDARD.md`
- `shared-core/initialization/TARGET_PLATFORM_MAPPING_STANDARD.md`
- `shared-core/initialization/ESTIMATION_DRIVER_STANDARD.md`

## Required agents
- `sa`
- `ba`
- `tl`

## Required skills
- `research-implementation`
- `audit-architecture`
- `spec`

## Required rules
- `research-first.md`
- `evidence-required.md`
- `no-duplicate-knowledge.md`

## Expected output
- A complete `CURRENT_SYSTEM_ASSESSMENT.md` that is migration-ready for BA, TL, estimation, and client discussion

## Evidence required
- Final assessment document
- Evidence index with source links and commands

## Validation
- Assessment contains technical audit, business capabilities, Shopify mapping, gaps, complexity, discovery focus, estimation drivers, strategy notes, evidence index, and unknowns
- Audit-only output is rejected for migration projects

## Memory updates
- `RESEARCH_NOTES.md`
- `CONTINUOUS_LEARNING.md`
- `project-context/06_KNOWN_CONSTRAINTS_AND_RISKS.md`

## Failure handling
- If any required section is missing, return the assessment as incomplete and keep the project in the assessment phase
- If evidence is missing, keep the unknown visible and do not auto-promote to blueprint

## Related standards
- `shared-core/initialization/CAPABILITY_INVENTORY_STANDARD.md`
- `shared-core/initialization/TARGET_PLATFORM_MAPPING_STANDARD.md`
- `shared-core/initialization/ESTIMATION_DRIVER_STANDARD.md`

## When to improve/update
- When a migration assessment section needs a clearer field structure
- When BA/TL feedback shows the output is still too technical or too shallow
