# create-solution-design

> Function (VI guidance). Copy vào `.ai/functions/create-solution-design.md`.

## Mục đích
Draft solution design / architecture option cho một feature — component, data flow, integration contract, tech decision. SA approve; AI draft.

## Trigger
- Prompt snippet: "Draft solution design cho {feature}: architecture option (pros/cons), integration contract, DB/API impact, risk. Read project-context/03, 05."

## Required inputs
- Approved requirement / feature spec
- Constraints (performance, scalability, integration)

## Required project files to read
- `project-context/03_ARCHITECTURE_AND_INTEGRATIONS.md`, `05_API_CONTRACTS.md`, `04_CUSTOM_MODULES_AND_CODE_AREAS.md`
- `DECISIONS.md` (đừng re-litigate), `RESEARCH_NOTES.md`

## Dependencies
- Agent: sa
- Rule: `backward-compatibility.md`, `research-first.md`, `no-duplicate-knowledge.md`
- Skill: `headless-api-contract-review` (nếu API), `magento-module-analysis` (nếu Magento)

## Execution steps
1. Research-first: đọc architecture/integration hiện tại.
2. Draft 1–2 architecture option với pros/cons.
3. Identify integration contract / DB schema / API impact.
4. Flag backward-compatibility risk + migration path.
5. Draft ADR (context/decision/rationale/consequence) — SA approve.

## Expected output
Solution design doc + ADR draft (append `DECISIONS.md` sau SA approve).

## Evidence required
ADR + design doc lưu `.ai/specs/` hoặc `docs/`.

## Memory files to update
- `DECISIONS.md` (ADR sau approve)
- `RESEARCH_NOTES.md`, `project-context/03`/`05` (nếu change)

## Failure handling
- Architecture impact Tier 2 → escalate SA/CTO trước khi finalize.
- Integration contract unconfirmed → flag, không assume.

## When to improve/update
- Khi một architecture pattern trở thành project convention → record `CONTINUOUS_LEARNING.md`; nếu reusable cross-project → propose backport toolkit.
