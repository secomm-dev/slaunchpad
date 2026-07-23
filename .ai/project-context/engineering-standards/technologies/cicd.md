# CI_CD_STANDARD

> Technology standard — CI/CD pipelines. English. Applies to projects with a CI/CD pipeline.

## Mandatory Rules
- Pipeline gates: lint/type/test/build before deploy; quality gate before merge.
- Secrets via CI secret store (never in repo/logs); mask in output.
- Deploy is human-triggered (or gated); AI never triggers production deploy.
- Rollback step present + tested; deploy steps idempotent.

## Recommended Practices
- Fail fast (lint first); cache dependencies; parallelize independent jobs.
- Run pre-review/security checks in CI on PRs.

## Anti-patterns
Secrets in pipeline config; auto-deploy to production without gate; no rollback step; long serial pipelines.

## Validation Checklist
- [ ] Gates: lint/type/test/build + quality gate before merge/deploy
- [ ] Secrets in CI store (masked); deploy gated + human-triggered
- [ ] Rollback step present + tested

## Related
**Agents**: devops, tl · **Skills**: deploy · **Functions**: prepare-deployment-checklist · **Rules**: production-readiness, security-first · **Hooks**: before-deploy, after-deploy · **Audits**: Deployment Readiness
