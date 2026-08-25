# TASK-AIC-PDC1 implementation plan

Specification ID: SPEC-TASK-AIC-PDC1
Spec: `.ai/specs/SPEC-TASK-AIC-PDC1-ai-commerce-product-detail-cache.md`

1. `Controller/Products/View.php` — inject `ResponseCache`; lookup route `product`
   params `['sku' => trim]` after isEnabled check; warm hit returns cached; miss →
   `ProductFetcher::fetch`, save with configured lifetime, respond with lifetime.
2. `Observer/StockInvalidation.php` — `clean_cache_by_tags` observer; object is
   `IdentityInterface` with a product-tag identity (`cat_p` or `cat_p_*`) →
   `InvalidateCache::cleanAll()`.
3. `etc/events.xml` — register observer.
4. Tests:
   - `Test/Unit/Controller/Products/ViewTest.php` — cold miss (fetch executed + save called),
     warm hit (fetch NOT executed), lifetime forwarded to responder, disabled → 404 no cache IO.
   - `Test/Unit/Observer/StockInvalidationTest.php` — product identities → cleanAll;
     non-product identities → no clean; non-identity object → no clean.
   - Extend `ResponseCacheTest` — product route key includes store + SKU; different SKU →
     different key (already implied; add explicit cases).
5. Validation: full AiCommerce + AiDiscoverability suites, php -l, PHPCS,
   `project-ai-validate --check-specs`, `git diff --check`.
6. Runtime proof vi_vn + store isolation + invalidation probe (reversible), cleanup.
7. Evidence report, README/CHANGELOG (AiCommerce 1.x entry), commit, push branch.
