# MCP Security Checklist

> **Language:** English (checklist). Copied to `.ai/mcp/mcp-security-checklist.md`. Run before adding/enabling any MCP server and during periodic security review.

## Before adding a server

- [ ] Server is on the allowed list (`mcp-policy.md`) OR has documented SA/CTO approval for an optional/disabled capability
- [ ] Provenance reviewed: maintainer, last release, source reputation (`core/production-ai-security.md` §4)
- [ ] Scope is least-privilege: project path only, read-only where possible
- [ ] No secrets required in server config (env references only)
- [ ] Open advisories checked for the server package
- [ ] TL approval recorded (Hard Gate)

## Output handling

- [ ] MCP output treated as untrusted data (`core/production-ai-security.md` §8)
- [ ] No MCP output auto-executes a command (instinct #12)
- [ ] MCP-referenced files/functions/config verified to exist before use
- [ ] External/fast-changing info marked `[EXTERNAL: verify]` in `RESEARCH_NOTES.md`

## Scope boundaries

- [ ] No access outside the project repository (no system/home/cross-client)
- [ ] No production database access (staging/dev read-only only, if enabled)
- [ ] No deploy / production-mutation / bulk-mutation capability
- [ ] No email/messaging (outward comms are human-reviewed)

## Config hygiene

- [ ] `mcp-recommended.json` committed (no secrets in it)
- [ ] Enabled servers recorded in `SECURITY_BASELINE.md` §7
- [ ] Server addition logged in `generation-log.md`
- [ ] `.gitignore` covers any local MCP secret files

## Verdict

- [ ] No Critical capability enabled without SA/CTO approval
- [ ] Result recorded (server list + approver + date)
