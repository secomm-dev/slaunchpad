# identify-estimation-drivers

> Function (English meta). Initialization phase. Explain what will drive migration effort and risk.

## Purpose
Identify the main effort and risk drivers for migration estimation so BA, TL, PM, and delivery can size the work realistically.

## When to use
- After gap analysis and complexity assessment
- Before estimating discovery or solution work
- When the client asks what makes the migration hard

## Required inputs
- Migration gap analysis
- Complexity assessment
- Business capability inventory
- Target platform mapping

## Required source system context
- Source customizations, data volume, and integration load

## Required target platform context
- Shopify implementation approach and any platform limitations

## Required research profile
- Estimation profile for migration work
- Shopify implementation profile for checkout, B2B, and integration work

## Required project files to read
- `shared-core/initialization/ESTIMATION_DRIVER_STANDARD.md`
- `shared-core/initialization/templates/CURRENT_SYSTEM_ASSESSMENT.template.md`

## Required agents
- `ba`
- `tl`
- `pm`

## Required skills
- `estimate-feature`
- `research-implementation`

## Required rules
- `evidence-required.md`
- `planning-first.md`
- `research-first.md`

## Expected output
- Top estimation drivers with source evidence, affected areas, complexity, assumptions, and open questions

## Evidence required
- Driver list that traces each driver back to source findings and target constraints

## Validation
- Drivers are specific, not generic
- Each driver explains why effort increases or risk rises
- Shopify-specific redesign or custom app work is called out separately from config work

## Memory updates
- `CONTINUOUS_LEARNING.md`
- `RESEARCH_NOTES.md`
- `estimation-tracking.csv` when the project uses estimate logging

## Failure handling
- If the driver cannot be linked to evidence, keep it as a question instead of a fact
- If the range is too wide, recommend splitting the work into smaller estimation chunks

## Related standards
- `shared-core/initialization/ESTIMATION_DRIVER_STANDARD.md`
- `shared-core/initialization/CAPABILITY_INVENTORY_STANDARD.md`

## When to improve/update
- When the same migration driver repeatedly causes estimate misses
- When a new source-to-Shopify pattern becomes a common effort driver
