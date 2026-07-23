# QC Test Case

> **Phase 1a (legacy, retained):** test case mặc định consolidate vào feature record `§Test Summary`. Skill này vẫn sinh standalone test-case set on demand (backward compat).

## Purpose

Generate test cases from a spec or ticket acceptance criteria. Covers happy path, edge cases, negative cases, business rule validation, and regression scenarios.

## When to Use

- Before QC testing begins on a feature (Mode A/B)
- Before TL review to verify test coverage (Mode C)
- When creating test plans for a release
- After spec or AC changes

## Prerequisites

- Feature spec or ticket with acceptance criteria
- `project-context/02_BUSINESS_RULES.md` — business rules relevant to the feature
- `project-context/03_ARCHITECTURE_AND_INTEGRATIONS.md` — integration details for integration tests
- `project-context/07_GLOSSARY.md` — for understanding domain terms in test cases

## Input

The spec or ticket acceptance criteria. Optionally: specific business rules to test.

## Steps

1. Read the spec/ticket — extract acceptance criteria as the primary test source
2. Read `project-context/02_BUSINESS_RULES.md` — identify business rules that apply to this feature
3. Generate test cases covering these categories:
   - **Happy path**: Each AC that represents normal flow → 1 TC per AC
   - **Edge cases**: Boundary values, empty states, max values, special characters
   - **Negative cases**: Invalid input, error states, unauthorized access, missing data
   - **Business rule validation**: Tests that verify BR interactions with the feature
   - **Integration points**: Tests that verify API contracts, webhook payloads, sync behavior
   - **Multi-store/multi-language** (if applicable): Test with different store scopes
   - **Mobile/responsive** (if applicable): Test at target breakpoints
4. Format each test case with: ID, name, precondition, steps, expected result, priority, type

## Output Format

> **Output language:** Produce all prose in the project's `output_language` (see `.ai/AGENTS.md`; default English). Code, identifiers, file paths, and technical terms (Magento, plugin, GraphQL, checkout, etc.) ALWAYS stay English.

```markdown
## Test Cases: {ticket_id / feature_name}

### TC-001: {test case name}
- **Precondition**: {what must be true before testing}
- **Steps**:
  1. {step}
  2. {step}
- **Expected Result**: {what should happen}
- **Priority**: High / Medium / Low
- **Type**: Functional / Business Rule / Edge Case / Negative / Integration
```

## Quality Checklist

- [ ] Every AC has at least one corresponding test case
- [ ] Edge cases include: empty state, max values, boundary conditions
- [ ] Negative cases include: invalid input, error states, unauthorized access
- [ ] Business rules from 02_BUSINESS_RULES.md that interact with this feature are tested
- [ ] Integration test cases reference actual API endpoints/contracts

## Escalation Rules

- Test cases reveal missing or conflicting business rules → flag for TL/BA review
- Test cases require data or environment not available → flag for TL
- Test cases for payment/checkout/shipping must be explicitly reviewed by TL

## Example

**Input**: AC for "B2B tiered pricing display" — Gold 20% off, Silver 12%, Bronze 5%.

**Output**:
```markdown
## Test Cases: FEAT-42 — B2B Tiered Pricing

### TC-001: Gold tier sees 20% discount on product page
- **Precondition**: Logged in as Gold B2B customer
- **Steps**: 1. Navigate to any product page 2. Observe displayed price
- **Expected Result**: Price shows 20% discount and final amount
- **Priority**: High | **Type**: Functional

### TC-002: Silver tier sees 12% discount
- **Precondition**: Logged in as Silver B2B customer
- **Steps**: Same as TC-001
- **Expected Result**: Price shows 12% discount
- **Priority**: High | **Type**: Functional

### TC-003: Retail customer sees standard price (no discount)
- **Precondition**: Logged in as retail customer (or guest)
- **Steps**: Same as TC-001
- **Expected Result**: Price shows standard price, no tier discount
- **Priority**: High | **Type**: Functional

### TC-004: Coupon + tiered pricing interaction (BR-003)
- **Precondition**: Logged in as Silver B2B customer
- **Steps**: 1. Add product to cart 2. Apply coupon code 3. Observe cart total
- **Expected Result**: Coupon discount applied, tiered discount removed (per BR-003)
- **Priority**: High | **Type**: Business Rule

### TC-005: Price correct in cart summary
- **Precondition**: Logged in as Gold B2B customer
- **Steps**: 1. Add product to cart 2. View cart 3. Observe line item price and total
- **Expected Result**: Tiered price matches product page, total reflects correct discount
- **Priority**: High | **Type**: Functional
```