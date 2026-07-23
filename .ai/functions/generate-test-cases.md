# generate-test-cases

> Function (VI guidance). Copy vào `.ai/functions/generate-test-cases.md`.

## Mục đích
Generate QC test case từ spec/AC — happy path, edge case, negative, business rule validation. Orchestrator cho `testcase` skill.

## Trigger
- Prompt snippet: "Generate test case cho {ticket}: happy path (mỗi AC 1 TC), edge case, negative, business rule (02). Output TC-NNN format."

## Required inputs
- Feature spec + acceptance criteria

## Required project files to read
- `project-context/02_BUSINESS_RULES.md` (rule interaction)
- `templates/qc-testcase-template.md`

## Dependencies
- Skill: `testcase`
- Agent: qc
- Rule: `evidence-required.md`

## Execution steps
1. Đọc spec/AC + business rule.
2. Chạy `testcase` skill.
3. Generate: happy path (mỗi AC), edge (empty/boundary/max), negative (invalid/unauthorized), business rule validation.
4. Output TC-NNN table.

## Expected output
Test case list (TC table) + coverage note.

## Evidence required
Test case list lưu `.ai/evidence/{ticket}/test-cases.md` hoặc `qc-testcase-template`.

## Memory files to update
- `CONTINUOUS_LEARNING.md` (testing gotcha)

## Failure handling
- AC không testable → flag spec issue, trả BA.
- Business rule interaction complex → thêm test case combination.

## When to improve/update
- Khi một edge case recurrent miss (e.g., concurrent cart) → thêm default + record.
