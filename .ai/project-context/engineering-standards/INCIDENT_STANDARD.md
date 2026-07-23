# INCIDENT_STANDARD

> Engineering standard — Incident response. English. References `workflow-guides/incident-workflow.md` + Mode D.

## Purpose / Scope / Applicability
Resolve production incidents fast and learn from them. Applies to every Sev1/2/3 incident.

## Mandatory Rules
- Communicate first (notify TL/CTO/PM per severity) before/while fixing.
- Assess severity (Sev1 outage/data-loss/payment → CTO leads; Sev2 TL; Sev3 TL).
- Minimal, targeted fix (stop the bleeding); no refactor/cleanup during incident.
- Rollback considered before complex fix.
- Retro ticket within 24h for **all** severities; post-mortem for Sev1 (within 5 working days).
- Evidence preserved (logs, timeline, fix diff).

## Recommended Practices
- Parallelize: dev fixes while TL reviews; comms owner keeps stakeholders updated.
- Root cause = confirmed, not suspected (5-whys for Sev1).

## Anti-patterns
Fixing more than the symptom; skipping TL review; no comms; no retro; complex prod fix over rollback.

## Validation Checklist
- [ ] Severity assessed; right people notified
- [ ] Fix minimal + safe; rollback considered
- [ ] Retro ticket <24h; post-mortem for Sev1
- [ ] Evidence preserved; root cause confirmed

## Related
**Agents**: tl, devops, security-reviewer · **Skills**: incident-analysis, security-review · **Functions**: audit-security, summarize-progress · **Rules**: security-first, production-readiness · **Hooks**: after-deploy · **Audits**: AI Output, Security · **Memory**: LESSONS_LEARNED, SECURITY_BASELINE
