# DOCUMENTATION_STANDARD

> Engineering standard — Documentation engineering. English.

## Purpose / Scope / Applicability
Keep docs accurate, current, and useful. Applies to `project-context/`, `docs/`, `AGENTS.md`, README, ADRs, release notes.

## Mandatory Rules
- `project-context/` reflects the delivered state after each release (Tracked Expectation).
- Architecture/integration/module changes update the relevant context file in the same PR.
- ADRs (`DECISIONS.md`) for significant decisions (append-only; supersede, don't delete).
- No stale docs: resolved issues marked resolved; version numbers match actual state.
- Link, don't copy (single source of truth).

## Recommended Practices
- Document the *why* and *constraints*, not just the *what*.
- Keep README/AGENTS short and pointed; detail in context files.

## Anti-patterns
Stale docs contradicting code; duplicated content across files; empty sections; orphaned references.

## Validation Checklist
- [ ] Context reflects current release
- [ ] Changes update docs in same PR
- [ ] ADRs recorded; resolved issues marked
- [ ] No duplication (links used)

## Related
**Agents**: ba, sa, project-auditor · **Skills**: spec, update-memory, status · **Functions**: summarize-progress, prepare-pr · **Rules**: no-duplicate-knowledge, memory-update · **Audits**: Documentation · **Memory**: DECISIONS, CONTINUOUS_LEARNING
