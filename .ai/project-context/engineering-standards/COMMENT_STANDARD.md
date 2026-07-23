# COMMENT_STANDARD

> Engineering standard — Code Comments. English.

## Purpose / Scope / Applicability
Comments explain *why*, not *what*. Applies to all code.

## Mandatory Rules
- Comment the *why* (decisions, trade-offs, non-obvious constraints), not the *what* (the code already says that).
- Every `TODO`/`FIXME`/`HACK` carries a ticket id + owner + reason.
- Public API / service contracts / complex business rules get intent comments.
- No commented-out code (delete it; git remembers).

## Recommended Practices
- If a comment explains *what*, refactor until the code is self-explanatory.
- Reference ADRs/specs for non-obvious decisions (`// see DECISIONS.md ADR-0007`).

## Anti-patterns
Commented-out code; stale comments contradicting code; noise comments (`// increment i`); TODOs without owners.

## Validation Checklist
- [ ] Comments explain why; no commented-out code
- [ ] TODOs have ticket + owner
- [ ] Complex rules/API have intent comments
- [ ] No stale/misleading comments

## Related
**Agents**: developer, tl · **Skills**: review-code · **Functions**: review-code, audit-code-quality · **Rules**: no-duplicate-knowledge · **Audits**: Code Quality, Documentation
