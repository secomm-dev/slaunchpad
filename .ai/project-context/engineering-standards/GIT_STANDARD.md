# GIT_STANDARD

> Engineering standard — Git/VCS. English.

## Purpose / Scope / Applicability
Keep history clean, changes reversible, branches safe. Applies to all repos.

## Mandatory Rules
- Branch strategy documented; protected branches enforced.
- One PR = one concern; commit messages `type(scope): description`.
- Never commit secrets/`.env`/`auth.json`/`*.pem` (enforced via `.gitignore`).
- Merges are human actions; AI never merges/pushes to protected branches.
- Rebase/merge per team convention; no force-push to shared/protected branches without TL approval.

## Recommended Practices
- Small, reviewable commits; reference the ticket in the PR.
- Squash noisy commits before merge when the team prefers clean history.

## Anti-patterns
Mixed-concern commits; secret in history; force-push to main; AI auto-merging; huge unreviewable PRs.

## Validation Checklist
- [ ] One concern per PR; messages follow convention
- [ ] No secrets committed; `.gitignore` covers sensitive files
- [ ] Protected branches respected; no AI merge/push
- [ ] History clean per team convention

## Related
**Agents**: developer, tl, devops · **Functions**: prepare-pr, implement-task · **Rules**: security-first · **Hooks**: before-commit · **Audits**: Code Quality
