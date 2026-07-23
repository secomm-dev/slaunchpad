# Analyze Ticket

> **Phase 1a (legacy, retained):** work-item unit mặc định giờ là canonical `.ai/records/features/FEAT-*.md` / `bugs/BUG-*.md`. Skill này vẫn analyze ticket/task on demand (backward compat); cho work mới, nên tạo canonical record trực tiếp.

## Purpose

Analyze a ticket or task to understand requirements, identify risks and affected areas, and recommend an implementation approach with effort estimate.

## When to Use

- Before starting any development work (Modes A, B, C)
- When a ticket is unclear or missing details
- During sprint planning to validate ticket readiness
- Before creating an implementation plan

## Prerequisites

- `AGENTS.md` — project context, workflow mode, escalation rules, high-risk areas
- Relevant `project-context/` files (01_PROJECT_OVERVIEW.md, 02_BUSINESS_RULES.md, 04_CUSTOM_MODULES_AND_CODE_AREAS.md, 06_KNOWN_CONSTRAINTS_AND_RISKS.md)
- Ticket or task description with acceptance criteria

## Input

The ticket/task to analyze — paste the ticket description, acceptance criteria, and any attached context.

## Steps

1. Read `AGENTS.md §1–§2, §9, §12` — project context, workflow mode, tech stack, restrictions (skip if already loaded this run)
2. Read relevant `project-context/` files — business rules, known constraints, code areas
3. Analyze the ticket:
   - What is being asked? Is the requirement clear?
   - Are the acceptance criteria testable?
   - What is the scope? What is explicitly out of scope?
4. Identify affected code areas: files, modules, components, integrations
5. Identify dependencies: other tickets, integrations, teams, external factors
6. Identify risks:
   - Business logic risks (does this change affect pricing, checkout, orders?)
   - Data integrity risks (does this change data structure or flow?)
   - Performance risks (will this affect page load, API response time?)
   - Security risks (does this touch auth, PII, payment data?)
7. Check the high-risk areas list in `AGENTS.md` Section 12 — flag if ticket touches any
8. Determine effort estimate range based on complexity, affected areas, and risks
9. Output structured analysis

## Output Format

> **Output language:** Produce all prose in the project's `output_language` (see `.ai/AGENTS.md`; default English). Code, identifiers, file paths, and technical terms (Magento, plugin, GraphQL, checkout, etc.) ALWAYS stay English.

```markdown
## Ticket Analysis: {ticket_id}

### Summary
{1-2 sentence summary of what the ticket asks}

### Scope
- **Files/areas affected**: {list of files, modules, components}
- **Dependencies**: {other tickets, integrations, teams}

### Risks
- [ ] {risk description} — severity: high/medium/low

### Missing Information
- [ ] {what's missing to proceed}

### Approach
{Recommended implementation approach — 3-5 steps}

### Effort Estimate
{Range: Xh - Yh}

### Escalation Required
{Yes/No — if yes, which tier and reason}
```

## Quality Checklist

- [ ] All high-risk areas from AGENTS.md Section 12 checked against ticket scope
- [ ] Business rules from project-context/02_BUSINESS_RULES.md cross-referenced
- [ ] Dependencies identified (not just "none")
- [ ] Missing information is specific (not "more details needed")
- [ ] Effort estimate includes testing and documentation time

## Escalation Rules

- If ticket touches high-risk areas (payment, checkout, shipping, order, DB migration, security) → flag for Tier 2 escalation before implementation
- If effort estimate is uncertain (>50% variance possible) → flag for TL review
- If ticket scope is unclear or requirement is ambiguous → return to PM/BA for clarification

## Example

**Input**: Ticket "Fix checkout price display — prices show as $10.00 instead of $10.50 for B2B customers"

**Output**:
```markdown
## Ticket Analysis: BUG-1234

### Summary
B2B customers see incorrect prices on checkout summary — tiered discount not applied before display.

### Scope
- **Files/areas affected**: Acme_B2BPricing module, checkout template, cart total calculation
- **Dependencies**: None

### Risks
- [ ] Pricing calculation error — severity: high
- [ ] Wrong discount tier applied — severity: high

### Missing Information
- [ ] Does this affect only the display or the actual charged amount?

### Approach
1. Reproduce the issue with a B2B test account
2. Trace price calculation through Acme_B2BPricing module
3. Compare cart total vs order total discrepancy
4. Fix and test with multiple B2B tiers

### Effort Estimate
4h - 8h

### Escalation Required
Yes — Tier 2. Pricing changes affect revenue and require SA review.
```