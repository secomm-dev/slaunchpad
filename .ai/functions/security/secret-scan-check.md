# secret-scan-check

> Function (VI). Security-sensitive conditional. Lifecycle: **platform**. Scan code/diff/history cho secret — API key, token, password, `.env`, credential, card data.

## Purpose
Scan diff/repo cho secret bị committed hoặc sắp commit — catch trước khi leak. Pattern-based + context-aware.

## When to use
- Before commit (pre-commit hook reinforcement).
- Before PR.
- Periodic repo scan.
- Post-incident (suspected leak).

## Trigger
- Prompt snippet: "Secret scan check: scan {diff/repo} cho API key, token, password, .env, credential, card. Mask; report finding."

## Required inputs
- Scope (diff / full repo / specific path)

## Required project files to read
- `.gitignore`, `SECURITY_BASELINE.md` (§2 secret types)

## Required agents / skills / rules / hooks
- Agents: security-reviewer
- Rules: `security-first.md`, `evidence-required.md`
- Hooks: `before-commit`, `before-pr`

## Required memory / evidence
- Memory: `SECURITY_BASELINE.md` (if leak found → incident), `LESSONS_LEARNED.md`
- Evidence: `.ai/evidence/{task}/secret-scan.md`

## Execution steps
1. Context (.gitignore/BASELINE) 2. Memory 3. Rules 4. Agent 5. Scan: pattern match (API key, token, password, private key, `.env`, card PAN/CVV, connection string) + context (is it real or test/example?) 6. Validate: filter false positive (test fixture, documentation placeholder) 7. Report: finding (file+line+type) + severity 8. Evidence 9. If leak → incident (rotate, Tier 2) 10. Memory 11. Next

## Output format
Secret scan report: finding (file+line+type+context) + severity + action (rotate/block/clean).

## Failure handling
- Real secret found → STOP; rotate immediately; remove from history; Tier 2 incident.
- `.gitignore` missing sensitive file → add.

## Related audits / standards
- Audits: Security
- Standards: SECURITY_STANDARD, GIT_STANDARD, AI_ENGINEERING_STANDARD
