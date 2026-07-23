# OPERATIONS_STANDARD

> Engineering standard — Operations. English.

## Purpose / Scope / Applicability
Run the system safely in production. Applies to monitoring, environments, secrets/env, incident readiness.

## Mandatory Rules
- Monitoring covers critical flows (checkout/payment/search/order sync); alerts on error rate + latency.
- Environments parity tracked (staging vs production config diffs documented).
- Secrets via env/secret manager only; rotation policy documented.
- Runbooks/rollback steps accessible; on-call/owner known.
- No direct production data edits without human + rollback plan.

## Recommended Practices
- Health checks + dashboards per critical integration.
- Periodic fire-drill on rollback + incident runbook.

## Anti-patterns
No monitoring on critical path; staging/prod drift; secrets in repo; undocumented ops steps.

## Validation Checklist
- [ ] Monitoring on critical flows; alerts tuned
- [ ] Env parity documented; secrets managed + rotated
- [ ] Runbooks accessible; owner known

## Related
**Agents**: devops, tl · **Skills**: incident-analysis, deploy · **Functions**: prepare-deployment-checklist, summarize-progress · **Audits**: Deployment Readiness, Operations · **Memory**: SECURITY_BASELINE, project-context/06
