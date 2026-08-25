# AI Commerce Production Hardening Audit — 2026-08-25

- Scope: public `Secomm_AiCommerce` surface `GET/HEAD /ai/store`, `/ai/catalog/search`,
  `/ai/categories`, `/ai/products/{sku}` under crawler/bot traffic.
- Branch: `audit/ai-commerce-production-hardening` (base `62313fc8`, integration HEAD).
- Method: current-code inspection (not prior evidence), runtime read-only probes against
  `https://webhook.thanhaloha.io.vn`, repository/runtime infra inspection.
- Mode: AUDIT ONLY — no Magento code change, no infra change, no production mutation.

## 1. Current safety inventory (from merged code)

| Safeguard | Implementation | Verified |
|---|---|---|
| HTTP methods | Router ([Controller/Router.php](../../app/code/Secomm/AiCommerce/Controller/Router.php)) matches GET/HEAD only; other verbs → 405 envelope | POST/PUT/DELETE → 405 ✓ |
| Query-string bound | Router rejects >512-char QUERY_STRING → 400 | 600-char query → 400 ✓ |
| Route allowlist | Exactly 4 routes; anything else under /ai/ falls to no-route 404 | `/ai/cart` → 404 ✓ |
| Input key allowlist | SearchQueryParser rejects unknown keys → 400 invalid_parameter | ✓ |
| q length | ≤128 chars (`MAX_Q_LENGTH`), reject beyond | 5000-char q → 400 ✓ |
| filter bounds | ≤4 filters (`MAX_FILTERS`), attribute allowlist from config, value ≤64 chars | 6 filters → 400; unknown attr → 400 ✓ |
| page / page_size | page ∈ [1,50]; page_size ∈ [1, config cap ≤ hard 50]; over-max REJECTED (400), not clamped | page_size=9999 → 400 ✓ |
| price bounds | regex `\d{1,9}(\.\d{1,4})?` | code-verified |
| SKU bound | `/^[A-Za-z0-9][A-Za-z0-9_.-]{0,63}$/` → malformed = 400; nonexistent/non-public = uniform 404 (no existence leak) | ✓ |
| store validation | StoreContext\Resolver: `store` param only, code regex `^[a-z0-9_]{1,32}$`, active-store check → 400 invalid_store; absent = default store view | traversal-ish code → 400 ✓ |
| Kill switch | `seocomm_ai_commerce/general/enabled` per store view → 404 when off | code-verified (each controller) |
| Internal response cache | ResponseCache per store+route+normalized-params; tags module+store; observers on product/category/config saves flush module tag | code-verified |
| ETag / 304 | `ETag: sha1(body)`; If-None-Match match → 304 + empty body (store/search/categories when cache lifetime >0) | 304 ✓ ×4 repeats |
| HEAD | empty body, same headers | code-verified |
| Errors | deterministic envelope (`invalid_parameter` 400, `invalid_store` 400, `not_found` 404, 405, generic 500; no internals leaked) | ✓ |
| no-store on error | all error paths `no-store` | code-verified |
| **`/ai/products/{sku}` NOT cached, no ETag** | `Responder->json($data, 0)` → `no-store`, no ETag, full work every request | headers confirm ✓ (finding P1) |

## 2. Request cost classification

| Endpoint | Backend work per uncached request | Internal cache | Cost |
|---|---|---|---|
| `/ai/store` | StoreDto: config reads (MySQL config) | yes (3600s observed) | **LOW** |
| `/ai/categories` | 2 category EAV collection queries (root path + tree, store-scoped) + ONE batched url_rewrite SELECT; tree assembled in memory; no N+1 | yes (3600s observed) | **MEDIUM** |
| `/ai/catalog/search` | Elasticsuite fulltext engine (OpenSearch) query + `getSize()` count + EAV attribute load for page + ONE batch MSI `AreProductsSalable` + pricing pipeline per item (FinalPrice/RegularPrice, configurable/bundle/grouped chains) + ONE batched url_rewrite SELECT | yes (3600s observed) | **HIGH** — most abuse-sensitive |
| `/ai/products/{sku}` | ProductRepository::get (EAV, store scope) + public-eligibility checks + MSI IsProductSalable + full pricing PriceInfo (configurable/bundle chains) + url_rewrite SELECT | **NO cache, NO ETag** | **MEDIUM-HIGH**, 100% repeated cost |

Notes:
- No per-item N+1: URL resolution and salability are batched (P1.2 design); confirmed in code.
- Pricing runs the core pricing pipeline per product — for configurable/bundle this loads
  children/selections (MySQL) — this is the dominant per-item cost inside search results too.
- Distinct-q cardinality: every distinct `q` (≤128 chars) creates a new ResponseCache entry for
  3600s. A crawler enumerating query strings fills the cache backend (Redis/valkey) — bounded
  only by q length and page/filter combos. **Moderate cache-fill vector (P2).**
- 304 does NOT avoid the cache-backend hit: on If-None-Match the controller still loads the
  cached body (or recomputes on miss) and hashes it — see §5.

## 3. Edge / infra audit

- **Cloudflare tunnel IS live**: container `cloudflared-tunnel` (cloudflare/cloudflared) running;
  `env/cloudflare.env` holds TUNNEL_TOKEN; public hostname resolves through Cloudflare
  (`webhook.thanhaloha.io.vn`). Cloudflare dashboard rules (WAF / rate-limit / bot fight) are
  NOT visible from the repository → **UNKNOWN**, no in-repo evidence of any rule.
- **Nginx** (`compose/nginx/default.conf` + `src/nginx.conf`): NO `limit_req`, NO `limit_conn`,
  NO rate limiting of any kind for `/ai/*` or otherwise. `/ai/*` routes via the standard
  `index.php` front-controller location → every request reaches PHP-FPM.
- **Trusted proxy / real IP**: no `set_real_ip_from`/`real_ip_header`/CF-Connecting-IP handling
  in nginx or Magento config inspected → Magento sees the tunnel/nginx source, and per-client
  edge identifiers are not propagated. Any future per-IP limiting at nginx would need this first.
- **Effective edge rate limiting for /ai/* today: NO** at nginx, **UNKNOWN** at Cloudflare dashboard.

## 4. Rate limit design (recommendation — NOT implemented)

Preferred layer: **Cloudflare (WAF rate-limiting rules on the tunnel hostname), before PHP**.
Nginx `limit_req` as the second line (works even if traffic bypasses Cloudflare, e.g. direct
origin hits). A Magento-internal limiter is NOT recommended (cost is already paid before it
could run) and is not proposed.

Policy classes (conservative STARTING values — tune with measured data from §7, do not treat
as final):

| Class | Endpoints | Starting limit | Burst | Notes |
|---|---|---|---|---|
| A cheap | `/ai/store`, `/ai/categories` | 120 req/min per IP | 60 | cacheable 3600s; also set Cloudflare Edge Cache respecting `Cache-Control: public` |
| B product detail | `/ai/products/*` | 60 req/min per IP | 30 | uncacheable today (P1 below); per-SKU fan-out is cheap to enumerate |
| C search | `/ai/catalog/search` | 30 req/min per IP | 10 | OpenSearch + pricing per item; the flood surface |

- **429 behavior**: edge returns 429 with `Retry-After` (Cloudflare rate-limit rule response or
  nginx `limit_req_status 429` + `Retry-After`). Use a short fixed window initially (60s);
  sliding window once measured.
- **Retry-After**: ≥60s. Also ask Cloudflare to honor/emit it on its challenge/block pages.
- **Cache interaction**: class A/C responses carry `Cache-Control: public, max-age=3600` —
  enable Cloudflare edge caching for `/ai/store` and `/ai/categories` (cache key must include
  the `store` query param); keep `/ai/products/*` bypassed until P1 is fixed. Edge cache hits
  don't count against the rate budget in Cloudflare by default — verify rule counting mode.
- **Bots/crawlers**: legit AI crawlers (GPTBot, ClaudeBot, PerplexityBot…) should be
  identified by verified bot lists, not UA strings alone; Cloudflare "verified bots" category
  can get a separate, higher class-A/B budget. Malicious floods are dropped at edge regardless.
- **What belongs at edge, not Magento**: rate limits, burst, 429, Retry-After, bot
  classification, per-IP accounting. Magento should keep only the existing correctness
  mechanisms (bounds, kill switch, caching).

## 5. Cache / conditional request audit

Runtime (vi_vn):
- `/ai/store`: 200 + `ETag "e43bbb…"`; If-None-Match repeat ×4 → **304** every time. ✓
- `/ai/catalog/search`: 200 + ETag + `Cache-Control: public, max-age=3600`. ✓
- `/ai/categories`: 200 + ETag + public cache. ✓
- `/ai/products/{sku}`: **no ETag, `no-store`** — conditional requests impossible. (finding P1)

**Critical distinction — flagged clearly**: the ETag is `sha1(body)` computed in
`Responder::raw()` AFTER the body exists ([Responder.php:109-114](../../app/code/Secomm/AiCommerce/Service/Response/Responder.php#L109)).
The 304 decision happens at the very end of the request:
- WITH the internal ResponseCache warm (≤3600s): Magento still boots, resolves store, loads the
  cached JSON from the cache backend, unserializes, re-serializes, hashes, then 304. Catalog/
  search work IS avoided, but full-page-cache-style short-circuit is not — PHP-FPM is hit
  every time. 304 here saves bandwidth, not PHP.
- On cache MISS: the entire backend pipeline (OpenSearch/MSI/pricing) runs BEFORE the 304
  comparison. A conditional request whose cache entry expired pays 100% of the cost.

**"ETag avoids backend computation": NO** (partially: only via the separate internal response
cache within its lifetime, never via the ETag mechanism itself). This is acceptable for an
edge-cached deployment (edge serves the 304 without touching origin) but must be paired with
edge caching to matter under flood.

## 6. Abuse / input tests (read-only, bounded)

| Probe | Result |
|---|---|
| POST/PUT/DELETE `/ai/store` | 405 envelope ✓ |
| `q` 5000 chars | 400 invalid_parameter ✓ |
| `page_size=9999` | 400 invalid_parameter ✓ (rejected, not clamped) |
| 6 filters (2 over bound) | 400 invalid_parameter ✓ |
| non-allowlisted `filter[notreal]` | 400 invalid_parameter ✓ |
| malformed `store` (`../../etc`) | 400 invalid_store ✓ |
| unknown SKU | 404 not_found (uniform, no leak) ✓ |
| malformed SKU (`<script>`) | 400 invalid_parameter ✓ |
| `/ai/cart` (unknown route) | 404 ✓ |
| QUERY_STRING >512 | 400 ✓ |
| conditional GET ×4 | 304 deterministic ✓ |

All deterministic; no 5xx, no HTML error pages, no stack traces.

## 7. Observability

Current state:
- nginx `access.log` exists (default combined format, container-local, no /ai-specific
  metrics, no aggregation). php container `var/log/` for Magento logs (system.log/exception.log).
- Cloudflare Analytics exists at the dashboard (not queryable from repo) → **UNKNOWN** coverage.
- No APM confirmed in this environment (Blackfire defined but commented out in compose).

Minimum production recommendation (no platform build):
1. Cloudflare Analytics: requests + status-class (2xx/4xx/429/5xx) filtered to hostname/path
   `/ai/*` — available out of the box; export/alert on 429 and 5xx rates.
2. nginx access log with `$request_time` (log format tweak) + periodic awk/goaccess-style
   summary per `/ai` endpoint, or ship the log to whatever collector exists.
3. Alert thresholds (start): `/ai/catalog/search` p95 > 2s; 429 rate > 5% of /ai traffic;
   any 5xx on /ai/*; OpenSearch errors in `var/log` (`SearchUnavailableException` count —
   already surfaced as deterministic `search_unavailable` 503 envelope).
4. PHP saturation: existing container metrics (php-fpm active processes) — container-level,
   no module change needed.

## 8. Security boundary recheck

- No cart/checkout/order/customer/address/payment/session surface: Router matches only the 4
  read routes; controllers are `HttpGetActionInterface`; DTOs (StoreDto/ProductDto/
  SearchResultDto/CategoryDto) emit catalog/public fields only — code-verified.
- No PII: no customer/session/order tables referenced anywhere in the module.
- No exact inventory quantity: `Availability` returns only `in_stock|out_of_stock`; salable-qty
  API deliberately unused (documented in code).
- Store selection is explicit bounded `?store=<code>` (regex + active check); no cookie/store
  switching accepted for /ai context.
- Uniform 404 prevents existence-probing of non-public products.
- **PASS** — with one data note: search results currently return `public_url: null` /
  `canonical_url: null` for products in vi_vn (no product url_rewrite rows at store 3 — the
  same data class as the earlier category audit), a data-quality gap, not a security issue.

## 9. Findings

**P0** (0)

None. No injection/mutation/leak path; all bounds reject deterministically.

**P1** (2)

1. **`/ai/products/{sku}` is uncacheable (no-store, no ETag)** — every request pays full
   EAV+MSI+pricing cost; under a SKU-enumerating crawler this is the easiest sustained-load
   surface, and edge caching cannot be enabled for it (origin always hit). Candidate minimal
   fix (NEXT TASK, not implemented): short internal response cache with product-save
   invalidation (observer already exists for the module tag) + ETag, OR explicitly accept and
   rely on edge rate limiting class B.
2. **No edge rate limiting exists at nginx and none is evidenced at Cloudflare** (tunnel live).
   Until a Cloudflare rate-limit rule (and/or nginx `limit_req` + real-IP handling) is added,
   the surface is unprotected against floods. Infra config change, NOT Magento code.

**P2** (3)

1. Cache-fill vector: distinct `q`/param combos each occupy a ResponseCache entry for 3600s;
   bounded crawler enumeration can pressure Redis/valkey memory. Mitigations: shorten cache
   lifetime for search, cap distinct cacheable q cardinality, or rely on edge rate limits.
2. 304 (and every cacheable hit) still pays a full Magento bootstrap + cache-backend round
   trip — edge caching (Cloudflare) should front these endpoints so 304s are served at edge.
3. Trusted-proxy/real-IP propagation absent — future nginx-level per-IP limits and accurate
   logging need `CF-Connecting-IP`/real_ip config first.

## 10. Verdict

**READY_WITH_EDGE_CONFIG** — safe to enable in production ONCE the edge controls of §4 are in
place (Cloudflare rate-limiting rules for classes A/B/C + edge caching for `/ai/store` and
`/ai/categories` + real-IP handling if nginx limiting is added). No Magento code change is
required for correctness; P1.1 (product-endpoint caching) is recommended but can be deferred
behind edge rate limiting.

**Exact recommended next action**: configure Cloudflare rate-limiting rules on the tunnel
hostname per §4 classes A/B/C and enable edge caching for `/ai/store` + `/ai/categories`
(infra task, outside this repo). Optionally schedule the P1.1 product-cache mini-task.
