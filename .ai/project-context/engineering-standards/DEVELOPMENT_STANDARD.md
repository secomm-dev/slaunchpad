# DEVELOPMENT_STANDARD

> Engineering standard — Development (consolidated). English (technical). Covers DI, Error/Exception/Logging, Performance-coding, Secure-coding, Testing-strategy, Refactoring, Tech Debt, Code Review. Enforcement items live in `CODING_RULES.md`.

## Purpose / Scope / Applicability
How code is written day-to-day. Applies to every implementation task. Technology-specific overrides in `technologies/{tech}.md`.

## Mandatory Rules (cross-cutting)
- **Dependency Injection**: inject dependencies (Magento DI/Laravel container); no `new` of services in logic; no hidden globals.
- **Error/Exception/Logging**: never swallow exceptions; catch the specific type; no catch-all without rethrow; APIs use the standard error envelope; mask PII/secrets in logs (`j***@example.com`).
- **Performance-coding**: no N+1 (eager-load/batch); large reads paginated; index hot columns; cache keys include all affecting params; external calls off the checkout critical path.
- **Secure-coding**: validate all input; escape all output; parameterized queries only; server-side revalidate client-supplied price/stock/coupon; never log raw card/secret.
- **Testing-strategy**: cover happy + edge + error; add a reproducing test for bug fixes; don't skip tests to save time.
- **Refactoring**: in-scope only; one PR = one concern; don't mix refactor with feature; preserve behavior.
- **Tech Debt**: record debt in `project-context/06` with severity + owner; schedule, don't accumulate silently.
- **Code Review**: every PR passes AI pre-review then TL review; evidence required.

## Recommended Practices
- Functions ≤ ~30 lines, ≤ ~3 params; early returns over nesting; no boolean flag params.
- Prefer composition over inheritance; favor pure functions where feasible.
- Name things for the reader (Least Surprise); remove dead/commented code.
- Commit messages: `type(scope): description`.

## Anti-patterns
- Swallowed exceptions; catch-all without rethrow; hardcoded env values; N+1 queries; string-concatenated SQL; logic in controllers/templates; mixed refactor+feature in one PR; uncommented `TODO/FIXME` without ticket; scope creep.

## Validation Checklist
- [ ] No swallowed exceptions; errors use standard envelope
- [ ] No N+1 on hot path; cache keys complete
- [ ] Input validated; output escaped; queries parameterized; no secret in logs
- [ ] Tests cover happy + edge + error; bug fix has reproducing test
- [ ] Change is single-concern, in-scope; dead code removed
- [ ] Pre-review passed; evidence saved

## Examples (short)
- ❌ `catch (\Exception $e) {}` → ✅ catch specific type, log masked, return error envelope.
- ❌ looped `$repo->find($id)` (N+1) → ✅ `$repo->findByIds($ids)` (batch).

## Technology overrides
See `technologies/{magento,laravel,...}.md` (e.g., Magento: plugins over preferences; Laravel: service/action + Eloquent).

## Related
- **Agents**: developer, tl, magento-reviewer, shopify-reviewer, security-reviewer, performance-reviewer
- **Skills**: review-code, security-review, testcase, magento-checkout-impact
- **Functions**: implement-task, review-code, refactor-code, generate-unit-test, audit-code-quality
- **Rules**: planning-first, security-first, project-conventions-first, backward-compatibility, engineering-standards-enforcement
- **Hooks**: before-task, before-commit, before-pr
- **Audits**: Code Quality, Security, Performance
- **Memory**: CURRENT_STATE, CONTINUOUS_LEARNING, project-context/06
- **Project types**: all
