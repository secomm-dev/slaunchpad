# split-feature-into-tasks

> Function (VI guidance). Copy vào `.ai/functions/split-feature-into-tasks.md`.
> **Phase 1a:** subtask split KHÔNG còn là default — chỉ khi parallel ownership cần (xem `no-duplicate-knowledge.md`). Canonical = một `.ai/records/features/FEAT-*.md`; legacy ticket/task list retained non-default.

## Mục đích
Break một feature thành task có thể implement — mỗi task có AC, scope hẹp, dependency, sequence.

## Trigger
- Prompt snippet: "Split feature {spec} thành task. Mỗi task: AC testable, affected files, dependency, effort range, sequence."

## Required inputs
- Approved feature spec / mini-spec

## Required project files to read
- `project-context/04_CUSTOM_MODULES_AND_CODE_AREAS.md` (module/area)
- `project-context/03` (integration affect split)
- Spec (từ `create-feature-spec`)

## Dependencies
- Skill: `task`
- Agent: tl, ba
- Rule: `planning-first.md`, `project-conventions-first.md`

## Execution steps
1. Đọc spec + code area.
2. Decompose thành task theo area/dependency (mỗi task một concern).
3. Mỗi task: AC, affected files, dependency, effort range, sequence.
4. Identify critical path + task block release.
5. TL review split.

## Expected output
Task list (ticket) với AC + dependency + sequence + effort.

## Evidence required
Task list trong PM system + `.ai/evidence/{feature}/task-split.md`.

## Memory files to update
- `NEXT_TASK.md` (task đầu tiên)
- `project-context/06` (risk phức tạp)

## Failure handling
- Task quá lớn (>16h) → split thêm hoặc promote Mode A.
- Dependency circular → flag, SA resolve.

## When to improve/update
- Khi một pattern split recurring (e.g., luôn tách checkout riêng) → record `CONTINUOUS_LEARNING.md`.
