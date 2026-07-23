# Generate Spec

> **Phase 1a (legacy, retained):** output mặc định giờ là `.ai/records/features/FEAT-*.md` (canonical record). Skill này vẫn sinh standalone spec/mini-spec on demand (backward compat).

## Purpose

Generate a feature spec (Mode A) or mini-spec (Mode B) from requirements, tickets, or client input. Produces structured, review-ready documentation with clear acceptance criteria.

## When to Use

- Writing a full feature spec for Mode A projects
- Writing a mini-spec for Mode B features
- Converting client requirements or meeting notes into structured spec
- Before ticket creation for complex features

## Prerequisites

- `AGENTS.md` — project context, workflow mode, project overview
- `project-context/01_PROJECT_OVERVIEW.md` — project basics
- `project-context/02_BUSINESS_RULES.md` — business rules to cross-reference
- `project-context/03_ARCHITECTURE_AND_INTEGRATIONS.md` — integration context
- `project-context/08_SPEC_TEMPLATE.md` — the spec template to use
- Requirement source (client request, ticket, meeting notes, change request)

## Input

The requirement source and the desired spec type (full or mini).

## Steps

1. Read `AGENTS.md §9` — workflow mode (Mode A → full spec, Mode B → mini-spec)
2. Read relevant `project-context/` files — understand business rules and architecture
3. Analyze the requirement source:
   - Extract user stories from the request
   - Identify business rules that apply
   - Note technical constraints or architecture implications
4. Determine spec type:
   - **Mode A**: Full spec with user stories, detailed AC, technical notes, dependencies, risks, out-of-scope
   - **Mode B**: Mini-spec with summary, AC, technical approach, risks
5. Generate the spec using `project-context/08_SPEC_TEMPLATE.md` as the structure
6. Mark assumptions clearly — if the requirement is ambiguous, note the assumption
7. Identify open questions — what needs clarification before development can start

## Output Format

> **Output language:** Produce all prose in the project's `output_language` (see `.ai/AGENTS.md`; default English). Code, identifiers, file paths, and technical terms (Magento, plugin, GraphQL, checkout, etc.) ALWAYS stay English.

Follow the spec template at `project-context/08_SPEC_TEMPLATE.md`. Ensure these sections are present:

```markdown
## Feature Overview
{ticket reference, feature name, type, priority}

## User Stories
{US-001, US-002, ...}

## Acceptance Criteria
{AC-001, AC-002, ... — each testable}

## Technical Notes
{implementation notes, affected files, architecture considerations}

## Dependencies
{dependency table}

## Risks & Unknowns
{risk table}

## Out of Scope
{explicit list}
```

## Quality Checklist

- [ ] Each AC is testable — can a QC person verify it independently?
- [ ] Business rules in AC are cross-referenced to 02_BUSINESS_RULES.md
- [ ] Assumptions are marked as `[ASSUMPTION: ...]`
- [ ] Open questions are listed with priority
- [ ] Out-of-scope items are explicit (prevents scope creep)
- [ ] Technical notes reference specific modules/files where applicable

## Escalation Rules

- Architecture decisions required → escalate to SA/TL (do not decide in spec)
- New integration requirements discovered → escalate to SA/TL
- Scope unclear or requirements conflict → escalate to PM/BA for clarification

## Example

**Input**: Client request "We need B2B customers to see different prices based on their tier. Gold gets 20% off, Silver 12%, Bronze 5%."

**Output (mini-spec)**:
```markdown
### Summary
Implement tiered pricing display for B2B customer segments.

### Acceptance Criteria
- [ ] AC-001: Gold tier customers see prices with 20% discount applied
- [ ] AC-002: Silver tier customers see prices with 12% discount applied
- [ ] AC-003: Bronze tier customers see prices with 5% discount applied
- [ ] AC-004: Retail (non-B2B) customers see standard prices unchanged
- [ ] AC-005: Discount is displayed as percentage off and final price

### Technical Approach
1. Check customer group in session
2. Apply tiered pricing override in price calculation
3. Update product page and cart to display tiered price

### Risks
- Cross-check with BR-003: coupons do not stack with tiered pricing
```