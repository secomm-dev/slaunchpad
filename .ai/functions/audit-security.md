# audit-security

> Function (VI guidance). Copy vào `.ai/functions/audit-security.md`.

## Mục đích
Run Security audit — verify production AI security + code-level security across project. Orchestrator cho `security-review` skill + Security audit.

## Trigger
- Command: `/audit security`
- Prompt snippet: "Run security audit: 8 topic (untrusted/prompt-injection/shell/secret/third-party/confidentiality/MCP/code-level) + secret scan. Finding S0–S3 per audit-workflows §3."

## Required inputs
- Scope (security-sensitive change / periodic / post-incident)

## Required project files to read
- `SECURITY_BASELINE.md`, `project-context/CODING_RULES.md`, `project-context/06`
- Dependency manifest, `.gitignore`, recent diff

## Dependencies
- Skill: `security-review`
- Agent: security-reviewer, project-auditor
- Audit: Security (`audit-workflows.md` §3)
- Rule: `security-first.md`
- Hook: `before-security-sensitive-change`

## Execution steps
1. Đọc baseline + coding rule.
2. Run `security-review` trên sample sensitive change.
3. Secret scan repo/history; check 8 topic.
4. Dependency advisory; MCP policy compliance.
5. Rate S0–S3 + evidence + next action.

## Expected output
Security audit report: verdict + finding (S0–S3) + next action.

## Evidence required
Report lưu `.ai/evidence/audit-security-{date}.md`.

## Memory files to update
- `SECURITY_BASELINE.md` (sau incident), `LESSONS_LEARNED.md`, `project-context/06`

## Failure handling
- S0 (secret exposure / auth bypass / payment hole) → STOP, rotate secret, escalate Tier 2.

## When to improve/update
- Khi new threat pattern → thêm topic + update baseline + record.
