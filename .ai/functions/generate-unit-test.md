# generate-unit-test

> Function (VI guidance). Copy vào `.ai/functions/generate-unit-test.md`. Generate unit tests theo Testing standard.

## Mục đích
Generate unit tests cho logic mới/sửa — theo Testing standard (Arrange-Act-Assert, happy+edge+error, isolated), bao trùm AC.

## Trigger
- Prompt snippet: "Generate unit tests cho {class/function}: Arrange-Act-Assert, happy+edge+error, isolated, cover AC. Read TESTING_STANDARD."

## Required inputs
- Target code (class/function) + AC

## Required project files to read
- `.ai/project-context/engineering-standards/TESTING_STANDARD.md`, `CODING_RULES.md`

## Required Engineering Standards (load trước)
ENGINEERING_PRINCIPLES → TESTING, DEVELOPMENT (testing-strategy) + `technologies/{tech}`.

## Dependencies
- Agent: developer, qc
- Skill: `testcase`
- Rule: `evidence-required.md`, `engineering-standards-enforcement.md`

## Execution steps
1. Load TESTING standard + target code.
2. Identify cases: happy, edge (boundary/empty/null), error/exception.
3. Generate Arrange-Act-Assert tests; isolated (no cross-test dependency).
4. For bug fix: add reproducing test trước fix.
5. Run suite → green.

## Expected output
Unit test file(s) + coverage note (cases covered).

## Evidence required
Test-result (green) lưu `.ai/evidence/{task}/unit-test-result.md`.

## Memory files to update
- `CONTINUOUS_LEARNING.md` (testing gotcha)

## Failure handling
- AC không testable → flag spec issue.
- Test brittle (tied to impl) → rewrite test behavior.

## When to improve/update
- Khi test pattern recurrent → standardize fixture/factory; record.
