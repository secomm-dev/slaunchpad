# MCP — Disabled by Default

> **Language:** English (config/policy). Copied to `.ai/mcp/mcp-disabled-by-default.md`.

These MCP capabilities are **disabled by default** because they can cause production harm, data loss, secret exposure, or cross-client contamination. Enabling any of them requires **explicit SA/CTO approval**, a documented justification, and an entry in `SECURITY_BASELINE.md` §7.

| Capability | Why disabled | If ever needed |
|---|---|---|
| **shell-exec** (arbitrary command execution) | Command injection; destructive action from untrusted input | Almost never. If required, sandbox + allowlist + human approval per command. |
| **write-filesystem** (outside project repo) | Modify files outside project scope; cross-project contamination | Restrict to project path; never system/home dirs. |
| **deploy / production-mutation** | Deployment is a human-initiated action (Hard Gate); AI must not deploy | Never enable — deploy stays human-driven (`core/ai-operating-principles.md`). |
| **secrets-access** (read env/vault/keys) | Secret exposure; MCP output may leave trust boundary | Never. Secrets stay in env/secret manager only. |
| **email / messaging** (send outward comms) | Client-facing communication is human-reviewed (Hard Gate) | Never via MCP. |
| **bulk-data-mutation** (bulk update/delete) | Data loss / integrity risk | Never via MCP. Bulk ops are human + script with rollback. |
| **cross-client-access** (access another client's repo/data) | Client confidentiality / IP risk | Never. One MCP config per project, scoped to that project only. |
| **production database** (any access) | Production data integrity / PII | Never via MCP. DB MCP is staging/dev read-only only, with explicit flag. |

## Rule

If a project believes it needs a disabled capability, the path is:
1. Document the need + risk + mitigation in a change request.
2. SA/CTO approval (Tier 2).
3. Record in `SECURITY_BASELINE.md` §7 + `generation-log.md`.
4. Re-review after any incident or quarterly.

## Cross-References

- MCP policy: `mcp-policy.md`
- Untrusted-output rule: `core/production-ai-security.md` §8
- Recommended config: `mcp-recommended.json`
- Security checklist: `mcp-security-checklist.md`
