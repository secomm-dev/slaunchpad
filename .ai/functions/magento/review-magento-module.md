# review-magento-module

> Function (VI). Magento platform. Lifecycle: **platform**. Module-level review: plugin chain, preference, observer, dependency, service contract.

## Purpose
Review một Magento module ở mức cấu trúc — plugin chain, preference override, observer conflict, dependency, service contract đúng — trước khi change hoặc approve.

## When to use
- Trước khi modify/add một Magento module.
- Code review module-level change.
- Module compatibility check (upgrade).

## Trigger
- Prompt snippet: "Review Magento module {Vendor_Module}: plugin chain, preference, observer, dependency, service contract. Read 09."

## Required inputs
- Module name (`Vendor_Module`) hoặc module path

## Required project files to read
- `project-context/09_MAGENTO_MODULE_MAP.md`, `04_CUSTOM_MODULES_AND_CODE_AREAS.md`

## Required agents / skills / rules / hooks
- Agents: magento-reviewer, sa
- Skills: `magento-module-analysis`
- Rules: `backward-compatibility.md`, `project-conventions-first.md`
- Hooks: `before-pr`

## Required memory / evidence
- Memory: `project-context/09` (update nếu change), `CONTINUOUS_LEARNING.md`
- Evidence: `.ai/evidence/{task}/module-review.md`

## Execution steps (11-step)
1. Load context (09/04) 2. Memory (DECISIONS) 3. Rules (backward-compat) 4. Skill (magento-module-analysis) 5. Agent (magento-reviewer) 6. Research module structure 7. Review: plugin chain (before/after/around), preference (avoid unless no alternative), observer (idempotent, fast), dependency (no circular), service contract (interface, repository) 8. Validate: no core hack, upgrade-safe 9. Evidence (report) 10. Update 09 11. Next: escalate nếu Tier 2 area

## Output format
Module review: finding (plugin/pref/observer/dep/contract) + severity + verdict.

## Failure handling
- Preference override on core class → flag (breaks upgrade); prefer plugin.
- Circular dependency → flag SA.
- No service contract (direct model access) → flag.

## Related audits / standards
- Audits: Magento, Code Quality
- Standards: ENGINEERING_PRINCIPLES → MAGENTO_STANDARD (Service Contracts, Plugins vs Preferences, DI, Upgrade-safe), DEVELOPMENT, REVIEW
