# TESTING_STANDARD

> Engineering standard — Testing engineering. English.

## Purpose / Scope / Applicability
Prove correctness and prevent regression. Applies to all logic changes (stronger for Mode A/B; lighter for Mode C with TL judgment).

## Mandatory Rules
- Cover happy + edge + error cases (not just happy path).
- Bug fixes include a reproducing test before the fix.
- Tests follow Arrange-Act-Assert; isolate (no cross-test order dependency).
- QC acceptance criteria map to test cases (Mode A/B).
- Don't skip tests to save time; don't disable assertions.

## Recommended Practices
- Test behavior, not implementation (refactor-safe).
- Integration tests for boundaries (API contract, payment sandbox, webhook HMAC).
- Keep the test suite fast; tag long tests.

## Anti-patterns
Happy-path-only tests; brittle tests tied to implementation; shared mutable state; disabled assertions; no regression test for a fixed bug.

## Validation Checklist
- [ ] Happy + edge + error covered; AC mapped to tests
- [ ] Bug fix has reproducing test
- [ ] Tests isolated + Arrange-Act-Assert
- [ ] Suite passes; no disabled assertions

## Related
**Agents**: qc, developer · **Skills**: testcase, review-code · **Functions**: generate-test-cases, generate-unit-test, prepare-qc-handoff · **Rules**: evidence-required · **Hooks**: before-pr · **Audits**: Code Quality, Testing · **Memory**: CONTINUOUS_LEARNING
