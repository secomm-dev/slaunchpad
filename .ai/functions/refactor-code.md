# refactor-code

> Function (VI guidance). Copy vào `.ai/functions/refactor-code.md`. Refactor theo Engineering Standards — in-scope, preserve behavior, small patch.

## Mục đích
Refactor code để improve quality (SOLID/DRY/perf/readability) MÀ KHÔNG đổi behavior — theo Engineering Standards, trong scope, có test safety net.

## Trigger
- Prompt snippet: "Refactor {area}: improve theo SOLID/DRY/standards, preserve behavior, có test. Small patch; one PR one concern."

## Required inputs
- Refactor target + reason plus valid Mini-Spec (behavior-preservation contract) or Full Spec reference

## Required project files to read
- `.ai/project-context/engineering-standards/` (SOLID, DEVELOPMENT, REFACTORING, CODING, DESIGN_PATTERN)
- `CODING_RULES.md`, existing tests

## Required Engineering Standards (load trước)
ENGINEERING_PRINCIPLES → SOLID, DEVELOPMENT, REFACTORING, CODING, DESIGN_PATTERN + `technologies/{tech}`.

## Dependencies
- Agent: developer, tl
- Skill: `review-code`
- Rule: `backward-compatibility.md`, `engineering-standards-enforcement.md`, `evidence-required.md`
- Hook: `before-task`, `before-commit`

## Execution steps
1. Run SpecReadinessGuard; if invalid, return `IMPLEMENTATION BLOCKED` before adding/modifying tests or code. Then load standards; ensure test safety net tồn tại.
2. Refactor preserve behavior (extract method/class, rename, remove dead code) — small patch.
3. Run test → green (behavior unchanged).
4. Pre-review; evidence (test-result before/after).

## Expected output
Refactored code (green tests) + refactor summary (what/why/risk).

## Evidence required
`.ai/evidence/{task}/`: test-result (before/after green) + diff summary.

## Memory files to update
- `CONTINUOUS_LEARNING.md`, `project-context/06` (tech debt resolved)

## Failure handling
- Behavior change → REJECT (refactor phải preserve behavior); revert.
- No test safety net → add test trước refactor; không refactor blind.
- Major refactor → architecture-review-required (SA review) trước.

## When to improve/update
- Khi refactor pattern recurrent → standardize; record.
