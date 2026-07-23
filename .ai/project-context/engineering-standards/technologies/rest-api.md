# REST_API_STANDARD

> Technology standard — REST API. English. Applies to REST APIs (Laravel/custom/middleware).

## Mandatory Rules
- Versioned routes (`/v1/`); consistent resource naming; standard status codes.
- Standard error envelope (`error`/`code`/`message`/`details`); never leak stack traces/paths.
- Auth via guard/middleware; authorize per resource; rate-limit + idempotency on writes.
- Input validation (Form Requests / DTO); pagination (cursor/offset); no unbounded list.

## Recommended Practices
- Document contracts (OpenAPI) shared with clients; idempotency keys for POST that create.

## Anti-patterns
Unversioned routes; inconsistent error shapes; missing authz; unbounded list; stack-trace leak.

## Validation Checklist
- [ ] Versioned routes; standard codes + error envelope
- [ ] Auth + authz per resource; rate-limit + idempotency on writes
- [ ] Validation + pagination; no leak

## Related
**Agents**: api-integration, security-reviewer · **Skills**: headless-api-contract-review · **Functions**: review-api-contract, validate-idempotency · **Rules**: backward-compatibility, security-first · **Audits**: API, Security
