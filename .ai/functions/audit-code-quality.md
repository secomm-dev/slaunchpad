# audit-code-quality

> Function (VI guidance). Copy vào `.ai/functions/audit-code-quality.md`. Runs the Code Quality audit using Engineering Standards as criteria.

## Mục đích
Audit code quality cross-cutting theo Engineering Standards — SOLID/OOP/Design Pattern/Naming/Comment/Security/Performance/Doc/Tech Debt/Maintainability. Produce severity + improvement recommendation.

## Trigger
- Command: `/audit code-quality`
- Prompt snippet: "Audit code quality theo Engineering Standards: SOLID, OOP, Design Pattern, Naming, Comment, Security, Performance, Documentation, Tech Debt, Maintainability. Output S0–S3 + recommendation per audit-workflows §2."

## Required inputs
- Scope (area / module / pre-milestone)

## Required project files to read
- `.ai/project-context/engineering-standards/` (SOLID, DEVELOPMENT, CODING, REVIEW, SECURITY, PERFORMANCE, DOCUMENTATION, REFACTORING)
- `CODING_RULES.md`, `project-context/06`

## Required Engineering Standards (load trước)
ENGINEERING_PRINCIPLES → SOLID, DEVELOPMENT, CODING, DESIGN_PATTERN, REVIEW, SECURITY, PERFORMANCE, DOCUMENTATION, REFACTORING + `technologies/{tech}`.

## Dependencies
- Agent: project-auditor, tl
- Audit: Code Quality (`audit-workflows.md` §2)
- Skill: `review-code`
- Rule: `engineering-standards-enforcement.md`

## Execution steps
1. Load Required Engineering Standards.
2. Score code vs mỗi standard category (S0–S3) + evidence.
3. Identify recurring defect pattern + test gap + dead code.
4. Produce verdict + improvement recommendation (ranked, assignable).

## Expected output
Code quality audit report: verdict + finding (S0–S3, evidence) + what's-working + improvement recommendation.

## Evidence required
Report lưu `.ai/evidence/audit-code-quality-{date}.md`.

## Memory files to update
- `CONTINUOUS_LEARNING.md` (defect pattern), `project-context/06` (tech debt)

## Failure handling
- S0 (untested payment/checkout logic) → block release; fix trước.

## When to improve/update
- Khi audit miss category recurrent → thêm checklist; record.
