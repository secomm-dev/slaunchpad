# review-plugin-preference-impact

> Function (VI). Magento platform. Lifecycle: **platform**. Plugin vs preference override impact — upgrade-safety, conflict, side-effect.

## Purpose
Assess impact của một plugin hoặc preference override — side effect, conflict, upgrade-safety, performance overhead. Đảm bảo dùng đúng mechanism (plugin > preference).

## When to use
- Khi `di.xml` thêm plugin hoặc preference.
- Code review override change.
- Upgrade compatibility check.

## Trigger
- Prompt snippet: "Review plugin/preference impact: {di.xml change}. Side effect, conflict, upgrade-safety, perf overhead. Prefer plugin over preference."

## Required inputs
- `di.xml` diff (plugin/preference declaration)

## Required project files to read
- `project-context/09_MAGENTO_MODULE_MAP.md`

## Required agents / skills / rules / hooks
- Agents: magento-reviewer, sa
- Skills: `magento-module-analysis`
- Rules: `backward-compatibility.md`, `engineering-standards-enforcement.md`
- Hooks: `before-pr`

## Required memory / evidence
- Memory: `project-context/09`, `DECISIONS.md` (override decision)
- Evidence: `.ai/evidence/{task}/plugin-pref-review.md`

## Execution steps
1. Context (09) 2. Memory (DECISIONS) 3. Rules (backward-compat) 4. Skill 5. Agent 6. Research di.xml + target class 7. Review: plugin (type=before/after/around, sortOrder, disabled?), preference (target class — core? will break upgrade?), side effect (around plugin missing proceed?), conflict (multiple plugin cùng method?), perf (around overhead) 8. Validate: prefer plugin; preference only if no alternative + ADR 9. Evidence 10. Update 09/DECISIONS 11. Next

## Output format
Plugin/pref review: mechanism used, target class, side-effect, conflict, upgrade-safety, perf, verdict.

## Failure handling
- Preference on core class → block (upgrade risk); require plugin or ADR + SA approval.
- Around plugin missing `proceed()` → block (breaks original).
- SortOrder conflict → flag.

## Related audits / standards
- Audits: Magento, Code Quality
- Standards: MAGENTO_STANDARD (Plugins vs Preferences, DI, Upgrade-safe), ARCHITECTURE, REVIEW
