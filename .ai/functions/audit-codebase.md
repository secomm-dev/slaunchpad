# audit-codebase

> Function (English meta). Initialization phase. Technical audit only for a migration project.

## Purpose
Inventory the source codebase and capture evidence for version, theme, modules, integrations, checkout, payments, shipping, SEO, and technical debt.

## When to use
- Migration or upgrade initialization
- When the team needs a source-system technical baseline before business assessment
- When the request is specifically "audit the codebase" or "audit existing source"

## Required inputs
- Source repo access
- Config files, composer manifests, module registry, theme files, cron/CLI traces

## Required source system context
- Magento version and edition
- Theme and overrides
- Enabled modules and custom modules
- Custom integrations, redirects, and automation

## Required target platform context
- Target platform name and plan
- Shopify capabilities available for the migration
- Any known plan constraints that affect parity

## Required research profile
- Magento audit profile
- Shopify migration profile
- Vendor verification for third-party modules and platform limits

## Required project files to read
- `shared-core/initialization/SOURCE_SYSTEM_AUDIT_GUIDE.md`
- `shared-core/initialization/templates/CURRENT_SYSTEM_ASSESSMENT.template.md`
- `shared-core/initialization/CAPABILITY_INVENTORY_STANDARD.md`

## Required agents
- `sa`
- `legacy-code-auditor` when the source has custom code or legacy debt

## Required skills
- `research-implementation`
- `audit-architecture`
- `audit-code-quality`

## Required rules
- `research-first.md`
- `security-first.md`
- `evidence-required.md`

## Expected output
- Technical audit section for `CURRENT_SYSTEM_ASSESSMENT.md`
- Evidence-backed inventory of source platform artifacts

## Evidence required
- File references
- Command output
- Config snapshots
- Redirect files
- Screenshots when source UI is relevant

## Validation
- Every major source area marked `[VERIFIED]`, `[ASSUMPTION]`, or `[UNKNOWN]`
- No unsupported claims about target parity
- All high-risk areas called out explicitly

## Memory updates
- `RESEARCH_NOTES.md`
- `project-context/06_KNOWN_CONSTRAINTS_AND_RISKS.md`
- `CONTINUOUS_LEARNING.md` for repeated migration findings

## Failure handling
- No source access: stop at `[UNKNOWN]` and flag missing workspace path
- Partial access: audit what is available and mark missing areas `[UNKNOWN]`
- Conflicting evidence: keep both claims visible and mark as unresolved

## Related standards
- `shared-core/initialization/CAPABILITY_INVENTORY_STANDARD.md`
- `shared-core/initialization/TARGET_PLATFORM_MAPPING_STANDARD.md`

## When to improve/update
- When a repeatable Magento source artifact should become a standard audit check
- When a new Magento or Shopify platform limitation changes the audit checklist
