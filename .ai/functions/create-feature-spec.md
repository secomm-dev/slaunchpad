# create-feature-spec

> Function (VI guidance). Copy vào `.ai/functions/create-feature-spec.md`.

## Mục đích
Generate feature spec (Mode A) hoặc mini-spec (Mode B) từ requirement/ticket — user story, AC, technical note, edge case.

## Trigger
- Command: `/spec`
- Prompt snippet: "Generate {feature-spec|mini-spec} cho: {requirement}. Read project-context/02 business rules. Output per template."

## Required inputs
- Requirement / ticket / change request
- (Optional) discovery question answers, design

## Required project files to read
- `project-context/02_BUSINESS_RULES.md` (AC phải respect business rule)
- `project-context/06` (known constraint/risk affect scope)
- `templates/feature-spec-template.md` / `mini-spec-template.md`

## Dependencies
- Skill: `spec`
- Agent: ba, sa
- Rule: `planning-first.md`, `no-duplicate-knowledge.md`

## Execution steps
1. Đọc business rule + constraint.
2. Chạy `spec` skill.
3. Generate user story + testable AC + technical note + edge case + out-of-scope.
4. SA/TL review trước khi giao dev.

## Expected output
Spec document (feature-spec hoặc mini-spec) theo template.

## Evidence required
Spec lưu `.ai/specs/{feature}.md`; SA/TL review signoff.

## Memory files to update
- `DECISIONS.md` (scope decision nếu significant)
- `CONTINUOUS_LEARNING.md` (convention phát hiện)

## Failure handling
- AC không testable → specific hóa (không accept "works correctly").
- Business rule conflict → flag, escalate.

## When to improve/update
- Khi template thiếu section cần (e.g., accessibility) → update template + record.
