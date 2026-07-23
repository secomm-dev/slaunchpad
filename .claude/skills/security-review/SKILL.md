# Security Review

## Purpose

Review a code change (diff), a new dependency, or an integration for the production AI security topics before TL review. Catches prompt-injection, untrusted-input, secret-handling, shell-safety, supply-chain, and confidentiality issues early — the security-focused counterpart of [review-code](../review-code/SKILL.md).

## When to Use

- When a change touches auth, PII, payment, checkout, order, secrets, or external input
- When introducing a new dependency, Magento module, Shopify app, or integration
- When a change reads/executes untrusted content (file, log, API response, tool/MCP output)
- Before TL review on any security-sensitive ticket
- When the blueprint flags security sensitivity (S12)

## Prerequisites

- `AGENTS.md` — high-risk areas (Section 12), escalation rules
- [production-ai-security.md](../../core/production-ai-security.md) — the authoritative standard
- `project-context/CODING_RULES.md` — code-level security rules (SQL/XSS/PII)
- The change to review (diff or file list) + the implementation plan/ticket

## Input

The code changes (diff or files), the implementation plan/ticket, and the scope (what the change is meant to do).

## Steps

1. Read [production-ai-security.md](../../core/production-ai-security.md) and the relevant `project-context/` (especially `06_KNOWN_CONSTRAINTS_AND_RISKS.md`).
2. Review the change against each security topic:
   - **Untrusted input**: is any client/API/log/file content treated as instruction or executed?
   - **Prompt injection**: does the change feed untrusted text into a prompt, tool call, or privileged action?
   - **Shell safety**: are commands built from untrusted/interpolated data? Any `eval`/`sh -c`/destructive verbs?
   - **Secrets**: any key/token/password/`.env` value in code, config, commit, or log? Any PII unmasked?
   - **Third-party code / dependencies**: new package/module/app — provenance, scopes, advisories reviewed?
   - **Client confidentiality**: does the change expose client data, cross-client info, or real store URLs/secrets externally?
   - **MCP / tool output**: does the change act on tool/integration output without validation?
   - **Code-level**: SQL injection, XSS, CSRF, auth bypass, missing input validation (cross-check `CODING_RULES.md`).
3. Map findings to severity (Critical / Warning / Note) with file + line.
4. Identify regression risks — what else might this change expose.
5. Recommend remediation and state PASS / PASS WITH WARNINGS / NEEDS FIX.

## Output Format

> **Output language:** Produce all prose in the project's `output_language` (see `.ai/AGENTS.md`; default English). Code, identifiers, file paths, and technical terms (Magento, plugin, GraphQL, checkout, etc.) ALWAYS stay English.

```markdown
## Security Review: {ticket_id / change}

### Summary
{1–2 lines: what was reviewed and the overall posture}

### Findings
#### Critical (must fix before merge)
- {finding — file:line — topic — why it's exploitable}

#### Warnings (should fix)
- {finding — file:line — topic}

#### Notes (consider)
- {finding — file:line — topic}

### Topics checked
- [x] Untrusted input
- [x] Prompt injection
- [x] Shell safety
- [x] Secrets / PII
- [x] Third-party code / dependencies
- [x] Client confidentiality
- [x] MCP / tool output
- [x] Code-level (SQL/XSS/CSRF/auth)

### Regression risks
- {what else this change could expose}

### Recommendation
{PASS / PASS WITH WARNINGS / NEEDS FIX}
```

## Quality Checklist

- [ ] All 8 topics explicitly checked (not just code-level)
- [ ] Each finding has file + line + topic + exploit rationale
- [ ] Secrets/PII scan done across the diff (including logs/responses)
- [ ] New dependencies flagged with provenance/scope/advisory notes
- [ ] Critical findings are truly blocking

## Escalation Rules

- Any secret/PII exposure, auth bypass, or payment/checkout/order security issue → **STOP**, Tier 2 escalation, do not merge
- Suspected prompt injection or untrusted-output vulnerability → flag Tier 2
- New dependency without provenance review → block until reviewed (Hard Gate)

## Example

**Input**: PR adds a "price from URL param" preview feature and a new Composer package for discount calc.

**Output**:
```markdown
## Security Review: PR-57 — URL-based price preview

### Summary
Adds `?preview_price=` URL param and a new discount-calc package. Two critical issues found.

### Findings
#### Critical (must fix before merge)
- **Untrusted price from URL** — `Product/View.php:88` reads `preview_price` and passes it to cart total calc. Client-supplied price must be revalidated server-side; this trusts the client → price manipulation. (production-ai-security.md §1)
- **Unverified dependency** — `composer.json` adds `acme/discount-calc dev-master` (unpinned, no advisory check). Hard Gate: TL approval + provenance review required. (§4, §7)

#### Warnings (should fix)
- **PII in log** — `Logger.php:22` logs the full customer email. Mask to `j***@example.com`. (§6)

#### Notes (consider)
- The preview price flows into a GraphQL resolver output — ensure it's tagged as preview-only, not the charged total.

### Topics checked
- [x] Untrusted input  [x] Prompt injection  [x] Shell safety  [x] Secrets/PII
- [x] Third-party code  [x] Client confidentiality  [x] MCP/tool output  [x] Code-level

### Regression risks
- Cached preview price leaking into real checkout totals

### Recommendation
NEEDS FIX — address untrusted price + dependency before TL review.
```
