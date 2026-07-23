# assess-business-capabilities

> Function (English meta). Initialization phase. Convert technical findings into business capabilities.

## Purpose
Translate technical source findings into a business capability inventory that BA and TL can use for discovery, estimation, and client discussion.

## When to use
- After the technical audit is complete
- Before discovery questions are generated
- When the team needs a business-readable assessment instead of a code inventory

## Required inputs
- Technical audit output
- Source file references and evidence
- Current source behavior notes

## Required source system context
- Catalog, customers, B2B, orders, checkout, payments, shipping, promotions, CMS, SEO, integrations

## Required target platform context
- Shopify native capabilities
- Shopify configuration options
- Shopify apps, functions, extensions, and custom apps

## Required research profile
- Magento-to-Shopify migration profile
- Shopify capability and limitation profile
- B2B and checkout extension profile when the source has customer pricing or checkout customizations

## Required project files to read
- `shared-core/initialization/CAPABILITY_INVENTORY_STANDARD.md`
- `shared-core/initialization/templates/CURRENT_SYSTEM_ASSESSMENT.template.md`
- `shared-core/initialization/SOURCE_SYSTEM_AUDIT_GUIDE.md`

## Required agents
- `ba`
- `sa`

## Required skills
- `spec`
- `research-implementation`

## Required rules
- `research-first.md`
- `no-duplicate-knowledge.md`
- `planning-first.md`

## Expected output
- Business capability inventory grouped by domain
- Business meaning, customization level, and dependency notes for each capability

## Evidence required
- Source file references tied to each capability
- Mapping notes from technical finding to business capability

## Validation
- Every capability explains why the business cares
- Every capability has a source evidence link
- Every capability notes whether Shopify can support it natively, by config, app, function, or redesign

## Memory updates
- `RESEARCH_NOTES.md`
- `CONTINUOUS_LEARNING.md`
- `DECISIONS.md` when a capability mapping becomes a repeated project pattern

## Failure handling
- If a capability cannot be inferred safely, mark it `[UNKNOWN]`
- If the business meaning is ambiguous, keep the technical finding visible and ask follow-up questions later

## Related standards
- `shared-core/initialization/CAPABILITY_INVENTORY_STANDARD.md`
- `shared-core/initialization/ESTIMATION_DRIVER_STANDARD.md`

## When to improve/update
- When a technical finding repeatedly maps to the same business capability
- When a new capability pattern appears in Magento or Shopify projects
