# PERFORMANCE_STANDARD

> Engineering standard — Performance engineering. English. Focuses on eCommerce critical paths (checkout/payment/catalog/search/cache).

## Purpose / Scope / Applicability
Keep critical paths fast; prevent perf regressions. Applies to hot-path code, caching, queries, and pre-launch.

## Mandatory Rules
- No N+1 queries (eager-load/batch); large reads paginated.
- Index hot columns; avoid `SELECT *`.
- Cache keys include every affecting parameter (customer group, store, date); invalidation correct.
- Keep external calls off the checkout critical path.
- No blocking sync on storefront; payloads bounded.

## Recommended Practices
- Measure before optimizing; load-test critical paths pre-launch.
- Prefer read replicas/cache for read-heavy catalog/search.
- Defer non-critical work to queue/cron.

## Anti-patterns
Premature optimization; unmeasured scaling; cache without correct keys; sync external call in checkout; unpaginated bulk read.

## Validation Checklist
- [ ] No N+1 on hot path; indexes present
- [ ] Cache keys complete; no stale risk
- [ ] External calls off critical path
- [ ] Payloads paginated/bounded
- [ ] Critical path load-tested pre-launch

## Related
**Agents**: performance-reviewer, sa · **Skills**: magento-checkout-impact, shopify-theme-review · **Functions**: audit-performance, refactor-code · **Rules**: production-readiness · **Audits**: Performance · **Memory**: CONTINUOUS_LEARNING, project-context/06
