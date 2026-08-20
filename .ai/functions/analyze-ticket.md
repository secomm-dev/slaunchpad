# analyze-ticket

> Function (VI guidance). Copy vào `.ai/functions/analyze-ticket.md`. Orchestration recipe — depends trên skill `task`, KHÔNG duplicate.

## Mục đích
Phân tích một ticket/task: requirement, risk, affected area, dependency, effort range, escalation need — trước khi planning.

## Trigger
- Command: `/task`
- Prompt snippet: "Analyze ticket: {ticket} against AGENTS.md + project-context/. Output: affected areas, risks (severity), dependencies, effort range, escalation."

## Required inputs
- Ticket/task description + acceptance criteria
- (Optional) change request / spec reference

## Required project files to read
- `AGENTS.md` (mode, §12 high-risk area)
- `project-context/02_BUSINESS_RULES.md`, `04_CUSTOM_MODULES_AND_CODE_AREAS.md`, `06_KNOWN_CONSTRAINTS_AND_RISKS.md`

## Dependencies
- Skill: `task`
- Agent: ba, tl
- Rule: `planning-first.md`

## Execution steps
1. Đọc `AGENTS.md §9 (mode), §12 (high-risk)` + relevant `project-context/` — skip nếu đã load trong run này (function-template §Efficiency).
2. Chạy skill `task` với ticket.
3. Cross-check high-risk area (§12); flag Tier 2 escalation nếu touch payment/checkout/order/DB/security.
4. **Phân loại container (P2A — `rules/work-item-identity.md` §3):** requirement có phải một *capability* chứa nhiều work item độc lập có thể deliver riêng không? CÓ → Feature (FEAT-) làm parent; KHÔNG → standalone Task/Bug/Spike — **KHÔNG tự tạo Feature một-ticket chỉ để ticket có parent**.
5. Nếu phân tích dẫn tới việc tạo work item mới: mint ID bằng `bin/project-ai-idgen <TYPE> --project <dir>` (collision-safe; KHÔNG quét max+1), tạo canonical record dưới `.ai/records/{features,tasks,bugs,spikes}/`, set `parent` metadata + display title H1 theo rule.
6. Produce structured output.

## Expected output
Ticket analysis: summary, scope (affected files/areas), risks (severity), missing info, approach, effort range, escalation (yes/no + tier).

## Evidence required
Analysis report lưu `.ai/evidence/{ticket}/analyze-output.md`.

## Memory files to update
- `RESEARCH_NOTES.md` (unknown/external phát hiện)
- `project-context/06` (risk mới phát hiện — propose diff)

## Failure handling
- Ticket thiếu AC → trả PM/BA (Hard Gate 2); không analyze tiếp.
- Requirement ambiguous → flag, escalate Tier 1.

## When to improve/update
- Khi team phát hiện analysis miss loại risk phổ biến (e.g., cache) → thêm vào checklist. Record trong `CONTINUOUS_LEARNING.md`.
