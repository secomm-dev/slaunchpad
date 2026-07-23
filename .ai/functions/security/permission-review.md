# permission-review

> Function (VI). Security-sensitive conditional. Lifecycle: **platform**. Review permission/auth — role/ACL/scope/API access, least-privilege, no privilege escalation.

## Purpose
Review permission/auth change — role/ACL (Magento admin role), scope (Shopify app scope), API access (OAuth/token), auth guard — ensure least-privilege, no escalation, proper enforcement.

## When to use
- Change touch auth/ACL/role/scope/permission.
- Shopify app scope review.
- Magento admin role/ACL change.
- API auth mechanism change.

## Trigger
- Prompt snippet: "Permission review: {auth/ACL/scope change}. Least-privilege, no escalation, proper enforcement. Read SECURITY_BASELINE."

## Required inputs
- Permission/auth change (role/ACL/scope/guard)

## Required project files to read
- `SECURITY_BASELINE.md` (§5 auth), `project-context/05_API_CONTRACTS.md` (auth), `09`/`10`

## Required agents / skills / rules / hooks
- Agents: security-reviewer, sa
- Skills: `security-review`
- Rules: `security-first.md`, `backward-compatibility.md`
- Hooks: `before-security-sensitive-change`

## Required memory / evidence
- Memory: `SECURITY_BASELINE.md`, `DECISIONS.md` (auth decision)
- Evidence: `.ai/evidence/{task}/permission-review.md`

## Execution steps
1. Context (BASELINE/05) 2. Memory 3. Rules 4. Skill 5. Agent 6. Research current permission state 7. Review: least-privilege (over-privileged? unused scope?), no escalation (user → admin path?), enforcement (auth check ở mọi protected resource?), backward-compat (role change break existing?) 8. Validate: Tier 2 nếu auth mechanism change 9. Evidence 10. Memory 11. Next

## Output format
Permission review: scope/role mapping + least-privilege assessment + escalation risk + enforcement gap + verdict.

## Failure handling
- Over-privileged scope/role → trim to least-privilege.
- Missing auth check → add.
- Privilege escalation path → block; Tier 2.

## Related audits / standards
- Audits: Security
- Standards: SECURITY_STANDARD, MAGENTO_STANDARD/SHOPIFY_STANDARD (admin ACL/app scope), AI_ENGINEERING_STANDARD
