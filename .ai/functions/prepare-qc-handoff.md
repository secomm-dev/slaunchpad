# prepare-qc-handoff

> Function (VI guidance). Copy vào `.ai/functions/prepare-qc-handoff.md`.

## Mục đích
Chuẩn bị handoff cho QC: deployment note (what changed, affected area), test case, regression scope, known risk — để QC test hiệu quả.

## Trigger
- Prompt snippet: "Prepare QC handoff cho {ticket/release}: deployment note, test case, regression area, known risk. Read project-context/03, 04."

## Required inputs
- Merged PR / release change list
- Test case (từ `generate-test-cases`)

## Required project files to read
- `project-context/03_ARCHITECTURE_AND_INTEGRATIONS.md`, `04_CUSTOM_MODULES_AND_CODE_AREAS.md`
- `project-context/06` (risk affect regression)

## Dependencies
- Agent: qc, developer
- Skill: `testcase`
- Hook: `before-pr` (TL side: code-review)

## Execution steps
1. Summarize what changed + affected area.
2. Link test case (từ `generate-test-cases`).
3. Define regression scope (adjacent module, shared service, integration).
4. List known risk (06) affect test.
5. Hand cho QC.

## Expected output
QC handoff doc: change summary + test case ref + regression scope + risk note.

## Evidence required
Handoff doc lưu `.ai/handoff/qc-{ticket}.md`.

## Memory files to update
- `CURRENT_STATE.md` (status: in QC)

## Failure handling
- Regression scope unclear → flag, SA/TL define.
- Affected integration → coordinate test với integration env.

## When to improve/update
- Khi QC phát hiện area regression mà handoff miss → thêm default regression area + record.
