# TASK-QV3R7T (LA-22) — Secomm_AiCommerce Implementation Evidence

- Base SHA: `a0159889` (spec/plan rev 2 accepted HEAD)
- Branch: `task/la-22-ai-commerce-read-layer-impl`
- Date: 2026-08-24
- Runtime: local docker stack, base URL `https://webhook.thanhaloha.io.vn/` (curl -k)

## 1. Static validation

| Check | Result |
|---|---|
| PHP lint (all module files) | ALL_LINT_OK |
| PHPCS Magento2 (php+xml) | 0 errors, 0 warnings (exit 0) |
| Unit tests | 44 tests, 74 assertions, OK (1 pre-existing allure PHPUnit warning) |
| `setup:di:compile` | "Generated code and dependency injection configuration successfully" |
| XML well-formedness (all etc/*.xml) | XML_OK |
| `project-ai-validate --check-specs` | VALID (0 FAIL, 0 WARN) |
| `git diff --check` | clean |

## 2. Route/status matrix (curl, 2026-08-24)

| Request | Status | Body |
|---|---|---|
| GET `/ai/store` | 200 | store_code default, locale vi_VN, currency EUR, base_url |
| GET `/ai/store?store=default` | 200 | same |
| GET `/ai/store?store=NOPE` | **400** `invalid_store` | fixed envelope |
| HEAD `/ai/store` | 200 | content-length: 0 (same headers, empty body) |
| POST `/ai/store` | **405** `method_not_allowed` + `Allow: GET, HEAD` | fixed envelope |
| PUT `/ai/products/24-WG086` | **405** `method_not_allowed` | fixed envelope |
| GET `/ai/catalog/search?q=strap` | 200 | total_count 6, items with names/prices/availability |
| GET `/ai/catalog/search?q=strap&evil=1` | **400** `invalid_parameter` | unknown key rejected |
| GET `/ai/store?<600 chars>` | **400** `invalid_parameter` | query-string cap 512 |
| GET `/ai/products/24-WG086` | 200 | full DTO (price, availability, categories, canonical) |
| GET `/ai/products/24-MB01` (nonexistent) | **404** `not_found` | same envelope as disabled/non-visible |
| GET `/ai/foo`, `/ai/graphql` | 404 (Magento no-route HTML) | no /ai surface matched |
| GET with `enabled=0` | **404** all /ai routes | config kill-switch verified |
| GET `?store=…` + `Authorization: Bearer fake` | 200, identical body | header changes nothing |
| GET with `X-Store: NOPE` header | 200, default store | header ignored (param-only contract) |
| GET search + `If-None-Match` (ETag) | **304** | ETag `sha1`, deterministic |

Notes:
- `?store=NOPE` initially returned `invalid_parameter`; fixed by adding `InvalidStoreException` (subclass) → distinct `invalid_store` code per contract. Unit test added.
- POST initially 302-redirected (core CsrfValidator hijack); fixed: `MethodNotAllowed` implements `CsrfAwareActionInterface` (`validateForCsrf() => true`) — documented in-class.

## 3. Search seam (Elasticsuite parity)

- `SearchService` uses module virtualType `secomm_aic_fulltext_collection_factory` (frontend/di.xml) = real `Product\CollectionFactory` with `instanceName` = `Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection`, which the Smile Elasticsuite **global** preference (module-elasticsuite-catalog/etc/di.xml:87) swaps to `Smile\...Product\Fulltext\Collection` — the exact collection class the storefront layer consumes.
- The core `Fulltext\CollectionFactory` is a **virtualType**, not a class, so it cannot be constructor-hinted; the virtualType+parent-hint pattern is the core-sanctioned workaround (same pattern core uses for `ItemCollectionProvider`).
- Runtime parity: facade `q=strap` total_count **6** = GraphQL `products(search:"strap")` total_count **6**, identical SKU sets and order.
- One behavioral difference found (documented, not a defect): `applySort()` is typed against `AbstractDb`, because the Smile collection does NOT extend the core Fulltext resource model (a naive type hint caused a runtime 500, caught and fixed during evidence).

## 4. Availability parity (locked seam)

Facade = `IsProductSalableInterface` (single) / `AreProductsSalableInterface` (batch, ONE call per page), stock id via `GetStockIdForCurrentWebsite`. No quantity is ever emitted.

Cross-check vs GraphQL `stock_status` (2026-08-24):

| SKU | Facade | GraphQL | MSI direct | legacy `cataloginventory_stock_status` |
|---|---|---|---|---|
| 24-WG085 (simple) | in_stock | IN_STOCK | true | 1 |
| 24-WG086 (simple) | in_stock | IN_STOCK | true | 1 |
| 24-WG087 (simple) | in_stock | IN_STOCK | true | 1 |
| 24-WG085-bundle-fixed | in_stock | **OUT_OF_STOCK** | true | 1 |
| 24-WG085-bundle-dynamic | in_stock | **OUT_OF_STOCK** | true | 1 |

**Documented contradiction (bundles only) — root cause identified, no facade change:**
`Magento\InventoryGraphQl\Model\Resolver\StockStatusProvider::resolve()` returns `OUT_OF_STOCK` unconditionally for bundle products when the product model has no `bundle_selection_ids` custom option (true for any catalog query outside add-to-cart). Full-text reindex (`indexer:reindex catalogsearch_fulltext`) did not change GraphQL's answer; MSI direct (`IsProductSalableInterface::execute`) and the legacy stock_status table both say in-stock. Per the locked contract the facade uses the MSI salability APIs, which agree with all other sources; the GraphQL bundle answer is a core resolver limitation, not a facade divergence. Flagged for TL awareness.

## 5. Pricing parity (verify-don't-guess)

- Facade pins the store default currency (`setCurrentCurrencyCode(default)`) before reading `FinalPrice`/`RegularPrice` — one conversion inside the core pipeline, no facade-side second conversion.
- `24-WG086`: facade `{value: 170000, currency: EUR}` = storefront product page `170.000,00 €`. GraphQL numeric value 170000 identical (GraphQL labels VND — graphql-area currency quirk; base currency is EUR and `currency/options/default` = EUR, so the facade label is the store-default contract value).
- `24-WG085-bundle-fixed`: facade 42 EUR = GraphQL regularPrice 42. Storefront shows "14,00 €" (bundle minimal/entry price by design) — minimal-vs-final price semantic difference, documented.
- Guest semantics: no customer session → public tier only; no group/tier prices emitted.

## 6. Category tree query-count evidence (N+1 rule)

With DB query logging enabled (`dev:query-log:enable`, cold caches), GET `/ai/categories` produced exactly **2** category-entity SELECTs:
1. `SELECT e.* FROM catalog_category_entity e WHERE entity_id = '2'` (root path lookup)
2. one joined SELECT `... WHERE e.path LIKE '1/2/%' AND is_active ...` (whole visible tree, attribute values joined)

No per-node/per-depth queries. Hierarchy assembled in memory, depth-bounded by config (`category_depth`, hard cap 10).

## 7. Cache behavior

- Internal cache key includes store id + route + sha1(ksorted normalized params minus `store`); tags `[secomm_aic, secomm_aic_store_{id}]`.
- HTTP: `Cache-Control` public + `ETag` (sha1 of body); `If-None-Match` revalidation → **304** verified.
- `/ai/products/{sku}` intentionally NOT internally cached (correctness-first: detail freshness; documented).
- Errors sent with no-store.
- Observers (product/category save/delete commit, config change) clean the whole module tag — correctness-first, because a product change can make an absent product ENTER search results (documented in `InvalidateCache`).

## 8. Security negatives (all verified by curl)

- No GraphQL document is ever accepted or proxied (`/ai/graphql` → 404; unknown params → 400).
- `Authorization: Bearer <token>` produces byte-identical success body (no privilege gain).
- `X-Store` header ignored — store scope ONLY via `?store=` param.
- Cookies/session cannot change scope: store resolution reads only the query param.
- No `qty`, no `cost`, no tier prices, no admin attributes, no PII in any 200 body (inspected outputs).
- No stack traces/paths/SQL in error envelopes (fixed strings only); 500s logged server-side with generic public envelope.
- GET/HEAD only; every other verb → 405 before any commerce logic (CSRF-exempt 405 action documented).
- Query string ≤512 chars; q ≤128; filters ≤4 allowlisted attrs; page ≤50; page_size ≤ config cap (hard 50); SKU pattern-bounded.

## 9. Honest limitations / deviations

1. **Bundle GraphQL stock_status contradiction** — see §4; facade follows the locked MSI contract; core GraphQL resolver limitation documented, escalated to TL.
2. **Bundle price semantics** — facade emits FinalPrice (matches GraphQL `regularPrice`); storefront entry price ("as low as") differs by Magento design.
3. `AreProductsSalableInterface` batches one facade call for N SKUs (per locked contract); internally Magento loops per SKU — bounded by page_size cap.
4. Single-store dataset (`default` only) — multi-store isolation verified structurally (cache key + per-store config + resolver unit tests), no fake multi-store runtime evidence fabricated.
5. Integration/security test suites: exercised as live runtime matrix above (this stack has no separate integration test harness for app/code modules); unit suite covers parser/store/URL/pricing/availability/DTO/errors/cache-key/router.
6. `app/etc/config.php` committed with `Secomm_AiCommerce` enabled (LC-30 precedent).

## 10. Files

Module `app/code/Secomm/AiCommerce/` — see README.md in the module root for the architecture map (Router → Controller → StoreContext/Resolver → Services (Search/ProductFetcher/CategoryTree/PublicPrice/Availability/PublicUrlResolver) → DTO/Responder → ResponseCache; observers → InvalidateCache).
