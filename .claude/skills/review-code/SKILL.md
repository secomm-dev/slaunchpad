# Pre-review

## Purpose

AI pre-review of code changes before requesting TL review. Catches issues early — scope violations, security concerns, missing error handling, and business rule conflicts.

## When to Use

- Before every TL code review (all workflow modes)
- After completing development, before creating a PR
- After fixing issues from a previous review round
- When code touches high-risk areas

## Prerequisites

- `AGENTS.md` — coding standards (Section 7.2), high-risk areas (Section 12), AI pre-review requirements (Section 8.3)
- Implementation plan or approach note for the task
- Ticket acceptance criteria or spec
- `project-context/02_BUSINESS_RULES.md` — business rules to verify against
- The code changes to review (diff or files)

## Input

The code changes (diff or file list) and the implementation plan/ticket they implement.

## Steps

1. Read `AGENTS.md §7.2, §8.3, §12` — coding standards, pre-review requirements, high-risk areas (skip if already loaded this run)
2. Read the implementation plan or ticket AC — understand what was supposed to be done
3. Review code changes against these checks:
   - **Plan match**: Does the code match the implementation plan or approach note?
   - **Scope**: Are there changes outside the requested scope?
   - **Hardcoded values**: Are there credentials, URLs, or environment-specific values?
   - **Error handling**: Is error handling present for all error paths?
   - **Business rules**: Are business rules from project-context/ respected?
   - **Security**: Are there obvious security issues (SQL injection, XSS, auth bypass)?
   - **Performance**: Are there N+1 queries, unnecessary loops, missing indexes?
   - **Standards**: Are stack-specific coding standards from AGENTS.md Section 7.2 followed?
4. Check high-risk areas list — flag if changes touch any
5. List regression risks — what else might this change affect?
6. Suggest test cases for the changes

## Output Format

> **Output language:** Produce all prose in the project's `output_language` (see `.ai/AGENTS.md`; default English). Code, identifiers, file paths, and technical terms (Magento, plugin, GraphQL, checkout, etc.) ALWAYS stay English.

```markdown
## AI Pre-review: {ticket_id / PR description}

### Summary
{Changes overview — what was changed and why}

### Findings
#### Critical (must fix)
- {finding description}

#### Warnings (should fix)
- {finding description}

#### Notes (consider)
- {finding description}

### Scope Check
- [ ] Changes match implementation plan
- [ ] No out-of-scope modifications

### Regression Risks
- {risk description}

### Suggested Tests
- {test description}

### Recommendation
{PASS / PASS WITH WARNINGS / NEEDS FIX}
```

## Quality Checklist

- [ ] Critical findings are truly blocking (not just preferences)
- [ ] Each finding includes location (file + line)
- [ ] Business rules from project-context/02 cross-referenced where applicable
- [ ] High-risk area check performed against AGENTS.md Section 12
- [ ] Security check covers: SQL injection, XSS, CSRF, auth bypass, hardcoded secrets

## Escalation Rules

- High-risk area changes detected → flag for Tier 2 escalation
- Critical security finding → STOP, notify TL immediately, do not merge
- Multiple warnings in same category → flag pattern for TL awareness

## Example

**Input**: PR implementing B2B tiered pricing. Diff shows changes to price calculation and cart template.

**Output**:
```markdown
## AI Pre-review: PR-42 — B2B Tiered Pricing

### Summary
Adds tiered pricing (Gold/Silver/Bronze) to product page and cart.

### Findings
#### Critical (must fix)
- **Hardcoded B2B tier values** in `app/code/Acme/B2BPricing/Model/Price.php:45` — tiers and discounts should be configurable, not hardcoded

#### Warnings (should fix)
- **Missing cache metadata** on product page block — tiered prices will not be cached correctly for different customer groups

#### Notes (consider)
- Consider adding cache key for customer group to avoid stale pricing

### Scope Check
- [x] Changes match implementation plan
- [x] No out-of-scope modifications

### Regression Risks
- Category page price display may show wrong tier if cache not handled

### Suggested Tests
- Test price display as Gold/Silver/Bronze/Retail customer
- Test with coupon + tiered pricing combination (BR-003)

### Recommendation
NEEDS FIX — address hardcoded values before TL review
```