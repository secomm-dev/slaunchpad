# research-implementation

> Function (VI guidance). Copy vào `.ai/functions/research-implementation.md`.

## Mục đích
Research-first phase trước implement: đọc blueprint + project files + conventions + dependencies → identify unknown → ghi `RESEARCH_NOTES.md`. Bắt buộc trước khi lập plan chi tiết.

## Trigger
- Prompt snippet: "Research cho {task}: đọc blueprint + relevant files + conventions + deps. Output: verified / assumption / [EXTERNAL: verify] / unknown → RESEARCH_NOTES."

## Required inputs
- Task/spec (đã analyze)

## Required project files to read
- `PROJECT_AI_BLUEPRINT.md`
- `project-context/01`–`06`, stack `09`–`12`
- Source code vùng sẽ thay đổi (conventions, existing impl)
- `DECISIONS.md`, `LESSONS_LEARNED.md`

## Dependencies
- Rule: `research-first.md`, `planning-first.md`
- **Research Engine**: `.ai/research/RESEARCH_ENGINE.md` + selected profile (`.ai/research/research-profile.md`) — trigger, sequence, stop condition, output, cache
- Agent: developer, sa
- Instinct: #1, #3, #5

## Execution steps
1. Đọc blueprint + relevant project-context + source.
2. Inspect existing conventions/implementation (đã có plugin/function chưa).
3. Inspect dependencies (version, contract, breaking risk).
4. Phân loại finding: `[VERIFIED]` / `[ASSUMPTION]` / `[EXTERNAL: verify]` / `[UNKNOWN]`.
5. Ghi `RESEARCH_NOTES.md`; flag unknown → escalate/research thêm.

## Expected output
`RESEARCH_NOTES.md` entry: mục tiêu + verified + assumption + external (verify) + unknown + decision pending.

## Evidence required
`RESEARCH_NOTES.md` entry (chính nó là evidence research đã done).

## Memory files to update
- `RESEARCH_NOTES.md` (primary)
- `DECISIONS.md` (nếu research lead decision)

## Failure handling
- Unknown block → không code; escalate hoặc research thêm.
- External info chưa verify → KHÔNG dựa vào; mark `[EXTERNAL: verify]`.

## When to improve/update
- Khi một loại research recurring miss (e.g., quên check indexer) → thêm vào checklist + `CONTINUOUS_LEARNING.md`.
