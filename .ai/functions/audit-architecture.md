# audit-architecture

> Function (VI guidance). Copy vào `.ai/functions/audit-architecture.md`.

## Mục đích
Run Architecture audit — detect drift, coupling, integration risk, tech debt vs design đã document. Orchestrator cho Architecture audit workflow.

## Trigger
- Command: `/audit architecture`
- Prompt snippet: "Run architecture audit: read 03/04/05 + DECISIONS + source. Output finding (S0–S3) + evidence + next action per audit-workflows §1."

## Required inputs
- Scope (whole project / area / pre-milestone)

## Required project files to read
- `project-context/03_ARCHITECTURE_AND_INTEGRATIONS.md`, `04_CUSTOM_MODULES_AND_CODE_AREAS.md`, `05_API_CONTRACTS.md`
- `DECISIONS.md`, source code

## Dependencies
- Agent: project-auditor, sa
- Audit: Architecture (`workflow-guides/audit-workflows.md` §1)
- Skill: `headless-api-contract-review` (contract drift), `magento-module-analysis` (module coupling)

## Execution steps
1. Đọc architecture doc + source.
2. So sánh code vs `03`/`04` (drift).
3. Check integration contract (`05`) accuracy; module coupling; ADR recorded.
4. Rate finding S0–S3 + evidence + next action.

## Expected output
Architecture audit report: verdict + finding (S0–S3) + what's-working + next action.

## Evidence required
Report lưu `.ai/evidence/audit-architecture-{date}.md`.

## Memory files to update
- `project-context/03`/`04`/`06` (drift fix), `DECISIONS.md` (ADR missing)

## Failure handling
- S0 architecture flaw (data-integrity/security) → escalate Tier 2 ngay.

## When to improve/update
- Khi audit recurrent miss area → thêm checklist; record `CONTINUOUS_LEARNING.md`.
