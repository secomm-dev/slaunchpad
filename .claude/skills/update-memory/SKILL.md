# Project Context Update

## Purpose

Generate a diff for updating `project-context/` files after project changes — new features, bug fixes, integrations, or releases. Produces a human-reviewable diff, never overwrites files directly.

## When to Use

- After a release or deployment (post-release context update)
- When new business rules are discovered or changed
- When a new integration is added or an existing one changes
- When architecture is modified (new module, refactored flow)
- When known issues are resolved or new ones discovered
- Before a new developer joins the project

## Prerequisites

- Current `project-context/` files (the existing versions)
- Release notes, PR descriptions, or change log for what changed
- Ticket list or feature list for the release

## Input

Description of what changed — release notes, PR list, or ticket list for the update period.

## Steps

1. Read the `project-context/` files flagged by your change map (step 2) — scan others only if a stale-reference check fails
2. Identify what changed (from input):
   - New business rules → affects `02_BUSINESS_RULES.md`
   - Architecture changes → affects `03_ARCHITECTURE_AND_INTEGRATIONS.md`
   - New/removed modules → affects `04_CUSTOM_MODULES_AND_CODE_AREAS.md`
   - Integration changes → affects `03_ARCHITECTURE_AND_INTEGRATIONS.md` and `05_API_CONTRACTS.md`
   - Resolved/new issues → affects `06_KNOWN_CONSTRAINTS_AND_RISKS.md`
   - New terminology → affects `07_GLOSSARY.md`
3. Determine which context files need update — generate a diff for each affected file
4. Check for stale information — if a known issue was resolved, mark it; if a risk was mitigated, update it
5. Output: what to add, what to modify, what to remove per file

## Output Format

> **Output language:** Produce all prose in the project's `output_language` (see `.ai/AGENTS.md`; default English). Code, identifiers, file paths, and technical terms (Magento, plugin, GraphQL, checkout, etc.) ALWAYS stay English.

```markdown
## Context Update: {release_name / date}

### Files to Update
| File | Action | Summary |
|------|--------|---------|
| `02_BUSINESS_RULES.md` | Add | {new rules} |
| `06_KNOWN_CONSTRAINTS_AND_RISKS.md` | Modify | {updated risks} |

### Changes Per File

#### `project-context/02_BUSINESS_RULES.md`
**Add**:
- BR-011: {new rule}
**Modify**:
- BR-003: {updated description}
**Remove**:
- (none)

#### `project-context/06_KNOWN_CONSTRAINTS_AND_RISKS.md`
**Resolved**: {issue description}
**New risk**: {risk description}
```

## Quality Checklist

- [ ] Every changed business rule is reflected in `02_BUSINESS_RULES.md`
- [ ] Resolved issues are marked as resolved (not deleted — keep historical record)
- [ ] New risks are documented with severity and mitigation
- [ ] Stale information is flagged (old module list, outdated architecture, deprecated integrations)
- [ ] Output is a diff for human review — not a direct file overwrite

## Escalation Rules

- If business rule changes affect payment/checkout/shipping/order → flag for SA/TL review
- If architectural changes contradict previous key decisions → flag for SA review
- Do NOT apply changes automatically — always output for human review

## Example

**Input**: Release v2.1.0 includes: new B2B pricing rules (tiered discounts), Stripe webhook update, resolved checkout timeout bug.

**Output**:
```markdown
## Context Update: v2.1.0 Release

### Files to Update
| File | Action | Summary |
|------|--------|---------|
| `02_BUSINESS_RULES.md` | Add | 3 new B2B pricing rules |
| `03_ARCHITECTURE_AND_INTEGRATIONS.md` | Modify | Stripe webhook endpoint changed |
| `05_API_CONTRACTS.md` | Modify | Stripe webhook payload format |
| `06_KNOWN_CONSTRAINTS_AND_RISKS.md` | Modify | Checkout timeout resolved, new Stripe dependency risk |

### Changes Per File

#### `02_BUSINESS_RULES.md`
**Add**:
- BR-008: Gold tier → 20% off list price
- BR-009: Silver tier → 12% off list price
- BR-010: Bronze tier → 5% off list price

#### `06_KNOWN_CONSTRAINTS_AND_RISKS.md`
**Resolved**: "Checkout timeout on payment step for orders >$500" — fixed by Stripe webhook optimization
**New risk**: "Stripe webhook payload format may change on API version upgrade — dependency risk added"
```