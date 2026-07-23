# GRAPHQL_STANDARD

> Technology standard — GraphQL API. English. Applies to GraphQL APIs (Magento/Shopify/custom).

## Mandatory Rules
- Schema-first; explicit types; deprecate (don't silently remove) fields.
- Resolvers thin (delegate to service); authorize per field; no N+1 (DataLoader/batch).
- Input validation at the schema + resolver; bound query depth/complexity.
- Version via deprecation + new fields; never silent breaking change.

## Recommended Practices
- Persisted queries for public clients; cost-based limits.

## Anti-patterns
Fat resolvers; missing authz on fields; unbounded queries; silent field removal.

## Validation Checklist
- [ ] Schema typed; resolvers thin + authorized; no N+1
- [ ] Depth/complexity bounded; breaking changes versioned via deprecation

## Related
**Agents**: api-integration, security-reviewer · **Skills**: headless-api-contract-review · **Functions**: review-api-contract, review-code · **Rules**: backward-compatibility, security-first · **Audits**: API
