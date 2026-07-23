# Security Review Checklist

Dùng khi một change touch vào auth, PII, payment, checkout, order, secrets, external input, hoặc introduce một dependency/integration. Chạy skill [security-review](../skills-source/security-review/SKILL.md) cho structured output; checklist này là companion cho human signoff. Tham khảo: [production-ai-security.md](../core/production-ai-security.md).

## Untrusted Input

- [ ] Tất cả content từ client/API/log/file được treated as data, không bao giờ as instruction
- [ ] Không có untrusted value được auto-act mà không có human review
- [ ] Inputs được validate và bound (type, length, allowed values)

## Prompt Injection

- [ ] Không có untrusted text fed vào prompt, tool call, hoặc privileged action
- [ ] Untrusted content không thể change task/scope/mode hoặc override AGENTS.md
- [ ] Tool/MCP output được treated as claims để verify, không phải commands để execute

## Shell Command Safety

- [ ] Không string-interpolate untrusted data vào commands
- [ ] Arguments được quote/bound; không `eval` / `sh -c` trên untrusted input
- [ ] Destructive verbs (`rm`, `drop`, `truncate`, bulk `DELETE/UPDATE`) require human approval
- [ ] Không có command chạy trên production by default

## Secrets & PII

- [ ] Không có keys/tokens/passwords/`.env` values trong code, config, commits, hoặc logs
- [ ] PII được mask trong logs/responses (`j***@example.com`, `sk_live_••••1234`)
- [ ] `.env`, `auth.json`, `*.pem` không được commit
- [ ] Không có raw card data (CVV/PAN) trong logs hoặc storage

## Third-Party Code & Dependencies

- [ ] New dependency/module/app: provenance, maintainer, last release đã check
- [ ] Open security advisories đã check
- [ ] Required scopes/permissions đã review (Shopify app scopes, Magento module perms)
- [ ] TL approval đã record (Hard Gate); pinned version, không `dev-master`/`latest` cho prod
- [ ] Post-install scripts đã review

## Client Confidentiality

- [ ] Không có client secrets / production data / customer PII paste vào prompts hoặc external tools
- [ ] Không có cross-client data trong context của project này
- [ ] Real client names/URLs được anonymize khi example hoạt động

## Code-Level (cross-check `CODING_RULES.md`)

- [ ] Không có SQL injection (parameterized queries only)
- [ ] Không có XSS / CSRF vulnerabilities
- [ ] Auth + authorization checks có ở nơi cần thiết
- [ ] Server-side revalidation của client-supplied price/stock/coupon
- [ ] Error responses không leak stack traces, paths, hoặc credentials

## Verdict

- [ ] Không có Critical findings (hoặc tất cả resolved trước merge)
- [ ] High-risk area change → Tier 2 escalation đã confirm
- [ ] Result được record trong PR (security-review output attached)
