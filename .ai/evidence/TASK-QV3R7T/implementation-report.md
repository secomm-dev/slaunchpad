# TASK-QV3R7T (LA-22) — Secomm_AiCommerce Implementation Evidence

- Base SHA: `a0159889` (spec/plan rev 2 accepted HEAD)
- Branch: `task/la-22-ai-commerce-read-layer-impl`
- Date: 2026-08-24 (rev 2: TL acceptance corrections)
- Runtime: local docker stack, base URL `https://webhook.thanhaloha.io.vn/` (curl -k)

## 0. TL review corrections (rev 2)

- **app/etc/config.php**: the branch no longer changes it —
  `git diff task/la-22-ai-commerce-read-layer-spec...HEAD -- app/etc/config.php` is **empty**.
  The earlier deviation (module enablement committed, LC-30 precedent) is reverted; module
  enablement is a local runtime concern only.
- **Availability contradiction (bundles)**: resolved by bounded root-cause audit → §4.
- **Pricing/currency contract**: re-audited, Store mutation replaced by pin+restore,
  type-aware pricing implemented → §5.

## 1. Static validation

| Check | Result |
|---|---|
| PHP lint (all module files) | ALL_LINT_OK |
| PHPCS Magento2 (php+xml) | 0 errors, 0 warnings (exit 0) |
| Unit tests | 46 tests, 74 assertions, OK (1 pre-existing allure PHPUnit warning) |
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

## 4. Availability policy (resolved — MSI salability, proven by storefront truth)

Facade = `IsProductSalableInterface` (single) / `AreProductsSalableInterface` (batch, ONE call per page), stock id via `GetStockIdForCurrentWebsite`. No quantity is ever emitted.

**Full type matrix (2026-08-24, rev 2 audit):**

| Product | Facade | MSI direct | legacy `cataloginventory_stock_status` | GraphQL stock_status | Storefront page availability |
|---|---|---|---|---|---|
| 24-WG085/86/87 (simple) | in_stock | true | 1 | IN_STOCK | "In stock" |
| 24-WG087 forced OOS (temp DB edit) | **out_of_stock** | false | 0 | — | **"Out of stock"** |
| bedding-linen (configurable) | in_stock | true | 1 | IN_STOCK | "In stock" |
| 24-WG085-bundle-fixed | in_stock | true | 1 | **OUT_OF_STOCK** | **"In stock"** |
| 24-WG085-bundle-dynamic | in_stock | true | 1 | **OUT_OF_STOCK** | **"In stock"** |
| 24-WG085_Group-22222 (grouped) | in_stock | true | 1 | IN_STOCK | "In stock" |

**Chosen policy: MSI salability.** The deciding question — "does the storefront actually sell
the product to an anonymous visitor?" — answers it empirically:
- The bundle product page (`sprite-yoga-strap3.html` / `-2.html`) renders an active
  "Add to Cart" and an **"In stock"** availability block — identical to what MSI reports.
- Negative control: temporarily forcing 24-WG087 out of stock made the facade report
  `out_of_stock` and the storefront render **"Out of stock"** (state fully restored).
- GraphQL's `OUT_OF_STOCK` for bundles is **genuinely incorrect for public semantics**:
  `Magento\InventoryGraphQl\Model\Resolver\StockStatusProvider::resolve()` returns
  OUT_OF_STOCK unconditionally when the bundle product model carries no
  `bundle_selection_ids` custom option (always true outside the add-to-cart flow) — a core
  resolver limitation, confirmed unaffected by a full `catalogsearch_fulltext` reindex.
- No custom stock algorithm invented: `IsProductSalableInterface` (IsProductSalableConditionChain)
  is Magento's own composite-aware salability service and agrees with storefront truth on
  every observed type and both stock states.

## 5. Pricing / currency policy (resolved)

**Store currency configuration (captured from `core_config_data`, store `default`):**
- `currency/options/base` = **EUR**
- `currency/options/default` = **EUR** (default display currency)
- `currency/options/allow` = VND
- Anonymous storefront renders **EUR** (`€`) for every tested product.
- GraphQL labels its amounts **VND** with the same numeric values — a graphql-area currency
  quirk of this unusual config (default=EUR, allow=VND); the storefront and the store-default
  contract both say EUR. Facade emits the **store default display currency (EUR)** — the
  deterministic, cookie-independent choice — and the numeric values match storefront/GraphQL
  exactly in every case.

**No permanent Store mutation remains.** `PublicPrice` now pins the default currency for the
read and **restores the previous current currency immediately afterwards**
(pin→read→restore); previously it left the store mutated for the rest of the request.
Documented + regression-tested (`testCurrencyIsPinnedAndRestored`).

**Type-aware public price semantics** (what an anonymous visitor's storefront shows):
- simple: FinalPrice (special-price aware); `regular_value` when regular differs.
- configurable: parent FinalPrice = **minimum final price** across variations (core
  ConfigurablePrice behavior; storefront shows the same minimum).
- bundle: **`BundleFinalPrice::getMinimalPrice()`** — the storefront "as low as" value,
  verified identical to the product page and its JSON-LD `"price"` (14.00 €), not the
  `getValue()` default-selection price (42) that storefront visitors never see listed.
- grouped: FinalPrice = minimum associated-product price (storefront entry price).

**Parity matrix (2026-08-24, facade vs storefront vs GraphQL numerics):**

| Product (type) | Facade | Storefront displayed | GraphQL numeric |
|---|---|---|---|
| 24-WG086 (simple) | 170000 EUR | 170.000,00 € / "price":"170000.00" | 170000 |
| 24-WG087 (simple) | 21 EUR | "price":"21.00" | 21 |
| 24-WG087 + special 15 (temp) | value 15, regular_value 21 EUR | "price":"15.00" | — |
| bedding-linen (configurable) | 195 EUR (min final) | 195,00 € | 195 (=regular=minimal) |
| 24-WG085-bundle-fixed (bundle) | 14 EUR (minimal) | "price":"14.00" | 42 (regularPrice; GraphQL has no storefront-minimal match) |
| 24-WG085-bundle-dynamic (bundle) | 14 EUR (minimal) | "price":"14.00" | 14 |
| 24-WG085_Group-22222 (grouped) | 14 EUR (min associated) | 14,00 € | — |

Fixed during this audit: the search endpoint previously emitted wrong prices (0 / 42) because
collection items lacked price attributes — `price, special_price, special_from_date,
special_to_date, price_type` are now explicitly selected; search summaries now equal the
detail endpoint for every SKU. Guest semantics: no session → public tier only, no group/
tier prices emitted. All temporary catalog edits (forced OOS, special price, website
unassignment) were reverted and re-verified.

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

1. **Bundle GraphQL stock_status** — resolved (§4): GraphQL is the outlier, storefront truth
   proves MSI correct; core resolver limitation documented, escalated to TL.
2. `AreProductsSalableInterface` batches one facade call for N SKUs (per locked contract); internally Magento loops per SKU — bounded by page_size cap.
3. Single-store dataset (`default` only) — multi-store isolation verified structurally (cache key + per-store config + resolver unit tests), no fake multi-store runtime evidence fabricated.
4. Integration/security test suites: exercised as live runtime matrix above (this stack has no separate integration test harness for app/code modules); unit suite covers parser/store/URL/pricing/availability/DTO/errors/cache-key/router.
5. **Store/website scope**: verified live — temporarily unassigning 24-WG087 from its website
   made `/ai/products/24-WG087` return 404; restored to 200.
6. No scope growth: no UCP/MCP/llms.txt integration added; LC-30 untouched
   (`Secomm_AiDiscoverability` files not modified on this branch).

## 10. Files

Module `app/code/Secomm/AiCommerce/` — see README.md in the module root for the architecture map (Router → Controller → StoreContext/Resolver → Services (Search/ProductFetcher/CategoryTree/PublicPrice/Availability/PublicUrlResolver) → DTO/Responder → ResponseCache; observers → InvalidateCache).
