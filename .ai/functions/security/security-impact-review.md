# security-impact-review

> Function (VI). Security-sensitive conditional. Lifecycle: **platform**. Comprehensive security impact assessment cho một change — 8 topic + blast radius + recommendation.

## Purpose
Assess security impact toàn diện của một change/diff/feature — untrusted input, prompt injection, shell, secret, third-party, confidentiality, MCP, code-level (SQL/XSS/auth) + blast radius + fix recommendation. Broader hơn `security-review` skill (gồm cả impact analysis).

## When to use
- Change touch auth/PII/payment/checkout/order/secret/external-input/dependency.
- Pre-release security gate.
- Post-incident security assessment.

## Trigger
- Prompt snippet: "Security impact review cho {change}: 8 topic + blast radius + recommendation. Read SECURITY_BASELINE + production-ai-security."

## Required inputs
- Change (diff/feature description) + scope

## Required project files to read
- `SECURITY_BASELINE.md`, `project-context/CODING_RULES.md`, `core/production-ai-security.md` (via AGENTS §7.4)
- `project-context/06` (security risk)

## Required agents / skills / rules / hooks
- Agents: security-reviewer, sa
- Skills: `security-review`
- Rules: `security-first.md`, `engineering-standards-enforcement.md`
- Hooks: `before-security-sensitive-change`

## Required memory / evidence
- Memory: `SECURITY_BASELINE.md` (update nếu new finding), `LESSONS_LEARNED.md`
- Evidence: `.ai/evidence/{task}/security-impact-review.md`

## Execution steps (11-step)
1. Context (BASELINE/CODING_RULES) 2. Memory (06/LESSONS) 3. Rules (security-first) 4. Skill (security-review) 5. Agent (security-reviewer) 6. Research change scope 7. Review 8 topic + blast radius (ai bị affect? data flow? privilege escalation?) 8. Validate: severity per topic + critical finding 9. Evidence (report) 10. Update BASELINE/06 11. Next: Tier 2 nếu critical

## Output format
Security impact report: 8-topic finding + blast radius + severity (S0–S3) + recommendation + verdict (PASS / NEEDS FIX / BLOCK).

## Failure handling
- S0 (secret exposure/auth bypass/payment hole) → STOP; Tier 2; rotate secret.

## Related audits / standards
- Audits: Security, AI Output
- Standards: SECURITY_STANDARD, AI_ENGINEERING_STANDARD, DEVELOPMENT (secure-coding)
