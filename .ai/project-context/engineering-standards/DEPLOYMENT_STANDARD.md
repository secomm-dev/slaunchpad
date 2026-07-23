# DEPLOYMENT_STANDARD

> Engineering standard — Deployment. English. References `templates/deployment-checklist-template.md` + `deploy` skill.

## Purpose / Scope / Applicability
Safe, reversible production releases. Applies to every deployment (all modes; abbreviated for hotfix but rollback plan still required).

## Mandatory Rules
- Every deploy passes the Release Gate: checklist complete + rollback plan + a second reviewer.
- Rollback triggers are **specific** (error rate > X%, checkout conversion drop, payment failure), not generic.
- DB migrations tested on staging with production-like volume; rollback migration ready.
- AI never deploys; deployment is human-initiated.
- Post-deploy: smoke test + monitoring watch window; rollback if smoke fails.

## Recommended Practices
- Deploy during agreed windows; coordinate third-party API changes.
- Keep deploy steps idempotent; automate via CI/CD.

## Anti-patterns
Generic rollback triggers; untested migration; deploy without monitoring; AI-initiated deploy; deploy without signoff.

## Validation Checklist
- [ ] Release Gate passed; rollback plan with specific triggers
- [ ] Migration tested staging; rollback ready
- [ ] Human-initiated; second reviewer signed off
- [ ] Post-deploy smoke + monitoring done

## Related
**Agents**: devops, tl · **Skills**: deploy · **Functions**: prepare-deployment-checklist, audit-performance (post-deploy) · **Rules**: production-readiness, security-first · **Hooks**: before-deploy, after-deploy · **Audits**: Deployment Readiness · **Memory**: project-context/06, LESSONS_LEARNED
