# create-discovery-questions

> Function (VI guidance). Copy vào `.ai/functions/create-discovery-questions.md`.

## Mục đích
Generate discovery questions từ client brief/raw input — structured theo business/technical/integration/constraint — để clarify requirement trước khi spec.
For migration projects, the questions must be derived from the migration assessment, not from generic domains alone.

## Trigger
- Prompt snippet: "Generate discovery questions từ: {brief/notes}. Structure: business, technical, integration, constraints, timeline. 3–5 mỗi category."

## Required inputs
- Client brief / SOW / meeting notes / raw input

## Required project files to read
- `AGENTS.md` (project context, stack)
- `project-context/01_PROJECT_OVERVIEW.md`, `02_BUSINESS_RULES.md` (existing rules để tránh hỏi lại)
- `CONTINUOUS_LEARNING.md` (client-rule đã biết)
- For migration projects also read `CURRENT_SYSTEM_ASSESSMENT.md`, `MIGRATION_GAP_ANALYSIS.md`, and `ESTIMATION_DRIVERS.md`.

## Dependencies
- Agent: ba
- Skill: `spec` (cho structure)
- Rule: `research-first.md`, `no-duplicate-knowledge.md`

## Execution steps
1. Đọc existing context để tránh hỏi gì đã biết.
2. Nếu migration: load assessment findings, gaps, complexity, drivers.
3. Phân loại raw input → gap.
4. Generate câu hỏi per category, specific + answerable.
5. Mark open question với owner (client/SA/PM).

## Expected output
Danh sách discovery questions (business/technical/integration/constraint/timeline) + open questions với owner.

## Evidence required
Question list lưu `.ai/specs/` hoặc ticket.

## Memory files to update
- `RESEARCH_NOTES.md` (unknown phát hiện)
- `CONTINUOUS_LEARNING.md` (client-rule mới sau khi có answer)

## Failure handling
- Input quá vague → flag, ask PM/BA补充; không generate câu hỏi generic.

## When to improve/update
- Khi một category question recurrent miss → thêm. Khi client trả lời revealing pattern → record `CONTINUOUS_LEARNING.md`.
