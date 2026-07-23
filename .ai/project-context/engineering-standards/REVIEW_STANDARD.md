# REVIEW_STANDARD

> Engineering standard — Review. English.

## Purpose / Scope / Applicability
Catch issues before merge; enforce standards. Applies to every PR (all modes).

## Mandatory Rules
- Every PR: AI pre-review (no critical findings) **then** TL review.
- Review against: implementation plan, AC, business rules (`project-context/02`), coding standards (`CODING_RULES.md` + engineering standards), high-risk areas (`AGENTS.md` §12).
- Scope check: no out-of-scope changes; one PR = one concern.
- Regression risks identified; tests verified.
- Evidence required (pre-review output attached to PR).

## Recommended Practices
- Pre-review catches ~60–70%; TL focuses on what AI missed (architecture, business logic, risk).
- Comment on patterns, not just lines; link to standards/ADRs.

## Anti-patterns
Skipping pre-review to save time; rubber-stamp review; reviewing only happy path; ignoring scope creep.

## Validation Checklist
- [ ] Pre-review passed (no critical); TL approved
- [ ] Reviewed against standards + business rules + high-risk areas
- [ ] Scope clean; regression risks noted
- [ ] Evidence attached

## Related
**Agents**: tl, security-reviewer, magento-reviewer, shopify-reviewer · **Skills**: review-code, security-review · **Functions**: review-code, prepare-pr, audit-code-quality · **Rules**: engineering-standards-enforcement, evidence-required · **Hooks**: before-pr · **Audits**: Code Quality, AI Output
